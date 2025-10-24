<?php

namespace UPMarket\Subscriptions\Admin;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table para planos (versão simplificada)
 *
 * @package UPMarket\Subscriptions\Admin
 */
class PlansListTable extends \WP_List_Table
{
    /**
     * Construtor
     */
    public function __construct()
    {
        parent::__construct([
            'singular' => 'plano',
            'plural' => 'planos',
            'ajax' => false
        ]);
    }

    /**
     * Colunas padrão
     */
    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'name' => 'Nome',
            'price' => 'Preço',
            'billing_period' => 'Período',
            'trial_period_days' => 'Trial',
            'is_active' => 'Status',
            'created_at' => 'Criado em'
        ];
    }

    /**
     * Colunas sortable
     */
    public function get_sortable_columns(): array
    {
        return [
            'name' => ['name', 'asc'],
            'price' => ['price', 'desc'],
            'created_at' => ['created_at', 'desc']
        ];
    }

    /**
     * Prepara os itens
     */
    public function prepare_items(): void
    {
        $this->process_bulk_action();

        global $wpdb;

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        // Paginação
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Busca
        $where = '1=1';
        if (!empty($_REQUEST['s'])) {
            $search = sanitize_text_field($_REQUEST['s']);
            $where .= $wpdb->prepare(" AND name LIKE %s", '%' . $wpdb->esc_like($search) . '%');
        }

        // Ordenação
        $orderby = 'created_at';
        $order = 'DESC';

        if (!empty($_REQUEST['orderby'])) {
            $orderby = sanitize_text_field($_REQUEST['orderby']);
        }

        if (!empty($_REQUEST['order'])) {
            $order = sanitize_text_field($_REQUEST['order']);
        }

        // Busca dados
        $table_name = $wpdb->prefix . 'upmkt_subscription_plans';
        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        // Total de itens para paginação
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE {$where}");

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }

    /**
     * Coluna padrão
     */
    public function column_default($item, $column_name): string
    {
        return $item->$column_name ?? '';
    }

    /**
     * Coluna checkbox
     */
    public function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="plan[]" value="%s" />',
            $item->id
        );
    }

    /**
     * Coluna do nome
     */
    public function column_name($item): string
    {
        $actions = [
            'edit' => sprintf(
                '<a href="%s">Editar</a>',
                admin_url('admin.php?page=upmkt-subscription-plans&action=edit&plan_id=' . $item->id)
            ),
            'delete' => sprintf(
                '<a href="%s" style="color:#a00;" onclick="return confirm(\'Tem certeza?\')">Excluir</a>',
                wp_nonce_url(admin_url('admin.php?page=upmkt-subscription-plans&action=delete&plan_id=' . $item->id), 'delete_plan_' . $item->id)
            )
        ];

        return sprintf(
            '<strong>%s</strong> %s',
            esc_html($item->name),
            $this->row_actions($actions)
        );
    }

    /**
     * Coluna do preço
     */
    public function column_price($item): string
    {
        return 'R$ ' . number_format($item->price, 2, ',', '.');
    }

    /**
     * Coluna do período
     */
    public function column_billing_period($item): string
    {
        $periods = [
            'day' => 'Dia',
            'month' => 'Mês',
            'year' => 'Ano'
        ];

        $period = $periods[$item->billing_period] ?? $item->billing_period;
        $frequency = $item->billing_frequency > 1 ? " a cada {$item->billing_frequency}" : '';

        return $period . $frequency;
    }

    /**
     * Coluna do trial
     */
    public function column_trial_period_days($item): string
    {
        return $item->trial_period_days > 0 ? $item->trial_period_days . ' dias' : 'Não';
    }

    /**
     * Coluna de status
     */
    public function column_is_active($item): string
    {
        if ($item->is_active) {
            return '<span class="upmkt-status upmkt-status-active">Ativo</span>';
        } else {
            return '<span class="upmkt-status upmkt-status-cancelled">Inativo</span>';
        }
    }

    /**
     * Coluna de data
     */
    public function column_created_at($item): string
    {
        return date('d/m/Y H:i', strtotime($item->created_at));
    }

    /**
     * Mensagem quando não há itens
     */
    public function no_items(): void
    {
        echo 'Nenhum plano encontrado.';
    }

    /**
     * Ações em massa
     */
    public function get_bulk_actions(): array
    {
        return [
            'activate' => 'Ativar',
            'deactivate' => 'Desativar',
        ];
    }

    /**
     * Processa ações em massa
     */
    public function process_bulk_action(): void
    {
        if (!isset($_POST['plan']) || !is_array($_POST['plan'])) {
            return;
        }

        $plan_ids = array_map('intval', $_POST['plan']);
        $action = $this->current_action();

        if (!$action) {
            return;
        }

        foreach ($plan_ids as $plan_id) {
            switch ($action) {
                case 'activate':
                    $this->activate_plan($plan_id);
                    break;
                case 'deactivate':
                    $this->deactivate_plan($plan_id);
                    break;
                case 'delete':
                    $this->delete_plan($plan_id);
                    break;
            }
        }
    }

    /**
     * Ativa um plano
     */
    private function activate_plan(int $plan_id): void
    {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'upmkt_subscription_plans',
            ['is_active' => 1],
            ['id' => $plan_id],
            ['%d'],
            ['%d']
        );
    }

    /**
     * Desativa um plano
     */
    private function deactivate_plan(int $plan_id): void
    {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'upmkt_subscription_plans',
            ['is_active' => 0],
            ['id' => $plan_id],
            ['%d'],
            ['%d']
        );
    }

    /**
     * Exclui um plano
     */
    private function delete_plan(int $plan_id): void
    {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'upmkt_subscription_plans',
            ['id' => $plan_id],
            ['%d']
        );
    }

}

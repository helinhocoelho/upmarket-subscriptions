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
            'cb' => '',
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
     * Prepara os itens da tabela
     */
    public function prepare_items(): void
    {
        global $wpdb;

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $table_name = $wpdb->prefix . 'upmkt_subscription_plans';
        $where = '1=1';
        $query_params = [];

        // Filtro por status (opcional, mas deixei caso queira usar no futuro)
        if (!empty($_REQUEST['status']) && $_REQUEST['status'] !== 'all') {
            $status = sanitize_text_field($_REQUEST['status']);
            if ($status === 'active' || $status === '1') {
                $where .= " AND p.is_active = %d";
                $query_params[] = 1;
            } elseif ($status === 'inactive' || $status === '0') {
                $where .= " AND p.is_active = %d";
                $query_params[] = 0;
            }
        }

        // Ordenação
        $allowed_orderby = ['id', 'name', 'price', 'billing_period', 'trial_period_days', 'is_active', 'created_at'];
        $orderby = in_array($_REQUEST['orderby'] ?? '', $allowed_orderby, true)
            ? 'p.' . sanitize_text_field($_REQUEST['orderby'])
            : 'p.created_at';
        $order = strtoupper($_REQUEST['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        // Query principal
        $sql = "
        SELECT p.*
        FROM {$table_name} p
        WHERE {$where}
        ORDER BY {$orderby} {$order}
        LIMIT %d OFFSET %d
    		";

        $query_params[] = $per_page;
        $query_params[] = $offset;

        // Só usa 'prepare' se houver placeholders
        if (!empty($query_params)) {
            $this->items = $wpdb->get_results($wpdb->prepare($sql, ...$query_params));
        } else {
            $this->items = $wpdb->get_results($sql);
        }

        // Total de itens
        $count_sql = "SELECT COUNT(*) FROM {$table_name} p WHERE {$where}";
        $count_params = array_slice($query_params, 0, -2);

        if (!empty($count_params)) {
            $total_items = (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$count_params));
        } else {
            $total_items = (int) $wpdb->get_var($count_sql);
        }

        // Paginação
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
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
     * Coluna do nome
     */
    public function column_name($item): string
    {
        $id = $item->id ?? 0;

        $actions = [
            'edit' => sprintf(
                '<a href="%s">Editar</a>',
                esc_url(admin_url('admin.php?page=upmkt-subscription-plans&action=edit&plan_id=' . $id))
            ),
            'delete' => sprintf(
                '<a href="%s" style="color:#a00;" onclick="return confirm(\'Tem certeza?\')">Excluir</a>',
                wp_nonce_url(admin_url('admin.php?page=upmkt-subscription-plans&action=delete&plan_id=' . $id), 'delete_plan_' . $id)
            )
        ];

        return sprintf(
            '<strong>%s</strong> %s',
            esc_html($item->name ?? ''),
            $this->row_actions($actions)
        );
    }

    /**
     * Coluna do preço
     */
    public function column_price($item): string
    {
        $price = isset($item->price) ? (float) $item->price : 0.0;
        return 'R$ ' . number_format($price, 2, ',', '.');
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

        $billing_period = $item->billing_period ?? '';
        $period = $periods[$billing_period] ?? $billing_period;
        $frequency = (!empty($item->billing_frequency) && $item->billing_frequency > 1)
            ? " a cada {$item->billing_frequency}"
            : '';

        return $period . $frequency;
    }

    /**
     * Coluna do trial
     */
    public function column_trial_period_days($item): string
    {
        $days = isset($item->trial_period_days) ? (int) $item->trial_period_days : 0;
        return $days > 0 ? $days . ' dias' : 'Não';
    }

    /**
     * Coluna de status
     */
    public function column_is_active($item): string
    {
        $active = isset($item->is_active) ? (bool) $item->is_active : false;
        return $active
            ? '<span class="upmkt-status upmkt-status-active">Ativo</span>'
            : '<span class="upmkt-status upmkt-status-cancelled">Inativo</span>';
    }

    /**
     * Coluna de data
     */
    public function column_created_at($item): string
    {
        if (empty($item->created_at)) {
            return '';
        }

        $ts = strtotime($item->created_at);
        if ($ts === false || $ts === null) {
            return '';
        }

        return date('d/m/Y H:i', $ts);
    }

    /**
     * Mensagem quando não há itens
     */
    public function no_items(): void
    {
        echo 'Nenhum plano encontrado.';
    }

}

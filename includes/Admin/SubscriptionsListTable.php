<?php

namespace UPMarket\Subscriptions\Admin;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table para assinaturas (versão simplificada)
 *
 * @package UPMarket\Subscriptions\Admin
 */
class SubscriptionsListTable extends \WP_List_Table
{
    /**
     * Construtor
     */
    public function __construct()
    {
        parent::__construct([
            'singular' => 'assinatura',
            'plural' => 'assinaturas',
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
            'id' => 'ID',
            'user_id' => 'Usuário',
            'plan_id' => 'Plano',
            'status' => 'Status',
            'start_date' => 'Data Início',
            'next_billing_date' => 'Próxima Cobrança',
            'created_at' => 'Criado em'
        ];
    }

    /**
     * Colunas sortable
     */
    public function get_sortable_columns(): array
    {
        return [
            'id' => ['id', 'desc'],
            'start_date' => ['start_date', 'desc'],
            'created_at' => ['created_at', 'desc']
        ];
    }

    /**
     * Prepara os itens
     */
    public function prepare_items(): void
    {
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
            $where .= $wpdb->prepare(" AND (id = %d OR user_id = %d)", $search, $search);
        }

        // Filtro de status
        if (!empty($_REQUEST['status']) && $_REQUEST['status'] !== 'all') {
            $status = sanitize_text_field($_REQUEST['status']);
            $where .= $wpdb->prepare(" AND status = %s", $status);
        }

        // Ordenação
        $orderby = 'id';
        $order = 'DESC';

        if (!empty($_REQUEST['orderby'])) {
            $orderby = sanitize_text_field($_REQUEST['orderby']);
        }

        if (!empty($_REQUEST['order'])) {
            $order = sanitize_text_field($_REQUEST['order']);
        }

        // Busca dados
        $table_name = $wpdb->prefix . 'upmkt_subscriptions';
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
            '<input type="checkbox" name="subscription[]" value="%s" />',
            $item->id
        );
    }

    /**
     * Coluna do usuário
     */
    public function column_user_id($item): string
    {
        $user = get_userdata($item->user_id);
        $user_name = $user ? $user->display_name : 'Usuário #' . $item->user_id;
        $user_email = $user ? $user->user_email : 'N/A';

        return sprintf(
            '<strong>%s</strong><br><small>%s</small>',
            esc_html($user_name),
            esc_html($user_email)
        );
    }

    /**
     * Coluna do plano
     */
    public function column_plan_id($item): string
    {
        // TODO: Buscar nome do plano
        return 'Plano #' . $item->plan_id;
    }

    /**
     * Coluna de status
     */
    public function column_status($item): string
    {
        $status_text = [
            'active' => 'Ativa',
            'pending' => 'Pendente',
            'cancelled' => 'Cancelada',
            'expired' => 'Expirada',
            'paused' => 'Pausada'
        ];

        $status = $status_text[$item->status] ?? $item->status;

        return sprintf(
            '<span class="upmkt-status upmkt-status-%s">%s</span>',
            esc_attr($item->status),
            esc_html($status)
        );
    }

    /**
     * Coluna de ações
     */
    public function column_id($item): string
    {
        $actions = [
            'edit' => sprintf(
                '<a href="%s">Editar</a>',
                admin_url('admin.php?page=upmkt-subscriptions&action=edit&subscription_id=' . $item->id)
            ),
            'cancel' => sprintf(
                '<a href="%s" style="color:#a00;" onclick="return confirm(\'Tem certeza?\')">Cancelar</a>',
                wp_nonce_url(admin_url('admin.php?page=upmkt-subscriptions&action=cancel&subscription_id=' . $item->id), 'cancel_subscription_' . $item->id)
            )
        ];

        return sprintf(
            '<strong>#%s</strong> %s',
            $item->id,
            $this->row_actions($actions)
        );
    }

    /**
     * Filtros
     */
    public function extra_tablenav($which): void
    {
        if ($which !== 'top') {
            return;
        }
        ?>
        <div class="alignleft actions">
            <label for="filter-by-status" class="screen-reader-text">Filtrar por status</label>
            <select name="status" id="filter-by-status">
                <option value="all">Todos os status</option>
                <option value="active" <?php selected($_REQUEST['status'] ?? '', 'active'); ?>>Ativas</option>
                <option value="pending" <?php selected($_REQUEST['status'] ?? '', 'pending'); ?>>Pendentes</option>
                <option value="cancelled" <?php selected($_REQUEST['status'] ?? '', 'cancelled'); ?>>Canceladas</option>
                <option value="expired" <?php selected($_REQUEST['status'] ?? '', 'expired'); ?>>Expiradas</option>
                <option value="paused" <?php selected($_REQUEST['status'] ?? '', 'paused'); ?>>Pausadas</option>
            </select>
            <?php submit_button('Filtrar', '', 'filter_action', false); ?>
        </div>
        <?php
    }

    /**
     * Mensagem quando não há itens
     */
    public function no_items(): void
    {
        echo 'Nenhuma assinatura encontrada.';
    }
}

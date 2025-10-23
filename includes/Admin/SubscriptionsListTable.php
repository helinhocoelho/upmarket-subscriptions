<?php

namespace UPMarket\Subscriptions\Admin;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table para assinaturas (versão completa)
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
            'user_info' => 'Usuário',
            'plan_info' => 'Plano',
            'status' => 'Status',
            'dates' => 'Datas',
            'next_billing' => 'Próxima Cobrança',
            'actions' => 'Ações'
        ];
    }

    /**
     * Colunas sortable
     */
    public function get_sortable_columns(): array
    {
        return [
            'id' => ['id', 'desc'],
            'next_billing' => ['next_billing_date', 'desc'],
            'dates' => ['start_date', 'desc']
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
            $where .= $wpdb->prepare(
                " AND (s.id = %d OR s.user_id = %d OR u.user_email LIKE %s OR u.display_name LIKE %s)",
                $search,
                $search,
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }

        // Filtro de status
        if (!empty($_REQUEST['status']) && $_REQUEST['status'] !== 'all') {
            $status = sanitize_text_field($_REQUEST['status']);
            $where .= $wpdb->prepare(" AND s.status = %s", $status);
        }

        // Ordenação
        $orderby = 's.id';
        $order = 'DESC';

        if (!empty($_REQUEST['orderby'])) {
            $allowed_orderby = ['id', 'start_date', 'next_billing_date'];
            $orderby = in_array($_REQUEST['orderby'], $allowed_orderby) ? 's.' . $_REQUEST['orderby'] : 's.id';
        }

        if (!empty($_REQUEST['order'])) {
            $order = strtoupper($_REQUEST['order']) === 'ASC' ? 'ASC' : 'DESC';
        }

        // Busca dados com JOIN para usuários
        $table_name = $wpdb->prefix . 'upmkt_subscriptions';
        $users_table = $wpdb->users;

        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, u.display_name, u.user_email 
                 FROM {$table_name} s 
                 LEFT JOIN {$users_table} u ON s.user_id = u.ID 
                 WHERE {$where} 
                 ORDER BY {$orderby} {$order} 
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );

        // Total de itens para paginação
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} s WHERE {$where}");

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
    public function column_user_info($item): string
    {
        $user_data = $this->get_user_display_data($item->user_id);
        $user_link = $user_data['exists'] ? admin_url('user-edit.php?user_id=' . $item->user_id) : '#';

        if ($user_data['exists']) {
            return sprintf(
                '<strong><a href="%s">%s</a></strong><br>
             <small>%s</small><br>
             <small>ID: %d</small>',
                esc_url($user_link),
                esc_html($user_data['display_name']),
                esc_html($user_data['user_email']),
                esc_html($item->user_id)
            );
        } else {
            return sprintf(
                '<strong style="color: #d63638;">%s</strong><br>
             <small>%s</small><br>
             <small>ID: %d</small>',
                esc_html($user_data['display_name']),
                esc_html($user_data['user_email']),
                esc_html($item->user_id)
            );
        }
    }

    /**
     * Coluna do plano
     */
    public function column_plan_info($item): string
    {
        $plan_name = $this->get_plan_name($item->plan_id);
        $plan_price = $this->get_plan_price($item->plan_id);

        return sprintf(
            '<strong>%s</strong><br>
             <small>R$ %s</small><br>
             <small>ID: %d</small>',
            esc_html($plan_name),
            esc_html($plan_price),
            esc_html($item->plan_id)
        );
    }

    /**
     * Coluna de status
     */
    public function column_status($item): string
    {
        $status_text = $this->get_status_text($item->status);
        $status_class = 'upmkt-status-' . $item->status;

        return sprintf(
            '<span class="upmkt-status %s">%s</span>',
            esc_attr($status_class),
            esc_html($status_text)
        );
    }

    /**
     * Coluna de datas
     */
    public function column_dates($item): string
    {
        $start_date = date('d/m/Y', strtotime($item->start_date));
        $created_date = date('d/m/Y', strtotime($item->created_at));

        return sprintf(
            '<strong>Início:</strong> %s<br>
             <strong>Criada:</strong> %s',
            esc_html($start_date),
            esc_html($created_date)
        );
    }

    /**
     * Coluna próxima cobrança
     */
    public function column_next_billing($item): string
    {
        $next_billing = date('d/m/Y H:i', strtotime($item->next_billing_date));
        $now = new \DateTime();
        $billing_date = new \DateTime($item->next_billing_date);
        $days_until = $now->diff($billing_date)->days;

        $class = '';
        if ($days_until <= 1) {
            $class = 'upmkt-billing-soon';
        } elseif ($days_until <= 7) {
            $class = 'upmkt-billing-upcoming';
        }

        return sprintf(
            '<span class="%s">%s</span><br>
             <small>%d dias</small>',
            esc_attr($class),
            esc_html($next_billing),
            esc_html($days_until)
        );
    }

    /**
     * Coluna de ações
     */
    public function column_actions($item): string
    {
        $actions = [];
        $subscription = new \UPMarket\Subscriptions\Entities\Subscription($item->id);

        // Ação Editar sempre disponível
        $actions['edit'] = sprintf(
            '<a href="%s" title="Editar assinatura">✏️ Editar</a>',
            admin_url('admin.php?page=upmkt-subscriptions-list&action=edit&subscription_id=' . $item->id)
        );

        // Ações baseadas no status
        if ($item->status !== 'cancelled') {
            $actions['cancel'] = sprintf(
                '<a href="%s" title="Cancelar assinatura" style="color:#a00;" onclick="return confirm(\'Tem certeza que deseja cancelar esta assinatura?\')">🚫 Cancelar</a>',
                wp_nonce_url(
                    admin_url('admin.php?page=upmkt-subscriptions-list&action=cancel&subscription_id=' . $item->id),
                    'cancel_subscription_' . $item->id
                )
            );
        }

        $actions['delete'] = sprintf(
            '<a href="%s" title="Excluir permanentemente" style="color:#a00;" onclick="return confirm(\'Tem certeza que deseja EXCLUIR permanentemente esta assinatura?\')">🗑️ Excluir</a>',
            wp_nonce_url(
                admin_url('admin.php?page=upmkt-subscriptions-list&action=delete&subscription_id=' . $item->id),
                'delete_subscription_' . $item->id
            )
        );

        return implode(' | ', $actions);
    }

    /**
     * Obtém nome do plano
     */
    private function get_plan_name(int $plan_id): string
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscription_plans';
        $plan_name = $wpdb->get_var(
            $wpdb->prepare("SELECT name FROM {$table_name} WHERE id = %d", $plan_id)
        );

        return $plan_name ?: 'Plano não encontrado';
    }

    /**
     * Obtém preço do plano
     */
    private function get_plan_price(int $plan_id): string
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscription_plans';
        $plan_price = $wpdb->get_var(
            $wpdb->prepare("SELECT price FROM {$table_name} WHERE id = %d", $plan_id)
        );

        return $plan_price ? number_format($plan_price, 2, ',', '.') : '0,00';
    }

    /**
     * Retorna texto do status
     */
    private function get_status_text(string $status): string
    {
        $statuses = [
            'active' => '🟢 Ativa',
            'pending' => '🟡 Pendente',
            'cancelled' => '🔴 Cancelada',
            'expired' => '⚫ Expirada'
        ];

        return $statuses[$status] ?? $status;
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
            </select>
            
            <?php submit_button('Filtrar', '', 'filter_action', false); ?>
            
            <?php if (!empty($_REQUEST['s']) || !empty($_REQUEST['status'])): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=upmkt-subscriptions-list')); ?>" class="button">
                    Limpar Filtros
                </a>
            <?php endif; ?>
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

    /**
     * Ações em massa
     */
    public function get_bulk_actions(): array
    {
        return [
            'activate' => 'Ativar',
            'cancel' => 'Cancelar',
            'delete' => 'Excluir'
        ];
    }

    /**
     * Processa ações em massa
     */
    public function process_bulk_action(): void
    {
        if (!isset($_POST['subscription']) || !is_array($_POST['subscription'])) {
            return;
        }

        $subscription_ids = array_map('intval', $_POST['subscription']);
        $action = $this->current_action();

        if (!$action) {
            return;
        }

        $results = [
            'success' => 0,
            'error' => 0
        ];

        foreach ($subscription_ids as $subscription_id) {
            try {
                $subscription = new \UPMarket\Subscriptions\Entities\Subscription($subscription_id);

                if (!$subscription->exists()) {
                    $results['error']++;
                    continue;
                }

                switch ($action) {
                    case 'activate':
                        $subscription->set_status('active');
                        break;
                    case 'cancel':
                        $subscription->cancel();
                        break;
                    case 'delete':
                        $this->delete_subscription($subscription_id);
                        $results['success']++;
                        continue 2; // Pular o save() para exclusão
                }

                if ($subscription->save()) {
                    $results['success']++;
                } else {
                    $results['error']++;
                }

            } catch (\Exception $e) {
                $results['error']++;
            }
        }

        // Mostrar resultados
        if ($results['success'] > 0) {
            add_action('admin_notices', function () use ($results, $action) {
                $action_text = [
                    'activate' => 'ativadas',
                    'cancel' => 'canceladas',
                    'delete' => 'excluídas'
                ];

                echo '<div class="notice notice-success is-dismissible"><p>' .
                     sprintf('%d assinatura(s) %s com sucesso.', $results['success'], $action_text[$action]) .
                     '</p></div>';
            });
        }

        if ($results['error'] > 0) {
            add_action('admin_notices', function () use ($results) {
                echo '<div class="notice notice-error is-dismissible"><p>' .
                     sprintf('Erro ao processar %d assinatura(s).', $results['error']) .
                     '</p></div>';
            });
        }
    }

    /**
     * Exclui uma assinatura
     */
    private function delete_subscription(int $subscription_id): void
    {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'upmkt_subscriptions',
            ['id' => $subscription_id],
            ['%d']
        );

        // Excluir metadados
        $wpdb->delete(
            $wpdb->prefix . 'upmkt_subscription_meta',
            ['subscription_id' => $subscription_id],
            ['%d']
        );
    }

    /**
     * Renderiza notificações para assinaturas
     */
    private function render_subscriptions_admin_notices(): void
    {
        if (isset($_GET['message'])) {
            $messages = [
                'updated' => 'Assinatura atualizada com sucesso!',
                'cancelled' => 'Assinatura cancelada com sucesso!',
                'resumed' => 'Assinatura retomada com sucesso!',
                'deleted' => 'Assinatura excluída com sucesso!'
            ];

            $message = $messages[$_GET['message']] ?? 'Ação realizada com sucesso!';

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }

    /**
     * Obtém dados do usuário com fallbacks de segurança
     */
    private function get_user_display_data(int $user_id): array
    {
        $user = get_userdata($user_id);

        if (!$user) {
            return [
                'display_name' => 'Usuário não encontrado',
                'user_email' => 'N/A',
                'exists' => false
            ];
        }

        return [
            'display_name' => $user->display_name ?: 'Usuário sem nome',
            'user_email' => $user->user_email ?: 'Sem email',
            'exists' => true
        ];
    }
}

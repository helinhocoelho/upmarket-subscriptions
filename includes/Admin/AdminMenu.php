<?php

namespace UPMarket\Subscriptions\Admin;

/**
 * Gerencia o menu administrativo do plugin
 *
 * @package UPMarket\Subscriptions\Admin
 */
class AdminMenu
{
    /**
     * Construtor
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Adiciona menus no admin
     */
    public function add_admin_menus(): void
    {
        // Menu principal
        add_menu_page(
            'UP Market Subscriptions',
            'Assinaturas',
            'manage_upmkt_subscriptions',
            'upmkt-subscriptions',
            [$this, 'render_dashboard_page'],
            'dashicons-update',
            30
        );

        // Submenus
        add_submenu_page(
            'upmkt-subscriptions',
            'Dashboard - Assinaturas',
            'Dashboard',
            'manage_upmkt_subscriptions',
            'upmkt-subscriptions',
            [$this, 'render_dashboard_page']
        );

        add_submenu_page(
            'upmkt-subscriptions',
            'Todas as Assinaturas',
            'Todas as Assinaturas',
            'manage_upmkt_subscriptions',
            'upmkt-subscriptions-list',
            [$this, 'render_subscriptions_page']
        );

        add_submenu_page(
            'upmkt-subscriptions',
            'Planos de Assinatura',
            'Planos',
            'manage_upmkt_subscriptions',
            'upmkt-subscription-plans',
            [$this, 'render_plans_page']
        );

        add_submenu_page(
            'upmkt-subscriptions',
            'Configurações',
            'Configurações',
            'manage_upmkt_subscriptions',
            'upmkt-settings',
            [$this, 'render_settings_page']
        );

        // Remove o submenu duplicado
        remove_submenu_page('upmkt-subscriptions', 'upmkt-subscriptions');
    }

    /**
     * Carrega scripts e styles do admin
     */
    public function enqueue_admin_scripts(string $hook): void
    {
        if (strpos($hook, 'upmkt-') === false) {
            return;
        }

        wp_enqueue_style(
            'upmkt-admin-css',
            UPMKT_PLUGIN_URL . 'assets/css/admin.css',
            [],
            UPMKT_VERSION
        );

        wp_enqueue_script(
            'upmkt-admin-js',
            UPMKT_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            UPMKT_VERSION,
            true
        );

        // Localize script para AJAX
        wp_localize_script('upmkt-admin-js', 'upmkt_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('upmkt_admin_nonce'),
            'i18n' => [
                'confirm_cancel' => 'Tem certeza que deseja cancelar esta assinatura?',
                'confirm_delete' => 'Tem certeza que deseja excluir este plano?',
                'processing' => 'Processando...'
            ]
        ]);
    }

    /**
     * Renderiza a página de dashboard
     */
    public function render_dashboard_page(): void
    {
        if (!current_user_can('manage_upmkt_subscriptions')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        $stats = $this->get_dashboard_stats();
        ?>
        <div class="wrap upmkt-admin">
            <h1 class="wp-heading-inline">Dashboard - Assinaturas</h1>
            
            <div class="upmkt-dashboard-stats">
                <div class="upmkt-stat-card">
                    <div class="upmkt-stat-number"><?php echo esc_html($stats['total_subscriptions']); ?></div>
                    <div class="upmkt-stat-label">Total de Assinaturas</div>
                </div>
                
                <div class="upmkt-stat-card">
                    <div class="upmkt-stat-number"><?php echo esc_html($stats['active_subscriptions']); ?></div>
                    <div class="upmkt-stat-label">Assinaturas Ativas</div>
                </div>
                
                <div class="upmkt-stat-card">
                    <div class="upmkt-stat-number"><?php echo esc_html($stats['total_revenue']); ?></div>
                    <div class="upmkt-stat-label">Receita Mensal</div>
                </div>
                
                <div class="upmkt-stat-card">
                    <div class="upmkt-stat-number"><?php echo esc_html($stats['pending_payments']); ?></div>
                    <div class="upmkt-stat-label">Pagamentos Pendentes</div>
                </div>
            </div>
            
            <div class="upmkt-dashboard-content">
                <div class="upmkt-dashboard-column">
                    <div class="upmkt-card">
                        <h3>Assinaturas Recentes</h3>
                        <?php $this->render_recent_subscriptions(); ?>
                    </div>
                </div>
                
                <div class="upmkt-dashboard-column">
                    <div class="upmkt-card">
                        <h3>Status das Assinaturas</h3>
                        <?php $this->render_subscriptions_chart(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza a página de listagem de assinaturas
     */
    public function render_subscriptions_page(): void
    {
        if (!current_user_can('manage_upmkt_subscriptions')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        // VERSAO SIMPLIFICADA - sem table list por enquanto
        ?>
    <div class="wrap upmkt-admin">
        <h1 class="wp-heading-inline">Todas as Assinaturas</h1>
        
        <div class="upmkt-admin-actions">
            <a href="<?php echo admin_url('admin.php?page=upmkt-subscriptions&action=export'); ?>" class="button">
                Exportar CSV
            </a>
        </div>
        
        <div class="upmkt-card">
            <p>Lista de assinaturas será implementada em breve.</p>
            <p>Por enquanto, você pode ver as assinaturas diretamente no banco de dados na tabela <code><?php global $wpdb;
        echo $wpdb->prefix; ?>upmkt_subscriptions</code></p>
        </div>
    </div>
    <?php
    }

    /**
     * Renderiza a página de planos
     */
    public function render_plans_page(): void
    {
        if (!current_user_can('manage_upmkt_subscriptions')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        $action = $_GET['action'] ?? 'list';

        switch ($action) {
            case 'edit':
            case 'add':
                $this->render_plan_edit_page();
                break;
            default:
                $this->render_plans_list_page();
        }
    }

    /**
     * Renderiza a lista de planos
     */
    private function render_plans_list_page(): void
    {
        // VERSAO SIMPLIFICADA - sem table list por enquanto
        ?>
    <div class="wrap upmkt-admin">
        <h1 class="wp-heading-inline">Planos de Assinatura</h1>
        <a href="<?php echo admin_url('admin.php?page=upmkt-subscription-plans&action=add'); ?>" class="page-title-action">
            Adicionar Novo
        </a>
        
        <div class="upmkt-card">
            <p>Lista de planos será implementada em breve.</p>
            <p>Por enquanto, você pode gerenciar os planos diretamente no banco de dados na tabela <code><?php global $wpdb;
        echo $wpdb->prefix; ?>upmkt_subscription_plans</code></p>
        </div>
    </div>
    <?php
    }

    /**
     * Renderiza página de edição de plano
     */
    private function render_plan_edit_page(): void
    {
        $plan_id = $_GET['plan_id'] ?? 0;
        $plan = new \UPMarket\Subscriptions\Entities\SubscriptionPlan($plan_id);

        // TODO: Implementar formulário de edição de plano
        ?>
        <div class="wrap upmkt-admin">
            <h1 class="wp-heading-inline">
                <?php echo $plan->exists() ? 'Editar Plano' : 'Adicionar Novo Plano'; ?>
            </h1>
            
            <div class="upmkt-card">
                <p>Formulário de edição de plano será implementado aqui.</p>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza a página de configurações
     */
    public function render_settings_page(): void
    {
        if (!current_user_can('manage_upmkt_subscriptions')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        // TODO: Implementar configurações
        ?>
        <div class="wrap upmkt-admin">
            <h1>Configurações - Assinaturas</h1>
            
            <div class="upmkt-card">
                <p>Configurações do plugin serão implementadas aqui.</p>
            </div>
        </div>
        <?php
    }

    /**
     * Retorna estatísticas para o dashboard
     */
    private function get_dashboard_stats(): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscriptions';

        return [
            'total_subscriptions' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"),
            'active_subscriptions' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'active'"),
            'total_revenue' => 'R$ 0,00', // TODO: Calcular receita
            'pending_payments' => $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending'")
        ];
    }

    /**
     * Renderiza assinaturas recentes
     */
    private function render_recent_subscriptions(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscriptions';
        $subscriptions = $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 5"
        );

        if (empty($subscriptions)) {
            echo '<p>Nenhuma assinatura encontrada.</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Usuário</th>
                    <th>Status</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subscriptions as $sub): ?>
                    <tr>
                        <td><?php echo esc_html($sub->id); ?></td>
                        <td>
                            <?php
                            $user = get_userdata($sub->user_id);
                    echo $user ? esc_html($user->display_name) : 'Usuário #' . esc_html($sub->user_id);
                    ?>
                        </td>
                        <td>
                            <span class="upmkt-status upmkt-status-<?php echo esc_attr($sub->status); ?>">
                                <?php echo esc_html($this->get_status_text($sub->status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(date('d/m/Y', strtotime($sub->created_at))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Renderiza gráfico de status
     */
    private function render_subscriptions_chart(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscriptions';
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table_name} GROUP BY status"
        );

        if (empty($status_counts)) {
            echo '<p>Nenhum dado disponível.</p>';
            return;
        }
        ?>
        <div class="upmkt-chart-container">
            <?php foreach ($status_counts as $status): ?>
                <div class="upmkt-chart-item">
                    <div class="upmkt-chart-label">
                        <?php echo esc_html($this->get_status_text($status->status)); ?>
                    </div>
                    <div class="upmkt-chart-bar">
                        <div class="upmkt-chart-fill" style="width: <?php echo esc_attr(($status->count / array_sum(array_column($status_counts, 'count'))) * 100); ?>%"></div>
                    </div>
                    <div class="upmkt-chart-count">
                        <?php echo esc_html($status->count); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Retorna texto do status
     */
    private function get_status_text(string $status): string
    {
        $statuses = [
            'active' => 'Ativa',
            'pending' => 'Pendente',
            'cancelled' => 'Cancelada',
            'expired' => 'Expirada',
            'paused' => 'Pausada'
        ];

        return $statuses[$status] ?? $status;
    }
}

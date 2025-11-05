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
        add_action('wp_enqueue_scripts', [$this, 'enqueue_front_scripts']);
        add_action('admin_init', [$this, 'handle_plan_actions']);
        add_action('admin_init', [$this, 'maybe_export_subscriptions_csv']);
    }

    /**
     * Adiciona mensagem de notificação no admin
     */
    private function add_admin_notice(string $message, string $type = 'success'): void
    {
        add_action('admin_notices', function () use ($message, $type) {
            ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p><b><?php echo esc_html($message); ?></b></p>
        </div>
        <?php
        });
    }

    /**
     * Verifica se o usuário atual tem permissão
     */
    private function current_user_can_manage(): bool
    {
        return current_user_can('manage_upmkt_subscriptions');
    }

    /**
     * Valida e sanitiza dados do formulário
     */
    private function sanitize_plan_data(array $data): array
    {
        return [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'icon_class' => sanitize_text_field($data['icon_class'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'price' => floatval($data['price'] ?? 0),
            'billing_period' => sanitize_text_field($data['billing_period'] ?? 'month'),
            'billing_frequency' => intval($data['billing_frequency'] ?? 1),
            'trial_period_days' => intval($data['trial_period_days'] ?? 0),
            'is_active' => isset($data['is_active']),
            'features' => array_filter(array_map('trim', explode("\n", sanitize_textarea_field($data['features'] ?? ''))))
        ];
    }

    /**
     * Manipula ações de planos (exclusão, salvamento, etc)
     */
    public function handle_plan_actions(): void
    {
        // Só processar se estamos na página de planos
        if (!isset($_GET['page']) || $_GET['page'] !== 'upmkt-subscription-plans') {
            return;
        }

        // Processar exclusão
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['plan_id'])) {
            $this->handle_plan_deletion();
        }

        // CORREÇÃO: Processar salvamento do formulário
        if (isset($_POST['submit_plan']) && isset($_POST['upmkt_plan_nonce'])) {
            $this->handle_plan_save();
        }
    }

    /**
     * Hook de execução do export CSV
     */
    public function maybe_export_subscriptions_csv(): void
    {
        if (!current_user_can('manage_upmkt_subscriptions')) {
            return;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'export' && isset($_GET['page']) && $_GET['page'] === 'upmkt-subscriptions-list') {
            $this->export_subscriptions_csv();
        }
    }

    /**
     * Manipula salvamento do plano (agora chamado via admin_init)
     */
    private function handle_plan_save(): void
    {
        if (!wp_verify_nonce($_POST['upmkt_plan_nonce'], 'upmkt_save_plan')) {
            $this->add_admin_notice('Erro de segurança. Nonce inválido.', 'error');
            return;
        }

        if (!current_user_can('manage_upmkt_subscriptions')) {
            $this->add_admin_notice('Sem permissão para salvar planos.', 'error');
            return;
        }

        $plan_id = $_GET['plan_id'] ?? 0;
        $plan = new \UPMarket\Subscriptions\Entities\SubscriptionPlan($plan_id);

        try {
            $name = sanitize_text_field($_POST['name'] ?? '');
            $icon_class = sanitize_text_field($_POST['icon_class'] ?? '');
            $description = sanitize_textarea_field($_POST['description'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $billing_period = sanitize_text_field($_POST['billing_period'] ?? 'month');
            $billing_frequency = intval($_POST['billing_frequency'] ?? 1);
            $trial_period_days = intval($_POST['trial_period_days'] ?? 0);
            $is_active = isset($_POST['is_active']);

            // Validações avançadas
            if (empty($name)) {
                throw new \Exception('O nome do plano é obrigatório.');
            }

            if ($price < 0) {
                throw new \Exception('O preço não pode ser negativo.');
            }

            if ($price == 0) {
                throw new \Exception('O preço deve ser maior que zero.');
            }

            if ($billing_frequency < 1) {
                throw new \Exception('A frequência deve ser pelo menos 1.');
            }

            $allowed_periods = ['day', 'month', 'year'];
            if (!in_array($billing_period, $allowed_periods)) {
                throw new \Exception('Período de cobrança inválido.');
            }

            if ($trial_period_days < 0) {
                throw new \Exception('Os dias de trial não podem ser negativos.');
            }

            // Processar features
            $features_text = sanitize_textarea_field($_POST['features'] ?? '');
            $features = array_filter(array_map('trim', explode("\n", $features_text)));

            $is_new = !$plan->exists();

            // Se é um novo plano, criar
            if ($is_new) {
                $new_plan = \UPMarket\Subscriptions\Entities\SubscriptionPlan::create(
                    $name,
                    $price,
                    $billing_period,
                    [
                        'icon_class' => $icon_class,
                        'description' => $description,
                        'billing_frequency' => $billing_frequency,
                        'trial_period_days' => $trial_period_days,
                        'is_active' => $is_active,
                        'features' => $features
                    ]
                );

                if ($new_plan) {
                    $message = 'created';
                } else {
                    throw new \Exception('Erro ao criar plano.');
                }
            } else {
                // Atualizar plano existente
                $plan_data = [
                    'name' => $name,
                    'icon_class' => $icon_class,
                    'description' => $description,
                    'price' => $price,
                    'billing_period' => $billing_period,
                    'billing_frequency' => $billing_frequency,
                    'trial_period_days' => $trial_period_days,
                    'is_active' => $is_active,
                    'features' => $features
                ];

                $result = $this->update_plan_entity($plan, $plan_data);

                if ($result) {
                    $message = 'updated';
                } else {
                    throw new \Exception('Erro ao atualizar plano.');
                }
            }

            // Redirecionar com segurança
            $redirect_url = admin_url('admin.php?page=upmkt-subscription-plans');
            $redirect_url = add_query_arg('message', $message, $redirect_url);

            wp_safe_redirect(esc_url_raw($redirect_url));
            exit;

        } catch (\Exception $e) {
            $this->add_admin_notice('Erro ao salvar plano: ' . $e->getMessage(), 'error');
        }
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
     * Carrega scripts e styles do front-end
     */
    public function enqueue_front_scripts(): void
    {
        wp_enqueue_style(
            'upmkt-front-css',
            UPMKT_PLUGIN_URL . 'assets/css/front.css',
            [],
            UPMKT_VERSION
        );

        wp_enqueue_script(
            'upmkt-front-js',
            UPMKT_PLUGIN_URL . 'assets/js/front.js',
            ['jquery'],
            UPMKT_VERSION,
            true
        );

        wp_localize_script('upmkt-front-js', 'upmkt_front', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('upmkt_front_nonce'),
            'i18n' => [
                'processing' => 'Processando...',
                'error' => 'Ocorreu um erro, tente novamente.',
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
        $action = $_GET['action'] ?? 'list';

        if ($action === 'export') {
            $this->export_subscriptions_csv();
            exit;
        }

        if (!current_user_can('manage_upmkt_subscriptions')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        $action = $_GET['action'] ?? 'list';
        $subscription_id = $_GET['subscription_id'] ?? 0;

        // Página de edição
        if ($action === 'edit' && $subscription_id) {
            $subscription_edit = new \UPMarket\Subscriptions\Admin\SubscriptionEdit();
            $subscription_edit->render_edit_page($subscription_id);
            return;
        }

        // Lista de assinaturas
        $subscriptions_table = new \UPMarket\Subscriptions\Admin\SubscriptionsListTable();
        $subscriptions_table->prepare_items();

        // Parâmetros extras (hidden inputs)
        $hidden_inputs = [
            'orderby' => $_GET['orderby'] ?? '',
            'order' => $_GET['order'] ?? '',
            'status' => $_GET['status'] ?? '',
            's' => $_GET['s'] ?? '',
            'page' => 'upmkt-subscriptions-list'
        ];
        ?>
    	<div class="wrap upmkt-admin">
        <h1 class="wp-heading-inline">Todas as Assinaturas</h1>

        <?php $this->render_subscriptions_admin_notices(); ?>

        <div class="upmkt-admin-actions">
						<a href="<?php echo esc_url(admin_url('admin.php?page=upmkt-subscriptions-list&action=export')); ?>" class="button">
								Exportar CSV
						</a>
        </div>

        <!-- O FORM é obrigatório para o WP_List_Table funcionar -->
        <form method="post">
            <?php
                // Hidden inputs extras
                foreach ($hidden_inputs as $name => $value) {
                    if ($value !== '') {
                        printf(
                            '<input type="hidden" name="%s" value="%s">',
                            esc_attr($name),
                            esc_attr($value)
                        );
                    }
                }

        $subscriptions_table->search_box('Buscar assinaturas', 'search');
        $subscriptions_table->display();
        ?>
        </form>
    	</div>
    	<?php
    }


    /**
     * Cria o arquivo CSV com os dados
     */
    private function export_subscriptions_csv(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscriptions';
        $users_table = $wpdb->users;

        $results = $wpdb->get_results("
        SELECT s.*, u.user_email, u.display_name
        FROM {$table_name} s
        LEFT JOIN {$users_table} u ON s.user_id = u.ID
        ORDER BY s.id ASC
    		", ARRAY_A);

        if (empty($results)) {
            wp_die('Nenhuma assinatura encontrada para exportar.');
        }

        $filename = upmkt_generate_csv_filename();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, array_keys($results[0]));

        foreach ($results as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
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
     * Manipula exclusão de plano com notificações melhoradas
     */
    private function handle_plan_deletion(): void
    {
        $plan_id = $_GET['plan_id'] ?? 0;
        $nonce = $_GET['_wpnonce'] ?? '';

        if (!$plan_id || !wp_verify_nonce($nonce, 'delete_plan_' . $plan_id)) {
            $this->add_admin_notice('Erro de segurança.', 'error');
            return;
        }

        if (!current_user_can('manage_upmkt_subscriptions')) {
            $this->add_admin_notice('Sem permissão para excluir planos.', 'error');
            return;
        }

        global $wpdb;

        // Verificar se há assinaturas usando este plano
        $subscriptions_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}upmkt_subscriptions WHERE plan_id = %d",
                $plan_id
            )
        );

        if ($subscriptions_count > 0) {
            $this->add_admin_notice('Não é possível excluir este plano pois existem assinaturas ativas vinculadas a ele.', 'error');
            return;
        }

        // Excluir plano
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'upmkt_subscription_plans',
            ['id' => $plan_id],
            ['%d']
        );

        if ($deleted) {
            wp_safe_redirect(esc_url_raw(admin_url('admin.php?page=upmkt-subscription-plans&message=deleted')));
            exit;
        } else {
            $this->add_admin_notice('Erro ao excluir plano.', 'error');
        }
    }

    /**
     * Renderiza a lista de planos
     */
    private function render_plans_list_page(): void
    {
        // Mostrar mensagens de feedback
        if (isset($_GET['message'])) {
            switch ($_GET['message']) {
                case 'created':
                    echo '<div class="notice notice-success"><p>Plano criado com sucesso!</p></div>';
                    break;
                case 'updated':
                    echo '<div class="notice notice-success"><p>Plano atualizado com sucesso!</p></div>';
                    break;
                case 'deleted':
                    echo '<div class="notice notice-success"><p>Plano excluído com sucesso!</p></div>';
                    break;
            }
        }

        $plans_table = new PlansListTable();
        $plans_table->prepare_items();
        ?>
					<div class="wrap upmkt-admin">
							<h1 class="wp-heading-inline">Planos de Assinatura</h1>
							<a href="<?php echo admin_url('admin.php?page=upmkt-subscription-plans&action=add'); ?>" class="page-title-action">
									Adicionar Novo
							</a>
							
							<form method="get">
									<input type="hidden" name="page" value="upmkt-subscription-plans">
									<?php $plans_table->display(); ?>
							</form>
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

        $is_editing = $plan->exists();

        ?>
    	<div class="wrap upmkt-admin">
        <h1 class="wp-heading-inline">
            <?php echo $is_editing ? 'Editar Plano' : 'Adicionar Novo Plano'; ?>
        </h1>
        
        <a href="<?php echo admin_url('admin.php?page=upmkt-subscription-plans'); ?>" class="page-title-action">
            ← Voltar para Lista
        </a>
               
        <div class="upmkt-card">
            <form method="post">
                <?php wp_nonce_field('upmkt_save_plan', 'upmkt_plan_nonce'); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="plan_name">Nome do Plano</label></th>
                            <td>
                                <input type="text" 
                                       id="plan_name" 
                                       name="name" 
                                       value="<?php echo esc_attr($plan->get_name()); ?>" 
                                       class="regular-text" 
                                       required>
                                <p class="description">Nome exibido para os clientes.</p>
                            </td>
                        </tr>

												<tr>
														<th scope="row"><label for="plan_icon_class">Ícone (classe CSS)</label></th>
														<td>
																<input type="text"
																			id="plan_icon_class"
																			name="icon_class"
																			value="<?php echo esc_attr($plan->get_icon_class()); ?>"
																			class="regular-text">
																<p class="description">Classe CSS personalizada para exibição no front-end.</p>
														</td>
												</tr>

                        <tr>
                            <th scope="row"><label for="plan_description">Descrição</label></th>
                            <td>
                                <textarea id="plan_description" 
                                          name="description" 
                                          class="large-text" 
                                          rows="3"><?php echo esc_textarea($plan->get_description()); ?></textarea>
                                <p class="description">Descrição detalhada do plano.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="plan_price">Preço (R$)</label></th>
                            <td>
                                <input type="number" 
                                       id="plan_price" 
                                       name="price" 
                                       value="<?php echo esc_attr($plan->get_price()); ?>" 
                                       step="0.01" 
                                       min="0" 
                                       class="small-text" 
                                       required>
                                <p class="description">Valor da assinatura em reais.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="plan_billing_period">Período de Cobrança</label></th>
                            <td>
                                <select id="plan_billing_period" name="billing_period" required>
                                    <option value="day" <?php selected($plan->get_billing_period(), 'day'); ?>>Diário</option>
                                    <option value="month" <?php selected($plan->get_billing_period(), 'month'); ?>>Mensal</option>
                                    <option value="year" <?php selected($plan->get_billing_period(), 'year'); ?>>Anual</option>
                                </select>
                                <p class="description">Frequência da cobrança recorrente.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="plan_billing_frequency">Frequência</label></th>
                            <td>
                                <input type="number" 
                                       id="plan_billing_frequency" 
                                       name="billing_frequency" 
                                       value="<?php echo esc_attr($plan->get_billing_frequency()); ?>" 
                                       min="1" 
                                       class="small-text" 
                                       required>
                                <p class="description">Ex: 1 para mensal, 3 para trimestral.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="plan_trial_period_days">Dias de Trial</label></th>
                            <td>
                                <input type="number" 
                                       id="plan_trial_period_days" 
                                       name="trial_period_days" 
                                       value="<?php echo esc_attr($plan->get_trial_period_days()); ?>" 
                                       min="0" 
                                       class="small-text">
                                <p class="description">Número de dias gratuitos (0 para nenhum trial).</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="plan_features">Recursos</label></th>
                            <td>
                                <textarea id="plan_features" 
                                          name="features" 
                                          class="large-text" 
                                          rows="5"
                                          placeholder="Cada recurso em uma linha"><?php
                                        $features = $plan->get_features();
        if (!empty($features)) {
            echo esc_textarea(implode("\n", $features));
        }
        ?></textarea>
                                <p class="description">Lista de recursos do plano (um por linha).</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Status</th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="is_active" 
                                           value="1" 
                                           <?php checked($plan->is_active()); ?>>
                                    Plano ativo
                                </label>
                                <p class="description">Planos inativos não estarão disponíveis para novos assinantes.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" 
                           name="submit_plan" 
                           class="button button-primary" 
                           value="<?php echo $is_editing ? 'Atualizar Plano' : 'Criar Plano'; ?>">
                    
                    <?php if ($is_editing): ?>
											<a href="<?php echo wp_nonce_url(
											    admin_url('admin.php?page=upmkt-subscription-plans&action=delete&plan_id=' . $plan->get_id()),
											    'delete_plan_' . $plan->get_id()
											); ?>" 
												class="button button-link-delete" 
												style="color:#a00;"
												onclick="return confirm('Tem certeza que deseja excluir este plano? Esta ação não pode ser desfeita.')">
													Excluir Plano
											</a>
                    <?php endif; ?>
                </p>
            </form>
        </div>
			</div>
			<?php
    }

    /**
     * Atualiza entidade do plano usando os métodos setters
     */
    private function update_plan_entity(\UPMarket\Subscriptions\Entities\SubscriptionPlan $plan, array $data): bool
    {
        // AGORA USANDO O MÉTODO UPDATE CORRETO
        return $plan->update($data);
    }

    /**
     * Renderiza a página de configurações
     */
    public function render_settings_page(): void
    {
        if (!current_user_can('manage_upmkt_subscriptions')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        // Processar salvamento das configurações
        if (isset($_POST['submit_upmkt_settings']) && isset($_POST['upmkt_settings_nonce'])) {
            $this->handle_settings_save();
        }

        $active_tab = $_GET['tab'] ?? 'general';
        $current_checkout_page_id = get_option('upmkt_checkout_page_id');
        ?>
					<div class="wrap upmkt-admin">
							<h1>Configurações - Assinaturas</h1>
							
							<nav class="nav-tab-wrapper">
									<a href="#general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
											Geral
									</a>
									<?php do_action('upmkt_admin_settings_tabs'); ?>
							</nav>
							
							<div class="upmkt-settings-content">
									<?php if ($active_tab === 'general'): ?>
											<div id="general" class="tab-content active">
													<div class="upmkt-card">
															<h2>Configurações Gerais</h2>
															
															<form method="post">
																	<?php wp_nonce_field('upmkt_save_settings', 'upmkt_settings_nonce'); ?>
																	
																	<table class="form-table">
																			<tbody>
																					<tr>
																							<th scope="row">
																									<label for="upmkt_checkout_page_id">Página de Checkout</label>
																							</th>
																							<td>
																									<select name="upmkt_checkout_page_id" id="upmkt_checkout_page_id">
																											<option value="">-- Selecione uma página --</option>
																											<?php
                                                                                                                    $pages = get_pages();
									    foreach ($pages as $page) {
									        $selected = selected($current_checkout_page_id, $page->ID, false);
									        echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>';
									        echo esc_html($page->post_title);
									        echo '</option>';
									    }
									    ?>
																									</select>
																									<p class="description">
																											<small>Selecione a página onde o shortcode <code>[upmkt_checkout]</code> foi inserido.</small>
																											<?php if ($current_checkout_page_id && get_post_status($current_checkout_page_id) === 'publish'): ?>
																											<?php endif; ?>
																									</p>
																							</td>
																					</tr>
																			</tbody>
																	</table>
																	
																	<p class="submit">
																			<input type="submit" name="submit_upmkt_settings" class="button button-primary" value="Salvar Configurações">
																	</p>
															</form>
													</div>
											</div>
									<?php endif; ?>
									
									<?php do_action('upmkt_admin_settings_content'); ?>
							</div>
					</div>
					
					<style>
					.nav-tab-wrapper {
							margin-bottom: 20px;
					}
					
					.tab-content {
							display: none;
					}
					
					.tab-content.active {
							display: block;
					}
					
					.upmkt-settings-content .form-table {
							margin-top: 0;
					}
					
					.upmkt-settings-content .form-table th {
							width: 200px;
					}
					
					#upmkt_checkout_page_id {
							width: auto;
					}
					</style>
					
					<script>
					jQuery(document).ready(function($) {
							// Tab navigation
							$('.nav-tab').on('click', function(e) {
									e.preventDefault();
									var target = $(this).attr('href');
									
									$('.nav-tab').removeClass('nav-tab-active');
									$('.tab-content').removeClass('active');
									
									$(this).addClass('nav-tab-active');
									$(target).addClass('active');
							});
					});
					</script>
				<?php
    }

    /**
     * Manipula o salvamento das configurações
     */
    private function handle_settings_save(): void
    {
        if (!wp_verify_nonce($_POST['upmkt_settings_nonce'], 'upmkt_save_settings')) {
            $this->add_admin_notice('Erro de segurança. Nonce inválido.', 'error');
            return;
        }

        if (!current_user_can('manage_upmkt_subscriptions')) {
            $this->add_admin_notice('Sem permissão para salvar configurações.', 'error');
            return;
        }

        try {
            // Salvar página de checkout
            $checkout_page_id = intval($_POST['upmkt_checkout_page_id'] ?? 0);

            if ($checkout_page_id > 0) {
                update_option('upmkt_checkout_page_id', $checkout_page_id);
                $this->add_admin_notice('Configurações salvas com sucesso!', 'success');
            } else {
                delete_option('upmkt_checkout_page_id');
                $this->add_admin_notice('Página de checkout removida.', 'success');
            }

        } catch (\Exception $e) {
            $this->add_admin_notice('Erro ao salvar configurações: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Retorna estatísticas otimizadas para o dashboard
     */
    private function get_dashboard_stats(): array
    {
        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'upmkt_subscriptions';
        $plans_table = $wpdb->prefix . 'upmkt_subscription_plans';

        // Consulta única otimizada para todas as estatísticas
        $stats = $wpdb->get_row("
        SELECT 
            COUNT(*) as total_subscriptions,
            COUNT(CASE WHEN s.status = 'active' THEN 1 END) as active_subscriptions,
            COUNT(CASE WHEN s.status = 'pending' THEN 1 END) as pending_payments,
            COALESCE(SUM(CASE WHEN s.status = 'active' THEN p.price ELSE 0 END), 0) as monthly_revenue
        FROM {$subscriptions_table} s
        LEFT JOIN {$plans_table} p ON s.plan_id = p.id
    ");

        return [
            'total_subscriptions' => $stats->total_subscriptions ?? 0,
            'active_subscriptions' => $stats->active_subscriptions ?? 0,
            'total_revenue' => 'R$ ' . number_format($stats->monthly_revenue ?? 0, 2, ',', '.'),
            'pending_payments' => $stats->pending_payments ?? 0
        ];
    }

    /**
     * Calcula receita mensal real
     */
    private function calculate_monthly_revenue(): float
    {
        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'upmkt_subscriptions';
        $plans_table = $wpdb->prefix . 'upmkt_subscription_plans';

        $revenue = $wpdb->get_var("
        SELECT SUM(p.price) 
        FROM {$subscriptions_table} s 
        INNER JOIN {$plans_table} p ON s.plan_id = p.id 
        WHERE s.status = 'active'
    ");

        return floatval($revenue ?? 0);
    }

    /**
     * Renderiza assinaturas recentes com dados otimizados
     */
    private function render_recent_subscriptions(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscriptions';

        // Consulta otimizada com JOIN para dados do usuário
        $subscriptions = $wpdb->get_results("
        SELECT s.*, u.display_name, u.user_email 
        FROM {$table_name} s 
        LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID 
        ORDER BY s.created_at DESC 
        LIMIT 5
    ");

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
                <th>Plano</th>
                <th>Status</th>
                <th>Data</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($subscriptions as $sub):
                $plan_name = $this->get_plan_name($sub->plan_id);
                ?>
                <tr>
                    <td><?php echo esc_html($sub->id); ?></td>
                    <td>
                        <?php if ($sub->display_name): ?>
                            <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $sub->user_id)); ?>">
                                <?php echo esc_html($sub->display_name); ?>
                            </a>
                            <br>
                            <small><?php echo esc_html($sub->user_email); ?></small>
                        <?php else: ?>
                            Usuário #<?php echo esc_html($sub->user_id); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($plan_name); ?></td>
                    <td>
                        <span class="upmkt-status upmkt-status-<?php echo esc_attr($sub->status); ?>">
                            <?php echo esc_html($this->get_status_text($sub->status)); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html(date('d/m/Y H:i', strtotime($sub->created_at))); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    }

    /**
     * Obtém nome do plano por ID
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
     * Renderiza gráfico de status com dados otimizados
     */
    private function render_subscriptions_chart(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscriptions';

        // Consulta otimizada para status
        $status_counts = $wpdb->get_results("
        SELECT status, COUNT(*) as count 
        FROM {$table_name} 
        GROUP BY status 
        ORDER BY count DESC
    ");

        if (empty($status_counts)) {
            echo '<p>Nenhum dado disponível.</p>';
            return;
        }

        $total = array_sum(array_column($status_counts, 'count'));
        ?>
					<div class="upmkt-chart-container">
							<?php foreach ($status_counts as $status):
							    $percentage = $total > 0 ? ($status->count / $total) * 100 : 0;
							    $status_text = $this->get_status_text($status->status);
							    ?>
									<div class="upmkt-chart-item">
											<div class="upmkt-chart-label">
													<?php echo esc_html($status_text); ?>
											</div>
											<div class="upmkt-chart-bar">
													<div class="upmkt-chart-fill" 
															style="width: <?php echo esc_attr($percentage); ?>%"
															title="<?php echo esc_attr($status->count . ' ' . $status_text); ?>">
													</div>
											</div>
											<div class="upmkt-chart-count">
													<?php echo esc_html($status->count); ?> 
													(<?php echo esc_html(number_format($percentage, 1)); ?>%)
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
            'paused' => 'Pausada',
            'pending' => 'Pendente',
            'cancelled' => 'Cancelada',
            'expired' => 'Expirada'
        ];

        return $statuses[$status] ?? $status;
    }
}

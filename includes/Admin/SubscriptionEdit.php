<?php

namespace UPMarket\Subscriptions\Admin;

use UPMarket\Subscriptions\Entities\Subscription;
use UPMarket\Subscriptions\Entities\SubscriptionPlan;

/**
 * Gerencia a edição de assinaturas
 *
 * @package UPMarket\Subscriptions\Admin
 */
class SubscriptionEdit
{
    /**
     * Inicializa hooks
     */
    public function init(): void
    {
        add_action('admin_init', [$this, 'handle_subscription_actions']);
    }

    /**
     * Manipula ações de assinatura
     */
    public function handle_subscription_actions(): void
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'upmkt-subscriptions-list') {
            return;
        }

        $action = $_GET['action'] ?? '';
        $subscription_id = $_GET['subscription_id'] ?? 0;

        if (!$action || !$subscription_id) {
            return;
        }

        // Verificar nonce para ações destrutivas
        if (in_array($action, ['cancel', 'delete'])) {
            $nonce = $_GET['_wpnonce'] ?? '';
            if (!wp_verify_nonce($nonce, $action . '_subscription_' . $subscription_id)) {
                wp_die('Erro de segurança.');
            }
        }

        $subscription = new Subscription($subscription_id);

        if (!$subscription->exists()) {
            wp_die('Assinatura não encontrada.');
        }

        switch ($action) {
            case 'edit':
                // A página de edição será renderizada
                break;

            case 'cancel':
                $this->handle_cancel_subscription($subscription);
                break;

            case 'resume':
                $this->handle_resume_subscription($subscription);
                break;

            case 'delete':
                $this->handle_delete_subscription($subscription);
                break;

            case 'update':
                $this->handle_update_subscription($subscription);
                break;
        }
    }

    public function render_edit_page(int $subscription_id): void
    {
        if (!current_user_can('manage_upmkt_subscriptions')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        $subscription = new Subscription($subscription_id);

        if (!$subscription->exists()) {
            wp_die('Assinatura não encontrada.');
        }


        $user_data = $this->get_user_display_data($subscription->get_user_id());
        $plan = new SubscriptionPlan($subscription->get_plan_id());
        $all_plans = $this->get_available_plans();

        ?>
					<div class="wrap upmkt-admin">
							<h1 class="wp-heading-inline">Editar Assinatura #<?php echo esc_html($subscription->get_id()); ?></h1>
							<a href="<?php echo esc_url(admin_url('admin.php?page=upmkt-subscriptions-list')); ?>" class="page-title-action">
									← Voltar para Lista
							</a>

							<hr class="wp-header-end">

							<?php $this->render_admin_notices(); ?>

							<div class="upmkt-edit-container">
									<div class="upmkt-edit-main">
											<form method="post" action="<?php echo esc_url(admin_url('admin.php?page=upmkt-subscriptions-list&action=update&subscription_id=' . $subscription->get_id())); ?>">
													<?php wp_nonce_field('update_subscription_' . $subscription->get_id(), 'upmkt_subscription_nonce'); ?>
													
													<div class="upmkt-card">
															<h2>Informações Básicas</h2>
															
															<table class="form-table">
																	<tbody>
																			<tr>
																					<th scope="row">
																							<label>Usuário</label>
																					</th>
																					<td>
																							<strong>
																									<?php if ($user_data['exists']): ?>
																											<a href="<?php echo esc_url($user_data['user_link']); ?>">
																													<?php echo esc_html($user_data['display_name']); ?>
																											</a>
																									<?php else: ?>
																											<?php echo esc_html($user_data['display_name']); ?>
																									<?php endif; ?>
																									(<?php echo esc_html($user_data['user_email']); ?>)
																							</strong>
																							<p class="description">Usuário #<?php echo esc_html($subscription->get_user_id()); ?></p>
																							<?php if (!$user_data['exists']): ?>
																									<p class="description" style="color: #dd3a49;">
																											⚠️ Este usuário não existe mais no sistema.
																									</p>
																							<?php endif; ?>
																					</td>
																			</tr>
																			<tr>
																					<th scope="row"><label for="subscription_plan">Plano</label></th>
																					<td>
																							<select name="plan_id" id="subscription_plan" class="regular-text">
																									<?php foreach ($all_plans as $plan_option): ?>
																											<option value="<?php echo esc_attr($plan_option->get_id()); ?>" 
																													<?php selected($subscription->get_plan_id(), $plan_option->get_id()); ?>>
																													<?php echo esc_html($plan_option->get_name()); ?> 
																													- R$ <?php echo esc_html(number_format($plan_option->get_price(), 2, ',', '.')); ?>
																											</option>
																									<?php endforeach; ?>
																							</select>
																							<p class="description">Alterar o plano atualiza o valor e características da assinatura.</p>
																					</td>
																			</tr>
																			
																			<tr>
																					<th scope="row"><label for="subscription_status">Status</label></th>
																					<td>
																							<select name="status" id="subscription_status" class="regular-text">
																									<option value="active" <?php selected($subscription->get_status(), 'active'); ?>>Ativa</option>
																									<option value="cancelled" <?php selected($subscription->get_status(), 'cancelled'); ?>>Cancelada</option>
																									<option value="expired" <?php selected($subscription->get_status(), 'expired'); ?>>Expirada</option>
																									<option value="pending" <?php selected($subscription->get_status(), 'pending'); ?>>Pendente</option>
																							</select>
																					</td>
																			</tr>
																	</tbody>
															</table>
													</div>

													<div class="upmkt-card">
															<h2>Datas e Ciclo</h2>
															
															<table class="form-table">
																	<tbody>
																			<tr>
																					<th scope="row"><label for="start_date">Data de Início</label></th>
																					<td>
																							<input type="datetime-local" 
																										id="start_date" 
																										name="start_date" 
																										value="<?php echo esc_attr($subscription->get_start_date()->format('Y-m-d\TH:i')); ?>" 
																										class="regular-text">
																					</td>
																			</tr>
																			
																			<tr>
																					<th scope="row"><label for="next_billing_date">Próxima Cobrança</label></th>
																					<td>
																							<input type="datetime-local" 
																										id="next_billing_date" 
																										name="next_billing_date" 
																										value="<?php echo esc_attr($subscription->get_next_billing_date()->format('Y-m-d\TH:i')); ?>" 
																										class="regular-text">
																							<p class="description">Data da próxima tentativa de cobrança recorrente.</p>
																					</td>
																			</tr>
																			
																			<tr>
																					<th scope="row"><label for="trial_end_date">Fim do Período de Trial</label></th>
																					<td>
																							<?php
                                                                                                    $trial_end = $subscription->get_meta('trial_end_date');
        $trial_value = $trial_end ? date('Y-m-d\TH:i', strtotime($trial_end)) : '';
        ?>
																							<input type="datetime-local" 
																										id="trial_end_date" 
																										name="trial_end_date" 
																										value="<?php echo esc_attr($trial_value); ?>" 
																										class="regular-text">
																							<p class="description">Deixe em branco para não usar trial.</p>
																					</td>
																			</tr>
																	</tbody>
															</table>
													</div>

													<div class="upmkt-card">
															<h2>Metadados Avançados</h2>
															
															<table class="form-table">
																	<tbody>
																			<tr>
																					<th scope="row"><label for="gateway_subscription_id">ID no Gateway</label></th>
																					<td>
																							<input type="text" 
																										id="gateway_subscription_id" 
																										name="gateway_subscription_id" 
																										value="<?php echo esc_attr($subscription->get_meta('gateway_subscription_id')); ?>" 
																										class="regular-text">
																							<p class="description">ID da assinatura no gateway de pagamento.</p>
																					</td>
																			</tr>
																			
																			<tr>
																					<th scope="row"><label for="gateway_customer_id">ID do Cliente no Gateway</label></th>
																					<td>
																							<input type="text" 
																										id="gateway_customer_id" 
																										name="gateway_customer_id" 
																										value="<?php echo esc_attr($subscription->get_meta('gateway_customer_id')); ?>" 
																										class="regular-text">
																					</td>
																			</tr>
																			
																			<tr>
																					<th scope="row"><label for="notes">Notas Internas</label></th>
																					<td>
																							<textarea id="notes" 
																												name="notes" 
																												class="large-text" 
																												rows="3"><?php echo esc_textarea($subscription->get_meta('admin_notes') ?? ''); ?></textarea>
																							<p class="description">Notas visíveis apenas para administradores.</p>
																					</td>
																			</tr>
																	</tbody>
															</table>
													</div>

													<div class="upmkt-actions">
															<?php submit_button('Atualizar Assinatura', 'primary', 'update_subscription'); ?>
															
															<div class="upmkt-danger-actions">
																	<?php if ($subscription->get_status() !== 'cancelled'): ?>
																			<a href="<?php echo esc_url(wp_nonce_url(
																			    admin_url('admin.php?page=upmkt-subscriptions-list&action=cancel&subscription_id=' . $subscription->get_id()),
																			    'cancel_subscription_' . $subscription->get_id()
																			)); ?>" 
																				class="button button-link-delete" 
																				onclick="return confirm('Tem certeza que deseja cancelar esta assinatura?')">
																					Cancelar Assinatura
																			</a>
																	<?php endif; ?>

																	<a href="<?php echo esc_url(wp_nonce_url(
																	    admin_url('admin.php?page=upmkt-subscriptions-list&action=delete&subscription_id=' . $subscription->get_id()),
																	    'delete_subscription_' . $subscription->get_id()
																	)); ?>" 
																		class="button button-link-delete" 
																		style="color:#a00;"
																		onclick="return confirm('Tem certeza que deseja EXCLUIR permanentemente esta assinatura? Esta ação não pode ser desfeita.')">
																			Excluir Permanentemente
																	</a>
															</div>
													</div>
											</form>
									</div>

									<div class="upmkt-edit-sidebar">
											<?php $this->render_subscription_summary($subscription); ?>
											<?php $this->render_quick_actions($subscription); ?>
											<?php $this->render_recent_activity($subscription); ?>
									</div>
							</div>
					</div>
				<?php
    }

    /**
     * Obtém planos disponíveis
     */
    private function get_available_plans(): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscription_plans';

        // Buscar todos os planos ativos
        $plans_data = $wpdb->get_results("
        SELECT * 
        FROM {$table_name} 
        WHERE is_active = 1 
        ORDER BY name
    ");

        $plans = [];

        if (empty($plans_data)) {
            error_log("UP Market: Nenhum plano ativo encontrado na tabela {$table_name}");
            return $plans;
        }

        foreach ($plans_data as $plan_data) {
            try {
                // Criar instância do plano com o ID
                $plan = new SubscriptionPlan($plan_data->id);

                // Verificar se o plano foi carregado corretamente
                if ($plan->exists()) {
                    $plans[] = $plan;
                } else {
                    error_log("UP Market: Plano com ID {$plan_data->id} não pôde ser carregado");
                }

            } catch (\Exception $e) {
                error_log("UP Market: Erro ao carregar plano {$plan_data->id}: " . $e->getMessage());
            }
        }

        error_log("UP Market: Total de planos carregados: " . count($plans));

        return $plans;
    }

    /**
     * Renderiza resumo da assinatura
     */
    private function render_subscription_summary(Subscription $subscription): void
    {
        $user_data = $this->get_user_display_data($subscription->get_user_id());
        $plan = new SubscriptionPlan($subscription->get_plan_id());
        ?>
					<div class="upmkt-card">
							<h3>Resumo da Assinatura</h3>
							
							<div class="upmkt-summary-item">
									<strong>Usuário:</strong><br>
									<?php if ($user_data['exists']): ?>
											<a href="<?php echo esc_url($user_data['user_link']); ?>">
													<?php echo esc_html($user_data['display_name']); ?>
											</a><br>
											<small><?php echo esc_html($user_data['user_email']); ?></small>
									<?php else: ?>
											<span style="color: #dd3a49;">
													<?php echo esc_html($user_data['display_name']); ?>
											</span><br>
											<small>ID: <?php echo esc_html($subscription->get_user_id()); ?></small>
									<?php endif; ?>
							</div>
							
							<div class="upmkt-summary-item">
									<strong>Plano:</strong><br>
									<?php
                                            $plan_name = $plan->exists() ? $plan->get_name() : 'Plano não encontrado';
        $plan_price = $plan->exists() ? number_format($plan->get_price(), 2, ',', '.') : '0,00';
        ?>
									<?php echo esc_html($plan_name); ?><br>
									<small>R$ <?php echo esc_html($plan_price); ?></small>
							</div>
							
							<div class="upmkt-summary-item">
									<strong>Status:</strong><br>
									<span class="upmkt-status upmkt-status-<?php echo esc_attr($subscription->get_status()); ?>">
											<?php echo esc_html($this->get_status_text($subscription->get_status())); ?>
									</span>
							</div>
							
							<div class="upmkt-summary-item">
									<strong>Próxima cobrança:</strong><br>
									<?php echo esc_html($subscription->get_next_billing_date()->format('d/m/Y H:i')); ?>
							</div>
							
							<?php if ($subscription->get_meta('gateway_subscription_id')): ?>
							<div class="upmkt-summary-item">
									<strong>ID no Gateway:</strong><br>
									<code><?php echo esc_html($subscription->get_meta('gateway_subscription_id')); ?></code>
							</div>
							<?php endif; ?>
					</div>
				<?php
    }

    /**
     * Renderiza ações rápidas
     */
    private function render_quick_actions(Subscription $subscription): void
    {
        ?>
        <div class="upmkt-card">
            <h3>Ações Rápidas</h3>
            
            <div class="upmkt-quick-actions">
                <?php if ($subscription->get_status() !== 'cancelled'): ?>
                    <a href="<?php echo esc_url(wp_nonce_url(
                        admin_url('admin.php?page=upmkt-subscriptions-list&action=cancel&subscription_id=' . $subscription->get_id()),
                        'cancel_subscription_' . $subscription->get_id()
                    )); ?>" 
                       class="button" style="width:100%; margin-bottom:5px; color:#dd3a49; background:#ffb3b3; border-color:#dd3a49;"
                       onclick="return confirm('Cancelar esta assinatura?')">
                       🚫 Cancelar
                    </a>
                <?php endif; ?>

                <a href="<?php echo esc_url(admin_url('admin.php?page=upmkt-subscriptions-list&action=refresh&subscription_id=' . $subscription->get_id())); ?>" 
                   class="button button-secondary" style="width:100%; margin-bottom:5px;">
                   🔄 Sincronizar com Gateway
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Renderiza atividade recente
     */
    private function render_recent_activity(Subscription $subscription): void
    {
        $activity = $this->get_subscription_activity($subscription->get_id());
        ?>
        <div class="upmkt-card">
            <h3>Atividade Recente</h3>
            
            <?php if (empty($activity)): ?>
                <p>Nenhuma atividade registrada.</p>
            <?php else: ?>
                <div class="upmkt-activity-list">
                    <?php foreach (array_slice($activity, 0, 5) as $event): ?>
                        <div class="upmkt-activity-item">
                            <div class="upmkt-activity-desc">
                                <?php echo esc_html($event['description']); ?>
                            </div>
                            <div class="upmkt-activity-date">
                                <?php echo esc_html(date('d/m/Y H:i', strtotime($event['created_at']))); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($activity) > 5): ?>
                    <a href="#" class="button button-small" style="width:100%; margin-top:10px;">
                        Ver todo histórico
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Obtém atividade da assinatura
     */
    private function get_subscription_activity(int $subscription_id): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscription_logs';

        // Verificar se a tabela de logs existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

        if (!$table_exists) {
            return [];
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE subscription_id = %d ORDER BY created_at DESC LIMIT 10",
                $subscription_id
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Manipula atualização da assinatura
     */
    private function handle_update_subscription(Subscription $subscription): void
    {
        if (!wp_verify_nonce($_POST['upmkt_subscription_nonce'] ?? '', 'update_subscription_' . $subscription->get_id())) {
            wp_die('Erro de segurança.');
        }

        try {
            // Atualizar dados básicos
            $subscription->set_plan_id(intval($_POST['plan_id'] ?? $subscription->get_plan_id()));
            $subscription->set_status(sanitize_text_field($_POST['status'] ?? $subscription->get_status()));

            // Atualizar datas
            if (!empty($_POST['start_date'])) {
                $subscription->set_start_date(new \DateTime(sanitize_text_field($_POST['start_date'])));
            }

            if (!empty($_POST['next_billing_date'])) {
                $subscription->set_next_billing_date(new \DateTime(sanitize_text_field($_POST['next_billing_date'])));
            }

            // Atualizar metadados
            $subscription->set_meta('trial_end_date', sanitize_text_field($_POST['trial_end_date'] ?? ''));
            $subscription->set_meta('gateway_subscription_id', sanitize_text_field($_POST['gateway_subscription_id'] ?? ''));
            $subscription->set_meta('gateway_customer_id', sanitize_text_field($_POST['gateway_customer_id'] ?? ''));
            $subscription->set_meta('admin_notes', sanitize_textarea_field($_POST['notes'] ?? ''));

            if ($subscription->save()) {
                $this->log_activity(
                    $subscription->get_id(),
                    'Assinatura atualizada pelo administrador',
                    [
                        'admin_id' => get_current_user_id(),
                        'changes' => $_POST
                    ]
                );

                wp_safe_redirect(esc_url_raw(
                    admin_url('admin.php?page=upmkt-subscriptions-list&message=updated&subscription_id=' . $subscription->get_id())
                ));
                exit;
            } else {
                throw new \Exception('Erro ao salvar assinatura.');
            }

        } catch (\Exception $e) {
            wp_die('Erro ao atualizar assinatura: ' . $e->getMessage());
        }
    }

    /**
     * Manipula cancelamento
     */
    private function handle_cancel_subscription(Subscription $subscription): void
    {
        try {
            if ($subscription->cancel()) {
                $subscription->save();

                $this->log_activity(
                    $subscription->get_id(),
                    'Assinatura cancelada pelo administrador',
                    ['admin_id' => get_current_user_id()]
                );

                wp_safe_redirect(esc_url_raw(
                    admin_url('admin.php?page=upmkt-subscriptions-list&message=cancelled')
                ));
                exit;
            }
        } catch (\Exception $e) {
            wp_die('Erro ao cancelar assinatura: ' . $e->getMessage());
        }
    }

    /**
     * Manipula retomada
     */
    private function handle_resume_subscription(Subscription $subscription): void
    {
        try {
            if ($subscription->resume()) {
                $subscription->save();

                $this->log_activity(
                    $subscription->get_id(),
                    'Assinatura retomada pelo administrador',
                    ['admin_id' => get_current_user_id()]
                );

                wp_safe_redirect(esc_url_raw(
                    admin_url('admin.php?page=upmkt-subscriptions-list&message=resumed')
                ));
                exit;
            }
        } catch (\Exception $e) {
            wp_die('Erro ao retomar assinatura: ' . $e->getMessage());
        }
    }

    /**
     * Manipula exclusão
     */
    private function handle_delete_subscription(Subscription $subscription): void
    {
        global $wpdb;

        try {
            $subscription_id = $subscription->get_id();

            // Registrar log antes de excluir
            $this->log_activity(
                $subscription_id,
                'Assinatura excluída permanentemente pelo administrador',
                ['admin_id' => get_current_user_id()]
            );

            // Excluir assinatura
            $deleted = $wpdb->delete(
                $wpdb->prefix . 'upmkt_subscriptions',
                ['id' => $subscription_id],
                ['%d']
            );

            if ($deleted) {
                // Excluir metadados
                $wpdb->delete(
                    $wpdb->prefix . 'upmkt_subscription_meta',
                    ['subscription_id' => $subscription_id],
                    ['%d']
                );

                wp_safe_redirect(esc_url_raw(
                    admin_url('admin.php?page=upmkt-subscriptions-list&message=deleted')
                ));
                exit;
            } else {
                throw new \Exception('Erro ao excluir assinatura do banco de dados.');
            }

        } catch (\Exception $e) {
            wp_die('Erro ao excluir assinatura: ' . $e->getMessage());
        }
    }

    /**
     * Registra atividade
     */
    private function log_activity(int $subscription_id, string $description, array $data = []): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscription_logs';

        // Criar tabela de logs se não existir
        $this->create_logs_table();

        $wpdb->insert(
            $table_name,
            [
                'subscription_id' => $subscription_id,
                'description' => $description,
                'data' => maybe_serialize($data),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s']
        );
    }

    /**
     * Cria tabela de logs
     */
    private function create_logs_table(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscription_logs';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            subscription_id bigint(20) NOT NULL,
            description text NOT NULL,
            data longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY subscription_id (subscription_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Renderiza notificações
     */
    private function render_admin_notices(): void
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
     * Retorna texto do status
     */
    private function get_status_text(string $status): string
    {
        $statuses = [
            'active' => 'Ativa',
            'pending' => 'Pendente',
            'cancelled' => 'Cancelada',
            'expired' => 'Expirada'
        ];

        return $statuses[$status] ?? $status;
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
     * Obtém dados do usuário com fallbacks de segurança
     */
    private function get_user_display_data(int $user_id): array
    {
        $user = get_userdata($user_id);

        if (!$user) {
            return [
                'display_name' => 'Usuário não encontrado',
                'user_email' => 'N/A',
                'exists' => false,
                'user_link' => '#'
            ];
        }

        return [
            'display_name' => $user->display_name ?: 'Usuário sem nome',
            'user_email' => $user->user_email ?: 'Sem email',
            'exists' => true,
            'user_link' => admin_url('user-edit.php?user_id=' . $user_id)
        ];
    }

}

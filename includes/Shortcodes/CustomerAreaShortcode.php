<?php

namespace UPMarket\Subscriptions\Shortcodes;

use UPMarket\Subscriptions\Entities\Subscription;
use UPMarket\Subscriptions\Core\Logger;

/**
 * Shortcode para área do cliente
 *
 * @package UPMarket\Subscriptions\Shortcodes
 */
class CustomerAreaShortcode
{
    /**
     * Construtor
     */
    public function __construct()
    {
        add_shortcode('upmkt_customer_area', [$this, 'render_customer_area']);
        add_action('wp_ajax_upmkt_cancel_subscription', [$this, 'cancel_subscription']);
        add_action('wp_ajax_upmkt_pause_subscription', [$this, 'pause_subscription']);
        add_action('wp_ajax_upmkt_resume_subscription', [$this, 'resume_subscription']);
        add_action('wp_ajax_upmkt_retry_payment', [$this, 'retry_payment']);
    }

    /**
     * Renderiza a área do cliente
     */
    public function render_customer_area($atts): string
    {
        if (isset($_GET['upmkt_checkout']) && $_GET['upmkt_checkout'] === 'success') {
            return $this->render_checkout_success();
        }

        if (!is_user_logged_in()) {
            return $this->render_login_required();
        }

        $user_id = get_current_user_id();
        $subscriptions = $this->get_user_subscriptions($user_id);

        ob_start();
        ?>
        <div class="upmkt-customer-area">
            <h2>Minhas Assinaturas</h2>
            
            <?php if (empty($subscriptions)): ?>
                <div class="upmkt-no-subscriptions">
                    <p>Você não possui assinaturas ativas.</p>
                    <p><a href="<?php echo esc_url(get_plans_page('planos')); ?>" class="button button-primary">Conhecer nossos planos</a></p>
                </div>
            <?php else: ?>
                <div class="upmkt-subscriptions-list">
                    <?php foreach ($subscriptions as $subscription): ?>
                        <?php $this->render_subscription_card($subscription); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza card de assinatura
     */
    private function render_subscription_card(Subscription $subscription): void
    {
        $plan = new \UPMarket\Subscriptions\Entities\SubscriptionPlan($subscription->get_plan_id());
        $plan_name = $plan->exists() ? $plan->get_name() : 'Plano não encontrado';
        $status = $subscription->get_status();
        $next_billing = $subscription->get_next_billing_date();
        $today = new \DateTime();

        $is_active = $subscription->is_active();
        $is_paused = $subscription->is_paused();
        $is_cancelled = $status === 'cancelled';
        $is_expired = $status === 'expired';
        $is_pending = $status === 'pending';
        $show_new_subscription_btn = $is_cancelled || $is_expired || ($is_paused && $next_billing <= $today);
        ?>
					<div class="upmkt-subscription-card">
							<div class="upmkt-subscription-header">
									<div class="upmkt-subscription-title">
											<?php echo esc_html($plan_name); ?>
									</div>
									<div class="upmkt-subscription-status upmkt-status-<?php echo esc_attr($status); ?>">
											<?php echo esc_html($this->get_status_text($status)); ?>
									</div>
							</div>
							
							<div class="upmkt-subscription-details">
									<div class="upmkt-detail-item">
											<strong>ID da Assinatura</strong>
											#<?php echo esc_html($subscription->get_id()); ?>
									</div>
									
									<div class="upmkt-detail-item">
											<strong>Data de Início</strong>
											<?php echo esc_html($subscription->get_start_date()->format('d/m/Y')); ?>
									</div>
									
									<?php if (!$is_pending): ?>
									<div class="upmkt-detail-item">
											<strong>Próxima Cobrança</strong>
											<?php echo esc_html($next_billing->format('d/m/Y')); ?>
									</div>
									<?php endif; ?>
									
									<div class="upmkt-detail-item">
											<strong>Valor</strong>
											R$ <?php echo esc_html(number_format($plan->get_price(), 2, ',', '.')); ?>
											<?php echo esc_html($this->get_billing_period_text($plan->get_billing_period())); ?>
									</div>
							</div>

							<!-- Status Pendente: Pagamento Falhou -->
							<?php if ($is_pending): ?>
									<div class="upmkt-recurrence-info danger">
											<p><strong>⚠️ Pagamento Pendente</strong></p>
											<p>O pagamento inicial falhou. Você precisa concluir o pagamento para ativar sua assinatura.</p>
											<p><strong>Motivo:</strong> Falha no processamento do pagamento. Verifique os dados do cartão e tente novamente.</p>
									</div>

							<!-- Status Ativo/Pausado/Cancelado (mantém o código original) -->
							<?php elseif ($is_paused): ?>
									<div class="upmkt-recurrence-info warning">
											<p><strong>⏸️ Recorrência Pausada</strong></p>
											<p>Você mantém o acesso até <strong><?php echo esc_html($next_billing->format('d/m/Y')); ?></strong>.</p>
											<p>Após esta data, a assinatura será cancelada automaticamente.</p>
									</div>
							<?php elseif ($is_active): ?>
									<div class="upmkt-recurrence-info">
											<p><strong>🔄 Recorrência Ativa</strong></p>
											<p>Próxima cobrança: <strong><?php echo esc_html($next_billing->format('d/m/Y')); ?></strong></p>
									</div>
							<?php elseif ($is_cancelled): ?>
									<div class="upmkt-recurrence-info danger">
											<p><strong>❌ Assinatura Cancelada</strong></p>
											<p>Seu acesso será mantido até <strong><?php echo esc_html($next_billing->format('d/m/Y')); ?></strong>.</p>
									</div>
							<?php endif; ?>

							<!-- Informações sobre pagamento (só mostra se não estiver pendente) -->
							<?php if (($is_active || $is_paused) && !$is_pending): ?>
									<div class="upmkt-payment-info">
											<h4>💳 Informações de Pagamento</h4>
											<p>Seus dados são armazenados de forma <strong>criptografada e segura</strong>.</p>
											<p>Para alterar o cartão:</p>
											<ul>
												<li>Cancele a assinatura atual</li>
												<li>Clique no botão "Assinar novamente"</li>
												<li>Crie uma nova assinatura com o novo cartão</li>
											</ul>
									</div>
							<?php endif; ?>
							
							<div class="upmkt-subscription-actions">
									<?php if ($is_pending): ?>
											<button class="upmkt-btn upmkt-btn-primary" 
															data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>"
															data-action="upmkt_retry_payment"
															onclick="upmktRetryPayment(<?php echo esc_attr($subscription->get_id()); ?>)">
													Efeturar pagamento
											</button>
											
											<button class="upmkt-btn upmkt-btn-danger" 
															data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>"
															data-action="upmkt_cancel_subscription"
															onclick="upmktCancelSubscription(<?php echo esc_attr($subscription->get_id()); ?>)">
													Cancelar
											</button>

											<div class="upmkt-actions-info">
													<small>
															<strong>Tentar Novamente:</strong> Reenvia o pagamento com os dados do cartão já cadastrados.<br>
															<strong>Cancelar:</strong> Remove esta assinatura pendente do seu perfil.
													</small>
											</div>

									<?php elseif ($is_active): ?>
											<!-- Código original para status ativo -->
											<button class="upmkt-btn upmkt-btn-warning" 
															data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>"
															data-action="upmkt_pause_subscription"
															onclick="upmktPauseSubscription(<?php echo esc_attr($subscription->get_id()); ?>)">
													Pausar recorrência
											</button>
											
											<button class="upmkt-btn upmkt-btn-danger" 
															data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>"
															data-action="upmkt_cancel_subscription"
															onclick="upmktCancelSubscription(<?php echo esc_attr($subscription->get_id()); ?>)">
													Cancelar
											</button>

											<div class="upmkt-actions-info">
													<small>
															<strong>Pausar:</strong> Mantém acesso até o vencimento, sem novas cobranças. Após o vencimento, cancela automaticamente.<br>
															<strong>Cancelar:</strong> Encerra definitivamente na data de vencimento. Você pode criar uma nova assinatura a qualquer momento.
													</small>
											</div>

									<?php elseif ($is_paused): ?>
											<!-- Código original para status pausado -->
											<button class="upmkt-btn upmkt-btn-success" 
															data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>"
															data-action="upmkt_resume_subscription"
															onclick="upmktResumeSubscription(<?php echo esc_attr($subscription->get_id()); ?>)">
													Retomar recorrência
											</button>
											
											<button class="upmkt-btn upmkt-btn-danger" 
															data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>"
															data-action="upmkt_cancel_subscription"
															onclick="upmktCancelSubscription(<?php echo esc_attr($subscription->get_id()); ?>)">
													Cancelar
											</button>

											<div class="upmkt-actions-info">
													<small>Retome a recorrência para continuar com o plano após <?php echo esc_html($next_billing->format('d/m/Y')); ?>, ou cancele para encerrar definitivamente.</small>
											</div>

									<?php elseif ($is_cancelled && $next_billing > $today): ?>
											<!-- Assinatura Cancelada mas ainda ativa -->
											<div class="upmkt-actions-info">
													<small>Assinatura cancelada. Acesso mantido até <?php echo esc_html($next_billing->format('d/m/Y')); ?>.</small>
											</div>

									<?php endif; ?>

									<!-- Botão para nova assinatura -->
									<?php if ($show_new_subscription_btn): ?>
											<a href="<?php echo esc_url(get_plans_page('planos')); ?>" class="upmkt-btn upmkt-btn-primary">
													Assinar novamente
											</a>
											<div class="upmkt-actions-info">
													<small>Crie uma nova assinatura para continuar aproveitando nossos serviços. Você pode escolher o mesmo plano ou experimentar outras opções.</small>
											</div>
									<?php endif; ?>
							</div>
					</div>
				<?php
    }

    /**
     * Retorna assinaturas do usuário
     */
    private function get_user_subscriptions(int $user_id): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscriptions';
        $subscription_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC",
                $user_id
            )
        );

        $subscriptions = [];
        foreach ($subscription_ids as $subscription_id) {
            $subscription = new Subscription($subscription_id);
            if ($subscription->exists()) {
                $subscriptions[] = $subscription;
            }
        }

        return $subscriptions;
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

    /**
     * Retorna texto do período de cobrança
     */
    private function get_billing_period_text(string $period): string
    {
        $periods = [
            'day' => '/dia',
            'month' => '/mês',
            'year' => '/ano'
        ];

        return $periods[$period] ?? '';
    }

    /**
     * Renderiza mensagem de sucesso no checkout
     */
    private function render_checkout_success(): string
    {
        $subscription_id = isset($_GET['subscription_id']) ? intval($_GET['subscription_id']) : 0;

        ob_start();
        ?>
        <div class="upmkt-checkout-success">
            <div class="upmkt-success-icon">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#28a745" stroke-width="2">
                    <path d="M20 6L9 17l-5-5"></path>
                </svg>
            </div>
            
            <h2>Assinatura Criada com Sucesso!</h2>
            
            <div class="upmkt-success-message">
                <p>Sua assinatura foi criada e ativada com sucesso.</p>
                
                <?php if ($subscription_id): ?>
                    <p><strong>ID da Assinatura:</strong> #<?php echo esc_html($subscription_id); ?></p>
                <?php endif; ?>
                
                <p>Você receberá um e-mail de confirmação em breve.</p>
                <p>Acesse sua <strong>Área do Cliente</strong> para gerenciar sua assinatura.</p>
            </div>
            
            <div class="upmkt-success-actions">
                <a href="<?php echo esc_url(remove_query_arg(['upmkt_checkout', 'subscription_id'])); ?>" 
                   class="upmkt-btn upmkt-btn-primary">
                    👤 Ir para Minha Área
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza mensagem de login com formulário PADRÃO WORDPRESS
     */
    private function render_login_required(): string
    {
        ob_start();
        ?>
					<div class="upmkt-login-required">
							<div class="upmkt-login-container">
									<h3>Acesse sua conta</h3>
									<p>Faça login para gerenciar suas assinaturas.</p>
									
									<!-- FORMULÁRIO PADRÃO WORDPRESS COM CLASSES PERSONALIZADAS -->
									<form name="upmkt-login-form" id="upmkt-login-form" class="upmkt-login-form" action="<?php echo esc_url(site_url('wp-login.php', 'login_post')); ?>" method="post">
											<div class="upmkt-form-group">
													<input type="text" 
																name="log" 
																id="user_login" 
																class="input" 
																value="" 
																size="20" 
																required 
																placeholder="e-mail">
											</div>
											
											<div class="upmkt-form-group">
													<input type="password" 
																name="pwd" 
																id="user_pass" 
																class="input" 
																value="" 
																size="20" 
																required 
																placeholder="senha">
											</div>
											
											<div class="upmkt-remember-me">
													<input name="rememberme" type="checkbox" id="rememberme" value="forever">
													<p>Manter conectado</p>
											</div>
											
											<div class="upmkt-form-group">
													<input type="submit" 
																name="wp-submit" 
																id="wp-submit" 
																class="upmkt-btn upmkt-btn-primary upmkt-submit-button" 
																value="Entrar">
													
													<!-- Campos hidden importantes -->
													<input type="hidden" name="redirect_to" value="<?php echo esc_url(get_permalink()); ?>">
											</div>
											
											<div class="upmkt-login-links">
													<p>
															<a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="upmkt-link">
																	Esqueceu sua senha?
															</a>
													</p>
													<p>
															Não tem uma conta? 
															<a href="<?php echo esc_url(home_url('/planos')); ?>" class="upmkt-link">
																	Assine agora
															</a>
													</p>
											</div>
									</form>
							</div>
					</div>
				<?php
        return ob_get_clean();
    }

    /**
     * Cancela assinatura via AJAX
     */
    public function cancel_subscription(): void
    {
        check_ajax_referer('upmkt_front_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuário não logado']);
            return;
        }

        $subscription_id = isset($_POST['subscription_id']) ? intval($_POST['subscription_id']) : 0;
        $user_id = get_current_user_id();

        if (!$subscription_id) {
            wp_send_json_error(['message' => 'Assinatura não especificada']);
            return;
        }

        try {
            $subscription = new Subscription($subscription_id);

            if (!$subscription->exists() || $subscription->get_user_id() !== $user_id) {
                wp_send_json_error(['message' => 'Assinatura não encontrada']);
                return;
            }

            $subscription_manager = new \UPMarket\Subscriptions\Services\SubscriptionManager();
            $result = $subscription_manager->cancel_subscription($subscription, 'user_request');

            if ($result) {
                wp_send_json_success(['message' => 'Assinatura cancelada com sucesso. Seu acesso será mantido até ' . $subscription->get_next_billing_date()->format('d/m/Y') . '. Você pode criar uma nova assinatura a qualquer momento.']);
            } else {
                wp_send_json_error(['message' => 'Erro ao cancelar assinatura']);
            }

        } catch (\Exception $e) {
            Logger::instance()->error('Cancel subscription error: ' . $e->getMessage(), 'customer_area');
            wp_send_json_error(['message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * Pausa assinatura via AJAX
     */
    public function pause_subscription(): void
    {
        check_ajax_referer('upmkt_front_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuário não logado']);
            return;
        }

        $subscription_id = isset($_POST['subscription_id']) ? intval($_POST['subscription_id']) : 0;
        $user_id = get_current_user_id();

        if (!$subscription_id) {
            wp_send_json_error(['message' => 'Assinatura não especificada']);
            return;
        }

        try {
            $subscription = new Subscription($subscription_id);

            if (!$subscription->exists() || $subscription->get_user_id() !== $user_id) {
                wp_send_json_error(['message' => 'Assinatura não encontrada']);
                return;
            }

            $subscription_manager = new \UPMarket\Subscriptions\Services\SubscriptionManager();
            $result = $subscription_manager->pause_subscription($subscription, 'user_request');

            if ($result) {
                wp_send_json_success(['message' => 'Recorrência pausada com sucesso. Você mantém o acesso até ' . $subscription->get_next_billing_date()->format('d/m/Y')]);
            } else {
                wp_send_json_error(['message' => 'Erro ao pausar recorrência']);
            }

        } catch (\Exception $e) {
            Logger::instance()->error('Pause subscription error: ' . $e->getMessage(), 'customer_area');
            wp_send_json_error(['message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * Retoma assinatura via AJAX
     */
    public function resume_subscription(): void
    {
        check_ajax_referer('upmkt_front_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuário não logado']);
            return;
        }

        $subscription_id = isset($_POST['subscription_id']) ? intval($_POST['subscription_id']) : 0;
        $user_id = get_current_user_id();

        if (!$subscription_id) {
            wp_send_json_error(['message' => 'Assinatura não especificada']);
            return;
        }

        try {
            $subscription = new Subscription($subscription_id);

            if (!$subscription->exists() || $subscription->get_user_id() !== $user_id) {
                wp_send_json_error(['message' => 'Assinatura não encontrada']);
                return;
            }

            $subscription_manager = new \UPMarket\Subscriptions\Services\SubscriptionManager();
            $result = $subscription_manager->resume_subscription($subscription, 'user_request');

            if ($result) {
                wp_send_json_success(['message' => 'Recorrência retomada com sucesso! Próxima cobrança: ' . $subscription->get_next_billing_date()->format('d/m/Y')]);
            } else {
                wp_send_json_error(['message' => 'Erro ao retomar recorrência']);
            }

        } catch (\Exception $e) {
            Logger::instance()->error('Resume subscription error: ' . $e->getMessage(), 'customer_area');
            wp_send_json_error(['message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * Tenta pagamento novamente redirecionando para o checkout
     */
    public function retry_payment(): void
    {
        check_ajax_referer('upmkt_front_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuário não logado']);
            return;
        }

        $subscription_id = isset($_POST['subscription_id']) ? intval($_POST['subscription_id']) : 0;
        $user_id = get_current_user_id();

        if (!$subscription_id) {
            wp_send_json_error(['message' => 'Assinatura não especificada']);
            return;
        }

        try {
            $subscription = new Subscription($subscription_id);

            if (!$subscription->exists() || $subscription->get_user_id() !== $user_id) {
                wp_send_json_error(['message' => 'Assinatura não encontrada']);
                return;
            }

            if ($subscription->get_status() !== 'pending') {
                wp_send_json_error(['message' => 'Esta assinatura não está pendente de pagamento']);
                return;
            }

            // Obtém o ID do plano da assinatura pendente
            $plan_id = $subscription->get_plan_id();

            // Obtém a URL do checkout com o plano
            $checkout_page = get_checkout_page();
            $redirect_url = add_query_arg(['plan_id' => $plan_id], $checkout_page);

            wp_send_json_success([
                'message' => 'Redirecionando para o checkout...',
                'redirect_url' => $redirect_url
            ]);

        } catch (\Exception $e) {
            Logger::instance()->error('Retry payment error: ' . $e->getMessage(), 'customer_area');
            wp_send_json_error(['message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }
}

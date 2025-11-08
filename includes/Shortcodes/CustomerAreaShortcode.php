<?php

namespace UPMarket\Subscriptions\Shortcodes;

use UPMarket\Subscriptions\Entities\Subscription;
use UPMarket\Subscriptions\Core\Logger;

/**
 * Shortcode para área do doador
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
     * Renderiza a área do doador
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

        // Verifica se deve mostrar o botão "Doar novamente" fora do loop
        $show_global_resubscribe_btn = $this->should_show_global_resubscribe_button($subscriptions);

        ob_start();
        ?>
        <div class="upmkt-customer-area">
            <h3>Minhas Doações</h3>
            
            <?php if (empty($subscriptions)): ?>
                <div class="upmkt-no-subscriptions">
                    <p>Você não possui doações ativas.</p>
                    <p><a href="<?php echo esc_url(get_plans_page('planos')); ?>" class="button button-primary">Conhecer nossos planos de doação</a></p>
                </div>
            <?php else: ?>
                <div class="upmkt-subscriptions-list">
                    <?php foreach ($subscriptions as $subscription): ?>
                        <?php $this->render_subscription_card($subscription); ?>
                    <?php endforeach; ?>
                </div>

                <!-- BOTÃO DOAR NOVAMENTE (FORA DO LOOP) -->
                <?php if ($show_global_resubscribe_btn): ?>
                    <div class="upmkt-global-resubscribe">
                        <div class="upmkt-resubscribe-content">
                            <h5>Quer fazer uma doação novamente?</h5>
                            <a href="<?php echo esc_url(get_plans_page('planos')); ?>" class="upmkt-btn upmkt-btn-primary upmkt-btn-large">
                                Doar novamente
                            </a>
                            <div class="upmkt-actions-info">
                                <small>Você pode escolher o mesmo plano ou experimentar outras opções.</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Verifica se deve mostrar o botão "Doar novamente" global
     */
    private function should_show_global_resubscribe_button(array $subscriptions): bool
    {
        if (empty($subscriptions)) {
            return false;
        }

        $has_active_subscription = false;
        $has_cancelled_or_expired = false;

        foreach ($subscriptions as $subscription) {
            $status = $subscription->get_status();

            // Se tem qualquer doação ativa, pendente ou pausada, NÃO mostra o botão global
            if (in_array($status, ['active', 'pending', 'paused'])) {
                $has_active_subscription = true;
            }

            // Se tem pelo menos uma doação cancelada ou expirada
            if (in_array($status, ['cancelled', 'expired'])) {
                $has_cancelled_or_expired = true;
            }
        }

        // Mostra o botão global apenas se:
        // - NÃO tem doações ativas/pendentes/pausadas
        // - E tem pelo menos uma doação cancelada/expirada
        return !$has_active_subscription && $has_cancelled_or_expired;
    }

    /**
     * Renderiza card de doação recorrente
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
                    <strong>ID da Doação</strong>
                    #<?php echo esc_html($subscription->get_id()); ?>
                </div>
                
                <div class="upmkt-detail-item">
                    <strong>Data de Início</strong>
                    <?php echo esc_html($subscription->get_start_date()->format('d/m/Y')); ?>
                </div>
                
                <?php if (!$is_pending): ?>
                <div class="upmkt-detail-item">
                    <strong>Próxima Doação</strong>
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
                    <h6><i class="fas fa-exclamation-triangle"></i> Doação Pendente</h6>
                    O pagamento inicial falhou. Você precisa concluir o pagamento para ativar sua doação.<br>
                    <strong>Motivo:</strong> Falha no processamento do pagamento. Verifique os dados do cartão e tente novamente.
                </div>

            <!-- Status Ativo/Pausado/Cancelado (mantém o código original) -->
            <?php elseif ($is_paused): ?>
                <div class="upmkt-recurrence-info warning">
                    <h6><i class="fas fa-pause"></i> Doação Pausada</h6>
                    Você mantém o status de doador até <strong><?php echo esc_html($next_billing->format('d/m/Y')); ?></strong>.<br>
                    Após esta data, a doação recorrente será cancelada automaticamente.<br>
                </div>
            <?php elseif ($is_active): ?>
                <div class="upmkt-recurrence-info">
                    <strong><i class="fas fa-sync-alt"></i> Doação Ativa</strong><br>
                    Próxima doação: <strong><?php echo esc_html($next_billing->format('d/m/Y')); ?></strong>
                </div>
            <?php elseif ($is_cancelled): ?>
                <div class="upmkt-recurrence-info danger">
                    <strong><i class="fas fa-times-circle"></i> Doação Cancelada</strong><br>
                    Seu status de doador será mantido até <strong><?php echo esc_html($next_billing->format('d/m/Y')); ?></strong>.
                </div>
            <?php endif; ?>

						<!-- Informações sobre pagamento (só mostra se não estiver pendente) -->
						<?php if (($is_active || $is_paused) && !$is_pending): ?>
								<?php $card_info = $this->get_card_display_info($subscription); ?>
								<div class="upmkt-payment-info">
										<h6><i class="fas fa-credit-card"></i> Informações de Pagamento</h6>
										
										<div class="upmkt-card-info">
												<?php if ($card_info['has_last_four']): ?>
														<div class="upmkt-card-number">
																<strong>Cartão:</strong> •••• •••• •••• <?php echo esc_html($card_info['last_four']); ?>
														</div>
														<div class="text-danger">
																<small>Token do Cartão: <b><?php echo esc_html($card_info['token_display']); ?></b> <--REMOVER ESTA LINHA</small>
														</div>
														<div class="my-1">
															Seus dados são armazenados de forma <strong>criptografada e segura</strong>.
														</div>
														<div class="my-1">
															Para alterar o cartão:
															<ul>
																	<li>Cancele a doação atual</li>
																	<li>Clique no botão "Doar novamente"</li>
																	<li>Crie uma nova doação com o novo cartão</li>
															</ul>
														</div>
														</div>
												<?php else: ?>
														<div class="upmkt-card-number">
																<strong>Cartão:</strong> <span style="color: #dd3a49;">Informações não disponíveis</span>
														</div>
												<?php endif; ?>
										</div>
								</div>
						<?php endif; ?>
            
            <div class="upmkt-subscription-actions">
                <?php if ($is_pending): ?>
                    <button class="upmkt-btn upmkt-btn-primary" 
                                    data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>"
                                    data-action="upmkt_retry_payment"
                                    onclick="upmktRetryPayment(<?php echo esc_attr($subscription->get_id()); ?>)">
                            Efetuar pagamento
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
                            <strong>Cancelar:</strong> Remove esta doação pendente do seu perfil.
                        </small>
                    </div>

                <?php elseif ($is_active): ?>
                    <!-- Código original para status ativo -->
                    <button class="upmkt-btn upmkt-btn-warning" 
                                    data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>"
                                    data-action="upmkt_pause_subscription"
                                    onclick="upmktPauseSubscription(<?php echo esc_attr($subscription->get_id()); ?>)">
                            Pausar doação
                    </button>
                    
                    <button class="upmkt-btn upmkt-btn-danger" 
                                    data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>"
                                    data-action="upmkt_cancel_subscription"
                                    onclick="upmktCancelSubscription(<?php echo esc_attr($subscription->get_id()); ?>)">
                            Cancelar doação
                    </button>

                    <div class="upmkt-actions-info">
                        <small>
                            <strong>Pausar:</strong> Mantém status de doador até o vencimento, sem novas cobranças. Após o vencimento, cancela automaticamente.<br>
                            <strong>Cancelar:</strong> Encerra definitivamente na data de vencimento. Você pode fazer uma nova doação a qualquer momento.
                        </small>
                    </div>

                <?php elseif ($is_paused): ?>
                    <!-- Código original para status pausado -->
                    <button class="upmkt-btn upmkt-btn-success" 
                                    data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>"
                                    data-action="upmkt_resume_subscription"
                                    onclick="upmktResumeSubscription(<?php echo esc_attr($subscription->get_id()); ?>)">
                            Retomar doação
                    </button>
                    
                    <button class="upmkt-btn upmkt-btn-danger" 
                                    data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>"
                                    data-action="upmkt_cancel_subscription"
                                    onclick="upmktCancelSubscription(<?php echo esc_attr($subscription->get_id()); ?>)">
                            Cancelar doação
                    </button>

                    <div class="upmkt-actions-info">
                        <small>Retome a doação para continuar como doador após <?php echo esc_html($next_billing->format('d/m/Y')); ?>, ou cancele para encerrar definitivamente.</small>
                    </div>

                <?php elseif ($is_cancelled && $next_billing > $today): ?>
                    <!-- Doação Cancelada mas ainda ativa -->
                    <div class="upmkt-actions-info">
                        <small>Doação cancelada. Status de doador mantido até <?php echo esc_html($next_billing->format('d/m/Y')); ?>.</small>
                    </div>

                <?php endif; ?>

            </div>
        </div>
        <?php
    }

    /**
     * Retorna doações do usuário
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
            
            <h3>Doação criada com sucesso!</h3>
            
            <div class="upmkt-success-message">
                <p>Sua doação foi criada e ativada com sucesso.</p>
                
                <?php if ($subscription_id): ?>
                    <p><strong>ID da Doação:</strong> #<?php echo esc_html($subscription_id); ?></p>
                <?php endif; ?>
                
                <p>Você receberá um e-mail de confirmação em breve.</p>
                <p>Acesse a área do usuário para gerenciar sua doação.</p>
            </div>
            
            <div class="upmkt-success-actions">
                <a href="<?php echo esc_url(remove_query_arg(['upmkt_checkout', 'subscription_id'])); ?>" 
                   class="upmkt-btn upmkt-btn-primary">
                    VER MINHA ÁREA
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
                                    <p>Faça login para gerenciar suas doações.</p>
                                    
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
                                                                    Faça uma doação agora
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
     * Cancela doação recorrente via AJAX
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
            wp_send_json_error(['message' => 'Doação não especificada']);
            return;
        }

        try {
            $subscription = new Subscription($subscription_id);

            if (!$subscription->exists() || $subscription->get_user_id() !== $user_id) {
                wp_send_json_error(['message' => 'Doação não encontrada']);
                return;
            }

            $subscription_manager = new \UPMarket\Subscriptions\Services\SubscriptionManager();
            $result = $subscription_manager->cancel_subscription($subscription, 'user_request');

            if ($result) {
                wp_send_json_success(['message' => 'Doação cancelada com sucesso. Seu status de doador será mantido até ' . $subscription->get_next_billing_date()->format('d/m/Y') . '. Você pode fazer uma nova doação a qualquer momento.']);
            } else {
                wp_send_json_error(['message' => 'Erro ao cancelar doação']);
            }

        } catch (\Exception $e) {
            Logger::instance()->error('Cancel subscription error: ' . $e->getMessage(), 'customer_area');
            wp_send_json_error(['message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * Pausa doação recorrente via AJAX
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
            wp_send_json_error(['message' => 'Doação não especificada']);
            return;
        }

        try {
            $subscription = new Subscription($subscription_id);

            if (!$subscription->exists() || $subscription->get_user_id() !== $user_id) {
                wp_send_json_error(['message' => 'Doação não encontrada']);
                return;
            }

            $subscription_manager = new \UPMarket\Subscriptions\Services\SubscriptionManager();
            $result = $subscription_manager->pause_subscription($subscription, 'user_request');

            if ($result) {
                wp_send_json_success(['message' => 'Doação pausada com sucesso. Você mantém o status de doador até ' . $subscription->get_next_billing_date()->format('d/m/Y')]);
            } else {
                wp_send_json_error(['message' => 'Erro ao pausar doação']);
            }

        } catch (\Exception $e) {
            Logger::instance()->error('Pause subscription error: ' . $e->getMessage(), 'customer_area');
            wp_send_json_error(['message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * Retoma doação recorrente via AJAX
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
            wp_send_json_error(['message' => 'Doação não especificada']);
            return;
        }

        try {
            $subscription = new Subscription($subscription_id);

            if (!$subscription->exists() || $subscription->get_user_id() !== $user_id) {
                wp_send_json_error(['message' => 'Doação não encontrada']);
                return;
            }

            $subscription_manager = new \UPMarket\Subscriptions\Services\SubscriptionManager();
            $result = $subscription_manager->resume_subscription($subscription, 'user_request');

            if ($result) {
                wp_send_json_success(['message' => 'Doação retomada com sucesso! Próxima doação: ' . $subscription->get_next_billing_date()->format('d/m/Y')]);
            } else {
                wp_send_json_error(['message' => 'Erro ao retomar doação']);
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
            wp_send_json_error(['message' => 'Doação não especificada']);
            return;
        }

        try {
            $subscription = new Subscription($subscription_id);

            if (!$subscription->exists() || $subscription->get_user_id() !== $user_id) {
                wp_send_json_error(['message' => 'Doação não encontrada']);
                return;
            }

            if ($subscription->get_status() !== 'pending') {
                wp_send_json_error(['message' => 'Esta doação não está pendente de pagamento']);
                return;
            }

            // Obtém o ID do plano da doação pendente
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

    /**
     * Obtém informações do cartão para exibição
     */
    private function get_card_display_info(Subscription $subscription): array
    {
        $card_token = $subscription->get_meta('rede_card_token');
        $last_four = $subscription->get_meta('card_last_four');

        return [
            'token' => $card_token,
            'last_four' => $last_four,
            'has_token' => !empty($card_token),
            'has_last_four' => !empty($last_four),
            'token_display' => $card_token ? $card_token : 'Não tokenizado'
        ];
    }
}

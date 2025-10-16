<?php

namespace UPMarket\Subscriptions\Shortcodes;

use UPMarket\Subscriptions\Entities\SubscriptionPlan;
use UPMarket\Subscriptions\Core\Logger;

/**
 * Shortcode para checkout de assinatura
 *
 * @package UPMarket\Subscriptions\Shortcodes
 */
class CheckoutShortcode
{
    /**
     * Construtor
     */
    public function __construct()
    {
        add_shortcode('upmkt_checkout', [$this, 'render_checkout']);
        add_action('save_post', [$this, 'detect_checkout_page']);
        add_action('wp_ajax_upmkt_process_checkout', [$this, 'process_checkout']);
        add_action('wp_ajax_nopriv_upmkt_process_checkout', [$this, 'process_checkout']);
    }

    public function detect_checkout_page($post_id)
    {
        // Evitar auto-saves e revisões
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Ignorar revisões - pegar apenas o post principal
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Ignorar autodrafts do Gutenberg
        $post = get_post($post_id);
        if ($post->post_status === 'auto-draft') {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $has_shortcode = has_shortcode($post->post_content, 'upmkt_checkout');

        if ($has_shortcode) {
            // Só atualizar se for um post publicado ou rascunho normal
            if (in_array($post->post_status, ['publish', 'draft'])) {
                update_option('upmkt_checkout_page_id', $post_id);
                update_post_meta($post_id, '_upmkt_is_checkout_page', true);
            }
        } else {
            // Se esta página era marcada como checkout mas perdeu o shortcode
            if (get_post_meta($post_id, '_upmkt_is_checkout_page', true)) {
                delete_option('upmkt_checkout_page_id');
                delete_post_meta($post_id, '_upmkt_is_checkout_page');
            }
        }
    }

    /**
     * Renderiza o formulário de checkout
     *
     * @param array $atts
     * @return string
     */
    public function render_checkout($atts): string
    {
        $plan_id = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : 0;

        if (!$plan_id) {
            return '<p>Plano não especificado. <a href="' . home_url() . '">Voltar para a página inicial</a>.</p>';
        }

        $plan = new SubscriptionPlan($plan_id);

        if (!$plan->exists() || !$plan->is_active()) {
            return '<p>Plano não encontrado ou indisponível.</p>';
        }

        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return $this->render_login_required();
        }

        ob_start();
        ?>
        <div class="upmkt-checkout">
            <div class="upmkt-checkout-summary">
                <h3>Resumo da Assinatura</h3>
                <div class="upmkt-plan-summary">
                    <strong><?php echo esc_html($plan->get_name()); ?></strong><br>
                    <?php echo esc_html($plan->get_formatted_price()); ?> / <?php echo esc_html($plan->get_formatted_period()); ?>
                    
                    <?php if ($plan->has_trial()): ?>
                        <div class="upmkt-trial-info">
                            <?php echo esc_html($plan->get_trial_period_days()); ?> dias de teste grátis
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <form id="upmkt-checkout-form" class="upmkt-checkout-form">
                <?php wp_nonce_field('upmkt_process_checkout', 'upmkt_nonce'); ?>
                <input type="hidden" name="action" value="upmkt_process_checkout">
                <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan_id); ?>">
                
                <div class="upmkt-payment-methods">
                    <h3>Método de Pagamento</h3>
                    
                    <div class="upmkt-payment-method">
                        <input type="radio" id="payment_rede" name="payment_method" value="rede" checked>
                        <label for="payment_rede">Cartão de Crédito (Rede)</label>
                        
                        <div class="upmkt-payment-details" id="rede-details">
                            <div class="upmkt-form-group">
                                <label for="card_number">Número do Cartão</label>
                                <input type="text" id="card_number" name="card_number" placeholder="0000 0000 0000 0000" required>
                            </div>
                            
                            <div class="upmkt-form-row">
                                <div class="upmkt-form-group">
                                    <label for="card_expiry">Validade (MM/AA)</label>
                                    <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/AA" required>
                                </div>
                                
                                <div class="upmkt-form-group">
                                    <label for="card_cvv">CVV</label>
                                    <input type="text" id="card_cvv" name="card_cvv" placeholder="123" required>
                                </div>
                            </div>
                            
                            <div class="upmkt-form-group">
                                <label for="card_holder">Nome no Cartão</label>
                                <input type="text" id="card_holder" name="card_holder" placeholder="Como está no cartão" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="upmkt-form-actions">
                    <button type="submit" class="upmkt-submit-button">
                        Finalizar Assinatura
                    </button>
                    
                    <div class="upmkt-loading" style="display: none;">
                        Processando pagamento...
                    </div>
                </div>
                
                <div class="upmkt-messages"></div>
            </form>
        </div>
        
        <style>
            .upmkt-checkout {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            
            .upmkt-checkout-summary {
                background: #f9f9f9;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 20px;
            }
            
            .upmkt-plan-summary {
                font-size: 1.2em;
            }
            
            .upmkt-trial-info {
                color: #28a745;
                font-size: 0.9em;
                margin-top: 10px;
            }
            
            .upmkt-payment-methods {
                margin-bottom: 20px;
            }
            
            .upmkt-payment-method {
                margin-bottom: 15px;
            }
            
            .upmkt-payment-method input[type="radio"] {
                margin-right: 10px;
            }
            
            .upmkt-payment-details {
                margin-top: 15px;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fafafa;
            }
            
            .upmkt-form-group {
                margin-bottom: 15px;
            }
            
            .upmkt-form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            
            .upmkt-form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
            
            .upmkt-form-group input {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 16px;
            }
            
            .upmkt-submit-button {
                background: #007cba;
                color: white;
                padding: 15px 30px;
                border: none;
                border-radius: 4px;
                font-size: 1.1em;
                cursor: pointer;
                width: 100%;
            }
            
            .upmkt-submit-button:hover {
                background: #005a87;
            }
            
            .upmkt-loading {
                text-align: center;
                padding: 10px;
                color: #666;
            }
            
            .upmkt-messages {
                margin-top: 15px;
            }
            
            .upmkt-message {
                padding: 10px;
                border-radius: 4px;
                margin-bottom: 10px;
            }
            
            .upmkt-message.success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            
            .upmkt-message.error {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#upmkt-checkout-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $submit = $form.find('.upmkt-submit-button');
                var $loading = $form.find('.upmkt-loading');
                var $messages = $form.find('.upmkt-messages');
                
                $submit.prop('disabled', true);
                $loading.show();
                $messages.empty();
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: $form.serialize(),
                    success: function(response) {
                        if (response.success) {
                            $messages.html('<div class="upmkt-message success">' + response.data.message + '</div>');
                            
                            // Redireciona após sucesso
                            if (response.data.redirect_url) {
                                setTimeout(function() {
                                    window.location.href = response.data.redirect_url;
                                }, 2000);
                            }
                        } else {
                            var errorHtml = '<div class="upmkt-message error"><strong>Erro:</strong><ul>';
                            response.data.errors.forEach(function(error) {
                                errorHtml += '<li>' + error + '</li>';
                            });
                            errorHtml += '</ul></div>';
                            
                            $messages.html(errorHtml);
                        }
                    },
                    error: function() {
                        $messages.html('<div class="upmkt-message error">Erro de conexão. Tente novamente.</div>');
                    },
                    complete: function() {
                        $submit.prop('disabled', false);
                        $loading.hide();
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza mensagem de login necessário
     *
     * @return string
     */
    private function render_login_required(): string
    {
        $login_url = wp_login_url(get_permalink());
        $register_url = wp_registration_url();

        ob_start();
        ?>
        <div class="upmkt-login-required">
            <h3>Login Necessário</h3>
            <p>Você precisa estar logado para assinar um plano.</p>
            <p>
                <a href="<?php echo esc_url($login_url); ?>" class="button">Fazer Login</a>
                <?php if (get_option('users_can_register')): ?>
                    <a href="<?php echo esc_url($register_url); ?>" class="button">Criar Conta</a>
                <?php endif; ?>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Processa o checkout via AJAX
     */
    public function process_checkout(): void
    {
        check_ajax_referer('upmkt_process_checkout', 'upmkt_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['errors' => ['Usuário não logado']]);
            return;
        }

        $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
        $payment_method = sanitize_text_field($_POST['payment_method'] ?? '');

        if (!$plan_id) {
            wp_send_json_error(['errors' => ['Plano não especificado']]);
            return;
        }

        try {
            $user_id = get_current_user_id();
            $payment_data = [
                'payment_method' => $payment_method,
                'card_number' => sanitize_text_field($_POST['card_number'] ?? ''),
                'card_expiry' => sanitize_text_field($_POST['card_expiry'] ?? ''),
                'card_cvv' => sanitize_text_field($_POST['card_cvv'] ?? ''),
                'card_holder' => sanitize_text_field($_POST['card_holder'] ?? ''),
                'amount' => 0 // Será calculado pelo plano
            ];

            // TODO: Integrar com SubscriptionManager
            $subscription_manager = new \UPMarket\Subscriptions\Services\SubscriptionManager();
            $result = $subscription_manager->create_subscription($user_id, $plan_id, $payment_data, $payment_method);

            if ($result['success']) {
                wp_send_json_success([
                    'message' => 'Assinatura criada com sucesso!',
                    'redirect_url' => $this->get_success_url($result['subscription_id'])
                ]);
            } else {
                wp_send_json_error(['errors' => $result['errors']]);
            }

        } catch (\Exception $e) {
            Logger::instance()->error('Checkout processing error: ' . $e->getMessage(), 'checkout');
            wp_send_json_error(['errors' => ['Erro ao processar assinatura: ' . $e->getMessage()]]);
        }
    }

    /**
     * Retorna URL de sucesso
     *
     * @param int $subscription_id
     * @return string
     */
    private function get_success_url(int $subscription_id): string
    {
        return add_query_arg([
            'upmkt_action' => 'success',
            'subscription_id' => $subscription_id
        ], get_permalink());
    }
}

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
    }

    /**
     * Renderiza a área do cliente
     *
     * @param array $atts
     * @return string
     */
    public function render_customer_area($atts): string
    {
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
                    <p><a href="<?php echo home_url(); ?>" class="button">Conhecer nossos planos</a></p>
                </div>
            <?php else: ?>
                <div class="upmkt-subscriptions-list">
                    <?php foreach ($subscriptions as $subscription): ?>
                        <?php $this->render_subscription_card($subscription); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
            .upmkt-customer-area {
                max-width: 800px;
                margin: 0 auto;
            }
            
            .upmkt-subscription-card {
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
                background: white;
            }
            
            .upmkt-subscription-header {
                display: flex;
                justify-content: between;
                align-items: center;
                margin-bottom: 15px;
            }
            
            .upmkt-subscription-title {
                font-size: 1.3em;
                font-weight: bold;
                color: #333;
            }
            
            .upmkt-subscription-status {
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 0.9em;
                font-weight: bold;
            }
            
            .upmkt-status-active {
                background: #d4edda;
                color: #155724;
            }
            
            .upmkt-status-pending {
                background: #fff3cd;
                color: #856404;
            }
            
            .upmkt-status-cancelled {
                background: #f8d7da;
                color: #721c24;
            }
            
            .upmkt-subscription-details {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-bottom: 15px;
            }
            
            .upmkt-detail-item strong {
                display: block;
                color: #666;
                font-size: 0.9em;
            }
            
            .upmkt-subscription-actions {
                border-top: 1px solid #eee;
                padding-top: 15px;
                text-align: right;
            }
            
            .upmkt-cancel-button {
                background: #dc3545;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 4px;
                cursor: pointer;
            }
            
            .upmkt-cancel-button:hover {
                background: #c82333;
            }
            
            .upmkt-cancel-button:disabled {
                background: #6c757d;
                cursor: not-allowed;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Retorna assinaturas do usuário
     *
     * @param int $user_id
     * @return array
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
     * Renderiza card de assinatura
     *
     * @param Subscription $subscription
     */
    private function render_subscription_card(Subscription $subscription): void
    {
        $plan = new \UPMarket\Subscriptions\Entities\SubscriptionPlan($subscription->get_plan_id());
        $plan_name = $plan->exists() ? $plan->get_name() : 'Plano não encontrado';
        ?>
        <div class="upmkt-subscription-card">
            <div class="upmkt-subscription-header">
                <div class="upmkt-subscription-title">
                    <?php echo esc_html($plan_name); ?>
                </div>
                <div class="upmkt-subscription-status upmkt-status-<?php echo esc_attr($subscription->get_status()); ?>">
                    <?php echo esc_html($this->get_status_text($subscription->get_status())); ?>
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
                
                <div class="upmkt-detail-item">
                    <strong>Próxima Cobrança</strong>
                    <?php echo esc_html($subscription->get_next_billing_date()->format('d/m/Y')); ?>
                </div>
                
                <div class="upmkt-detail-item">
                    <strong>Gateway</strong>
                    <?php echo esc_html($subscription->get_meta('gateway_id', 'N/A')); ?>
                </div>
            </div>
            
            <?php if ($subscription->is_active()): ?>
                <div class="upmkt-subscription-actions">
                    <button class="upmkt-cancel-button" 
                            data-subscription-id="<?php echo esc_attr($subscription->get_id()); ?>"
                            onclick="upmktCancelSubscription(<?php echo esc_attr($subscription->get_id()); ?>)">
                        Cancelar Assinatura
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Retorna texto do status
     *
     * @param string $status
     * @return string
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
     * Renderiza mensagem de login necessário
     *
     * @return string
     */
    private function render_login_required(): string
    {
        $login_url = wp_login_url(get_permalink());

        ob_start();
        ?>
        <div class="upmkt-login-required">
            <p>Você precisa estar logado para acessar esta área.</p>
            <p><a href="<?php echo esc_url($login_url); ?>" class="button">Fazer Login</a></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Cancela assinatura via AJAX
     */
    public function cancel_subscription(): void
    {
        check_ajax_referer('upmkt_nonce', 'nonce');

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
                wp_send_json_success(['message' => 'Assinatura cancelada com sucesso']);
            } else {
                wp_send_json_error(['message' => 'Erro ao cancelar assinatura']);
            }

        } catch (\Exception $e) {
            Logger::instance()->error('Cancel subscription error: ' . $e->getMessage(), 'customer_area');
            wp_send_json_error(['message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }
}

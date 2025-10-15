<?php

namespace UPMarket\Subscriptions\Shortcodes;

use UPMarket\Subscriptions\Entities\SubscriptionPlan;
use UPMarket\Subscriptions\Core\Logger;

/**
 * Shortcode para exibir planos de assinatura
 *
 * @package UPMarket\Subscriptions\Shortcodes
 */
class SubscriptionPlansShortcode
{
    /**
     * Construtor
     */
    public function __construct()
    {
        add_shortcode('upmkt_subscription_plans', [$this, 'render_plans']);
    }

    /**
     * Renderiza a listagem de planos
     *
     * @param array $atts
     * @return string
     */
    public function render_plans($atts): string
    {
        $atts = shortcode_atts([
            'layout' => 'grid', // grid, list
            'columns' => '3',
            'show_trial' => 'yes',
            'class' => ''
        ], $atts);

        try {
            $plans = SubscriptionPlan::get_active_plans();

            if (empty($plans)) {
                return '<p>Nenhum plano de assinatura disponível no momento.</p>';
            }

            ob_start();
            ?>
            <div class="upmkt-plans upmkt-plans-<?php echo esc_attr($atts['layout']); ?> <?php echo esc_attr($atts['class']); ?>">
                <?php foreach ($plans as $plan): ?>
                    <div class="upmkt-plan-item">
                        <?php $this->render_plan_card($plan, $atts); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <style>
                .upmkt-plans-grid {
                    display: grid;
                    grid-template-columns: repeat(<?php echo esc_attr($atts['columns']); ?>, 1fr);
                    gap: 20px;
                    margin: 20px 0;
                }
                
                .upmkt-plan-card {
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    padding: 20px;
                    text-align: center;
                    transition: all 0.3s ease;
                }
                
                .upmkt-plan-card:hover {
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                    transform: translateY(-2px);
                }
                
                .upmkt-plan-name {
                    font-size: 1.5em;
                    font-weight: bold;
                    margin-bottom: 10px;
                    color: #333;
                }
                
                .upmkt-plan-price {
                    font-size: 2em;
                    font-weight: bold;
                    color: #007cba;
                    margin-bottom: 10px;
                }
                
                .upmkt-plan-period {
                    font-size: 0.9em;
                    color: #666;
                }
                
                .upmkt-plan-features {
                    list-style: none;
                    padding: 0;
                    margin: 20px 0;
                    text-align: left;
                }
                
                .upmkt-plan-features li {
                    padding: 5px 0;
                    border-bottom: 1px solid #f0f0f0;
                }
                
                .upmkt-plan-features li:last-child {
                    border-bottom: none;
                }
                
                .upmkt-plan-button {
                    display: inline-block;
                    background: #007cba;
                    color: white;
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 4px;
                    font-weight: bold;
                    transition: background 0.3s ease;
                }
                
                .upmkt-plan-button:hover {
                    background: #005a87;
                    color: white;
                }
                
                .upmkt-plan-trial {
                    font-size: 0.9em;
                    color: #28a745;
                    margin-top: 10px;
                }
            </style>
            <?php
            return ob_get_clean();

        } catch (\Exception $e) {
            Logger::instance()->error('Shortcode plans error: ' . $e->getMessage(), 'shortcodes');
            return '<p>Erro ao carregar planos de assinatura.</p>';
        }
    }

    /**
     * Renderiza card individual do plano
     *
     * @param SubscriptionPlan $plan
     * @param array $atts
     */
    private function render_plan_card(SubscriptionPlan $plan, array $atts): void
    {
        ?>
        <div class="upmkt-plan-card">
            <div class="upmkt-plan-name">
                <?php echo esc_html($plan->get_name()); ?>
            </div>
            
            <div class="upmkt-plan-price">
                <?php echo esc_html($plan->get_formatted_price()); ?>
            </div>
            
            <div class="upmkt-plan-period">
                por <?php echo esc_html($plan->get_formatted_period()); ?>
            </div>
            
            <?php if ($atts['show_trial'] === 'yes' && $plan->has_trial()): ?>
                <div class="upmkt-plan-trial">
                    <?php echo esc_html($plan->get_trial_period_days()); ?> dias grátis
                </div>
            <?php endif; ?>
            
            <?php $features = $plan->get_features(); ?>
            <?php if (!empty($features)): ?>
                <ul class="upmkt-plan-features">
                    <?php foreach ($features as $feature): ?>
                        <li>✓ <?php echo esc_html($feature); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <a href="<?php echo esc_url($this->get_checkout_url($plan->get_id())); ?>" class="upmkt-plan-button">
                Assinar Agora
            </a>
        </div>
        <?php
    }

    /**
     * Retorna URL do checkout para o plano
     *
     * @param int $plan_id
     * @return string
     */
    private function get_checkout_url(int $plan_id): string
    {
        return add_query_arg([
            'upmkt_action' => 'checkout',
            'plan_id' => $plan_id
        ], get_permalink());
    }
}

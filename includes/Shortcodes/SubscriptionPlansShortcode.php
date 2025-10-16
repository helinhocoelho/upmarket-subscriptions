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
							<div class="upmkt-plans upmkt-plans-grid">
									<?php foreach ($plans as $plan): ?>
											<?php $this->render_plan_card($plan, $atts); ?>
									<?php endforeach; ?>
							</div>
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
        // Dados principais do plano
        $name        = esc_html($plan->get_name());
        $price       = esc_html($plan->get_formatted_price());
        $description = esc_html($plan->get_description());
        //$icon        = esc_attr($plan->get_icon_class() ?: 'fa-medal');
        $features    = $plan->get_features() ?: [];
        $checkout_url = esc_url($this->get_checkout_url($plan->get_id()));

        ?>
    <div class="fusion-layout-column fusion_builder_column_inner fusion-flex-column" style="
        --awb-bg-blend: overlay;
        --awb-bg-size: cover;
        --awb-width-large: 25%;
        --awb-margin-top-large: 0px;
        --awb-spacing-right-large: 7.68%;
        --awb-margin-bottom-large: 50px;
        --awb-spacing-left-large: 7.68%;
        --awb-width-medium: 33.3333333333%;
        --awb-spacing-right-medium: 5.76%;
        --awb-spacing-left-medium: 5.76%;
        --awb-width-small: 100%;
        --awb-spacing-right-small: 1.92%;
        --awb-spacing-left-small: 1.92%;
    ">
        <div class="fusion-column-wrapper fusion-flex-justify-content-flex-start fusion-content-layout-column">
            <div class="fusion-content-boxes content-boxes columns row fusion-columns-1 content-boxes-icon-on-top content-left"
                style="--awb-hover-accent-color: var(--awb-color5); --awb-circle-hover-accent-color: transparent; --awb-item-margin-bottom: 40px;">
                <div style="--awb-backgroundcolor: rgba(255, 255, 255, 0);" 
                    class="fusion-column content-box-column col-lg-12 fusion-content-box-hover">
                    <div class="col content-box-wrapper content-wrapper link-area-link-icon link-type-text icon-hover-animation-slide">
                        <div class="heading heading-with-icon icon-left">
                            <a class="heading-link" href="<?php echo $checkout_url; ?>" target="_blank" rel="noopener noreferrer">
                                <div class="icon">
                                    <i class="fontawesome-icon fas fa-envelope <?php //echo $icon;?> circle-yes"
                                        style="
                                            border-color: var(--awb-color5);
                                            border-width: 1px;
                                            background-color: rgba(51, 51, 51, 0);
                                            box-sizing: content-box;
                                            height: 100px;
                                            width: 100px;
                                            line-height: 100px;
                                            border-radius: 50%;
                                            font-size: 50px;
                                        ">
                                    </i>
                                </div>
                                <h2 class="content-box-heading fusion-responsive-typography-calculated"
                                    style="--h2_typography-font-size: 20px; line-height: var(--awb-typography1-line-height);">
                                    <?php echo $name; ?>
                                </h2>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <i class="fb-icon-element fontawesome-icon creative-angle-down circle-no fusion-animated"
                style="--awb-font-size: 30px; --awb-align-self: center; animation-duration: 0.5s;"
                data-animationtype="fadeInDown"></i>

            <div class="fusion-separator fusion-full-width-sep" style="margin: 20px 0; width: 100%;"></div>

            <?php if (!empty($description)) : ?>
                <div class="fusion-text min-h-105" style="--awb-font-size: 0.7em;">
                    <p style="text-align: center;"><?php echo $description; ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($features)) : ?>
                <div class="fusion-separator" style="margin: 20px auto; width: 100%; max-width: 60%;">
                    <div class="fusion-separator-border sep-single sep-solid"
                        style="--awb-height: 20px; border-color: var(--awb-color4); border-top-width: 1px;">
                    </div>
                </div>
                <div class="fusion-text min-h-85" style="--awb-font-size: 0.7em;">
                    <ul style="list-style: none; padding: 0; text-align: center;">
                        <?php foreach ($features as $feature): ?>
                            <li style="margin-bottom: 5px;"><?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div style="text-align: center;">
                <a class="fusion-button button-flat fusion-button-default-size button-default mb-60"
                    href="<?php echo $checkout_url; ?>"
                    target="_blank" rel="noopener noreferrer"
                    style="--button_text_transform: none;">
                    <span class="fusion-button-text"><?php echo $price; ?></span>
                </a>
            </div>
        </div>
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

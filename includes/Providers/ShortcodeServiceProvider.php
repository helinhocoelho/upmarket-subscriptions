<?php

namespace UPMarket\Subscriptions\Providers;

use UPMarket\Subscriptions\Shortcodes\SubscriptionPlansShortcode;
use UPMarket\Subscriptions\Shortcodes\CheckoutShortcode;
use UPMarket\Subscriptions\Shortcodes\CustomerAreaShortcode;

/**
 * Service Provider para Shortcodes
 *
 * @package UPMarket\Subscriptions\Providers
 */
class ShortcodeServiceProvider
{
    /**
     * Registra todos os shortcodes
     */
    public function register(): void
    {
        new SubscriptionPlansShortcode();
        new CheckoutShortcode();
        new CustomerAreaShortcode();

        // Hook para outros shortcodes
        do_action('upmkt_register_shortcodes');
    }
}

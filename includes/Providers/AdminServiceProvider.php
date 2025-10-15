<?php

namespace UPMarket\Subscriptions\Providers;

use UPMarket\Subscriptions\Admin\AdminMenu;
use UPMarket\Subscriptions\Admin\GatewaySettings;

/**
 * Service Provider para funcionalidades do Admin
 *
 * @package UPMarket\Subscriptions\Providers
 */
class AdminServiceProvider
{
    /**
     * Registra todas as funcionalidades do admin
     */
    public function register(): void
    {
        new AdminMenu();
        new GatewaySettings();

        do_action('upmkt_register_admin');
    }
}

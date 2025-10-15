<?php

namespace UPMarket\Subscriptions\Providers;

use UPMarket\Subscriptions\Admin\AdminMenu;

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

        // TODO: Adicionar outros componentes do admin

        // Hook para extensões
        do_action('upmkt_register_admin');
    }
}

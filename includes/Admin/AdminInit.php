<?php

namespace UPMarket\Subscriptions\Admin;

/**
 * Inicializador administrativo
 *
 * @package UPMarket\Subscriptions\Admin
 */
class AdminInit
{
    /**
     * Inicializa todas as funcionalidades administrativas
     */
    public static function init(): void
    {
        // AdminMenu já se inicializa sozinho via hooks

        // GatewaySettings
        $gatewaySettings = new GatewaySettings();

        // SubscriptionEdit
        $subscriptionEdit = new SubscriptionEdit();
        $subscriptionEdit->init();

        // Outras inicializações administrativas...
    }
}

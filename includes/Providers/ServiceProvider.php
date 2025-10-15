<?php

namespace UPMarket\Subscriptions\Providers;

use UPMarket\Subscriptions\Core\Container;
use UPMarket\Subscriptions\Services\SubscriptionManager;
use UPMarket\Subscriptions\Services\NotificationService;
use UPMarket\Subscriptions\Handlers\CronHandlers;

/**
 * Service Provider para serviços do plugin
 *
 * @package UPMarket\Subscriptions\Providers
 */
class ServiceProvider
{
    /**
     * Registra todos os serviços
     *
     * @param Container $container
     */
    public function register(Container $container): void
    {
        $container->singleton('subscription_manager', function () {
            return new SubscriptionManager();
        });

        $container->singleton('notification_service', function () {
            return new NotificationService();
        });

        $container->singleton('cron_handlers', function () {
            return new CronHandlers();
        });

        // Outros serviços podem ser registrados aqui
        do_action('upmkt_register_services', $container);
    }
}

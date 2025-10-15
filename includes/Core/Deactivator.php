<?php

namespace UPMarket\Subscriptions\Core;

/**
 * Responsável pela desativação do plugin
 *
 * @package UPMarket\Subscriptions\Core
 */
class Deactivator
{
    /**
     * Executa na desativação do plugin
     */
    public static function deactivate(): void
    {
        self::clear_scheduled_events();
        flush_rewrite_rules();
    }

    /**
     * Limpa eventos agendados
     */
    private static function clear_scheduled_events(): void
    {
        $timestamp = wp_next_scheduled('upmkt_daily_subscription_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'upmkt_daily_subscription_check');
        }

        $timestamp = wp_next_scheduled('upmkt_hourly_payment_retry');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'upmkt_hourly_payment_retry');
        }
    }
}

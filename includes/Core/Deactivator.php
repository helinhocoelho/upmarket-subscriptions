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
        // Remove eventos cron
        self::clear_scheduled_events();

        flush_rewrite_rules();
    }

    /**
     * Limpa eventos agendados
     */
    private static function clear_scheduled_events(): void
    {
        // Implementaremos no sistema de recorrência
    }
}

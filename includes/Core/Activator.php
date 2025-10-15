<?php

namespace UPMarket\Subscriptions\Core;

/**
 * Responsável pela ativação do plugin
 *
 * @package UPMarket\Subscriptions\Core
 */
class Activator
{
    /**
     * Executa na ativação do plugin
     */
    public static function activate(): void
    {
        // Cria tabelas customizadas se necessário
        self::create_tables();

        // Adiciona capabilities se necessário
        self::add_capabilities();

        // Agenda eventos cron
        self::schedule_events();

        flush_rewrite_rules();
    }

    /**
     * Cria tabelas customizadas no banco
     */
    private static function create_tables(): void
    {
        // Implementaremos quando criar as entidades
    }

    /**
     * Adiciona capabilities para roles
     */
    private static function add_capabilities(): void
    {
        // Implementaremos posteriormente
    }

    /**
     * Agenda eventos cron
     */
    private static function schedule_events(): void
    {
        // Implementaremos no sistema de recorrência
    }
}

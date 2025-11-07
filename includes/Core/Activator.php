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
        self::create_tables();
        self::add_capabilities();
        self::schedule_events();
        flush_rewrite_rules();
    }

    /**
     * Cria tabelas customizadas no banco
     */
    private static function create_tables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = [];

        // Tabela de planos de doação
        $sql[] = "CREATE TABLE {$wpdb->prefix}upmkt_subscription_plans (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
						icon_class varchar(100),
            description text,
            price decimal(10,2) NOT NULL DEFAULT '0.00',
            billing_period varchar(20) NOT NULL DEFAULT 'month',
            billing_frequency int(11) NOT NULL DEFAULT 1,
            trial_period_days int(11) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            features longtext,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        // Tabela de doações
        $sql[] = "CREATE TABLE {$wpdb->prefix}upmkt_subscriptions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            plan_id bigint(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            start_date datetime NOT NULL,
            next_billing_date datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY plan_id (plan_id),
            KEY status (status)
        ) {$charset_collate};";

        // Tabela de metadados das doações
        $sql[] = "CREATE TABLE {$wpdb->prefix}upmkt_subscription_meta (
            meta_id bigint(20) NOT NULL AUTO_INCREMENT,
            subscription_id bigint(20) NOT NULL,
            meta_key varchar(255),
            meta_value longtext,
            PRIMARY KEY (meta_id),
            KEY subscription_id (subscription_id),
            KEY meta_key (meta_key)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ($sql as $query) {
            dbDelta($query);
        }

        Logger::instance()->info('Database tables created successfully', 'activation');
    }

    /**
     * Adiciona capabilities para roles
     */
    private static function add_capabilities(): void
    {
        $admin_role = get_role('administrator');

        if ($admin_role) {
            $admin_role->add_cap('manage_upmkt_subscriptions');
            $admin_role->add_cap('view_upmkt_subscriptions');
            $admin_role->add_cap('edit_upmkt_subscriptions');
        }
    }

    /**
     * Agenda eventos cron
     */
    private static function schedule_events(): void
    {
        if (!wp_next_scheduled('upmkt_daily_subscription_check')) {
            wp_schedule_event(time(), 'daily', 'upmkt_daily_subscription_check');
        }

        if (!wp_next_scheduled('upmkt_hourly_payment_retry')) {
            wp_schedule_event(time(), 'hourly', 'upmkt_hourly_payment_retry');
        }
    }
}

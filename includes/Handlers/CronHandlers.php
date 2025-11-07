<?php

namespace UPMarket\Subscriptions\Handlers;

use UPMarket\Subscriptions\Core\Logger;
use UPMarket\Subscriptions\Services\SubscriptionManager;

/**
 * Handlers para tarefas agendadas via Cron
 *
 * @package UPMarket\Subscriptions\Handlers
 */
class CronHandlers
{
    /**
     * @var SubscriptionManager
     */
    private $subscription_manager;

    /**
     * Construtor
     */
    public function __construct(SubscriptionManager $subscription_manager = null)
    {
        // Usa injeção de dependência ou busca do container
        $this->subscription_manager = $subscription_manager ?? \UPMarket\Subscriptions\Core\Plugin::instance()->container()->subscription_manager;
        $this->init_hooks();
    }

    /**
     * Inicializa os hooks do Cron
     */
    private function init_hooks(): void
    {
        // Daily subscription processing
        add_action('upmkt_daily_subscription_check', [$this, 'handle_daily_subscriptions']);

        // Hourly payment retry
        add_action('upmkt_hourly_payment_retry', [$this, 'handle_payment_retries']);

        // Monthly cleanup
        add_action('upmkt_monthly_cleanup', [$this, 'handle_monthly_cleanup']);
    }

    /**
     * Processa doações diárias
     */
    public function handle_daily_subscriptions(): void
    {
        Logger::instance()->info('Starting daily subscription processing', 'cron');

        try {
            $this->subscription_manager->process_daily_subscriptions();
            Logger::instance()->info('Daily subscription processing completed', 'cron');
        } catch (\Exception $e) {
            Logger::instance()->error('Daily subscription processing failed: ' . $e->getMessage(), 'cron');
        }
    }

    /**
     * Processa retentativas de pagamento
     */
    public function handle_payment_retries(): void
    {
        Logger::instance()->info('Starting payment retry processing', 'cron');

        try {
            $this->subscription_manager->process_payment_retries();
            Logger::instance()->info('Payment retry processing completed', 'cron');
        } catch (\Exception $e) {
            Logger::instance()->error('Payment retry processing failed: ' . $e->getMessage(), 'cron');
        }
    }

    /**
     * Limpeza mensal de dados antigos
     */
    public function handle_monthly_cleanup(): void
    {
        Logger::instance()->info('Starting monthly cleanup', 'cron');

        try {
            $this->clean_old_logs();
            $this->clean_expired_subscriptions();
            Logger::instance()->info('Monthly cleanup completed', 'cron');
        } catch (\Exception $e) {
            Logger::instance()->error('Monthly cleanup failed: ' . $e->getMessage(), 'cron');
        }
    }

    /**
     * Limpa logs antigos
     */
    private function clean_old_logs(): void
    {
        $log_file = WP_CONTENT_DIR . '/upmkt-debug.log';

        if (file_exists($log_file)) {
            // Mantém apenas os últimos 30 dias de logs
            $max_age = 30 * DAY_IN_SECONDS;
            $file_time = filemtime($log_file);

            if (time() - $file_time > $max_age) {
                unlink($log_file);
                Logger::instance()->info('Old log file cleaned up', 'cleanup');
            }
        }
    }

    /**
     * Limpa doações expiradas
     */
    private function clean_expired_subscriptions(): void
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscriptions';
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

        $expired_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} 
                 WHERE status = 'cancelled' 
                 AND updated_at < %s",
                $thirty_days_ago
            )
        );

        Logger::instance()->info("Found {$expired_count} expired subscriptions for cleanup", 'cleanup');
    }
}

<?php

namespace UPMarket\Subscriptions\Services;

use UPMarket\Subscriptions\Entities\Subscription;
use UPMarket\Subscriptions\Core\GatewayManager;
use UPMarket\Subscriptions\Core\Logger;

/**
 * Gerenciador principal de doações
 *
 * @package UPMarket\Subscriptions\Services
 */
class SubscriptionManager
{
    /**
     * @var GatewayManager
     */
    private $gateway_manager;

    /**
     * Construtor
     */
    public function __construct()
    {
        $this->gateway_manager = GatewayManager::instance();
        $this->init_hooks();
    }

    /**
     * Inicializa os hooks
     */
    private function init_hooks(): void
    {
        // Hook para processar doações diariamente
        add_action('upmkt_daily_subscription_check', [$this, 'process_daily_subscriptions']);

        // Hook para retentativa de pagamentos
        add_action('upmkt_hourly_payment_retry', [$this, 'process_payment_retries']);

        // Hook para webhooks
        add_action('upmkt_webhook_rede', [$this, 'handle_rede_webhook']);
    }

    /**
     * Cria uma nova doação
     *
     * @param int $user_id
     * @param int $plan_id
     * @param array $payment_data
     * @param string $gateway_id
     * @return array
     */
    public function create_subscription(int $user_id, int $plan_id, array $payment_data, string $gateway_id = 'rede'): array
    {
        Logger::instance()->info("Creating subscription for user {$user_id}, plan {$plan_id}", 'subscriptions');

        // Validações básicas
        if (!$user_id || !$plan_id) {
            return [
                'success' => false,
                'errors' => ['User ID and Plan ID are required']
            ];
        }

        // Busca o plano para usar no cálculo da data
        $plan = new \UPMarket\Subscriptions\Entities\SubscriptionPlan($plan_id);
        if (!$plan->exists() || !$plan->is_active()) {
            return [
                'success' => false,
                'errors' => ['Plano não encontrado ou indisponível']
            ];
        }

        // Busca o gateway
        $gateway = $this->gateway_manager->get_gateway($gateway_id);
        if (!$gateway || !$gateway->is_configured()) {
            return [
                'success' => false,
                'errors' => ['Payment gateway not available']
            ];
        }

        try {
            // Cria a doação com status pending
            $subscription = Subscription::create($user_id, $plan_id, [
                'start_date' => current_time('mysql'),
                'next_billing_date' => $this->calculate_next_billing_date($plan),
                'meta' => [
                    'gateway_id' => $gateway_id,
                    'payment_method' => $payment_data['payment_method'] ?? 'credit_card'
                ]
            ]);

            if (!$subscription) {
                return [
                    'success' => false,
                    'errors' => ['Failed to create subscription']
                ];
            }

            // CORREÇÃO: Adicionar amount e currency aos dados de pagamento
            $payment_data['amount'] = $plan->get_price();
            $payment_data['currency'] = 'BRL'; // Moeda fixa para Brasil

            error_log("Payment data with amount and currency: " . print_r([
                'amount' => $payment_data['amount'],
                'currency' => $payment_data['currency'],
                'card_number' => substr($payment_data['card_number'] ?? '', 0, 6) . '...', // Log parcial por segurança
                'card_holder' => $payment_data['card_holder'] ?? ''
            ], true));

            // Processa o pagamento inicial
            $payment_result = $gateway->process_initial_payment($payment_data, $subscription);

            if ($payment_result['success']) {
                // Atualiza doação para ativa
                $subscription->set_status(Subscription::STATUS_ACTIVE);
                $subscription->set_meta('initial_transaction_id', $payment_result['transaction_id']);
                $subscription->save();

                Logger::instance()->info("Subscription {$subscription->get_id()} created successfully", 'subscriptions');

                return [
                    'success' => true,
                    'subscription_id' => $subscription->get_id(),
                    'message' => 'Doação criada com sucesso'
                ];
            } else {
                // Falha no pagamento - mantém como pending
                $subscription->set_meta('payment_errors', $payment_result['errors']);
                $subscription->save();

                return [
                    'success' => false,
                    'subscription_id' => $subscription->get_id(),
                    'errors' => $payment_result['errors']
                ];
            }

        } catch (\Exception $e) {
            Logger::instance()->error("Subscription creation error: " . $e->getMessage(), 'subscriptions');

            return [
                'success' => false,
                'errors' => ['Erro ao criar doação: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Processa cobranças recorrentes diárias
     */
    public function process_daily_subscriptions(): void
    {
        Logger::instance()->info('Starting daily subscription processing', 'cron');

        $subscriptions = $this->get_subscriptions_due_for_billing();

        foreach ($subscriptions as $subscription) {
            $this->process_recurring_payment($subscription);
        }

        Logger::instance()->info("Processed {$subscriptions} subscriptions due for billing", 'cron');
    }

    /**
     * Processa uma cobrança recorrente específica
     *
     * @param Subscription $subscription
     * @return array
     */
    public function process_recurring_payment(Subscription $subscription): array
    {
        Logger::instance()->info("Processing recurring payment for subscription {$subscription->get_id()}", 'subscriptions');

        $gateway_id = $subscription->get_meta('gateway_id', 'rede');
        $gateway = $this->gateway_manager->get_gateway($gateway_id);

        if (!$gateway) {
            Logger::instance()->error("Gateway {$gateway_id} not found for subscription {$subscription->get_id()}", 'subscriptions');
            return ['success' => false, 'errors' => ['Gateway not available']];
        }

        try {
            $payment_result = $gateway->process_recurring_payment($subscription);

            if ($payment_result['success']) {
                // Atualiza próxima data de cobrança
                $plan = new \UPMarket\Subscriptions\Entities\SubscriptionPlan($subscription->get_plan_id());
                $next_billing_date = $this->calculate_next_billing_date($plan);

                $subscription->set_prop('next_billing_date', $next_billing_date);
                $subscription->set_meta('last_payment_date', current_time('mysql'));
                $subscription->set_meta('last_transaction_id', $payment_result['transaction_id']);
                $subscription->save();

                Logger::instance()->info("Recurring payment successful for subscription {$subscription->get_id()}", 'subscriptions');

                // Dispara ação para notificações
                do_action('upmkt_recurring_payment_success', $subscription, $payment_result);

                return [
                    'success' => true,
                    'message' => 'Cobrança recorrente processada com sucesso'
                ];
            } else {
                // Falha no pagamento
                $retry_count = $subscription->get_meta('payment_retry_count', 0) + 1;
                $subscription->set_meta('payment_retry_count', $retry_count);
                $subscription->set_meta('last_payment_error', $payment_result['errors'][0] ?? 'Unknown error');
                $subscription->save();

                Logger::instance()->warning("Recurring payment failed for subscription {$subscription->get_id()}, retry {$retry_count}", 'subscriptions');

                // Dispara ação para notificações de falha
                do_action('upmkt_recurring_payment_failed', $subscription, $payment_result, $retry_count);

                return [
                    'success' => false,
                    'errors' => $payment_result['errors'],
                    'retry_count' => $retry_count
                ];
            }

        } catch (\Exception $e) {
            Logger::instance()->error("Recurring payment error for subscription {$subscription->get_id()}: " . $e->getMessage(), 'subscriptions');

            return [
                'success' => false,
                'errors' => ['Erro ao processar cobrança recorrente: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Processa retentativas de pagamento
     */
    public function process_payment_retries(): void
    {
        $subscriptions = $this->get_subscriptions_with_payment_failures();

        foreach ($subscriptions as $subscription) {
            $retry_count = $subscription->get_meta('payment_retry_count', 0);
            $max_retries = 3;

            if ($retry_count < $max_retries) {
                $this->process_recurring_payment($subscription);
            } else {
                // Cancela doação após max retries
                $this->cancel_subscription($subscription, 'payment_failure');
            }
        }
    }

    /**
     * Cancela uma doação
     *
     * @param Subscription $subscription
     * @param string $reason
     * @return bool
     */
    public function cancel_subscription(Subscription $subscription, string $reason = 'user_request'): bool
    {
        Logger::instance()->info("Canceling subscription {$subscription->get_id()}, reason: {$reason}", 'subscriptions');

        try {
            // Cancela no gateway
            $gateway_id = $subscription->get_meta('gateway_id', 'rede');
            $gateway = $this->gateway_manager->get_gateway($gateway_id);

            if ($gateway) {
                $gateway->cancel_subscription($subscription);
            }

            // Atualiza status local
            $subscription->cancel();
            $subscription->set_meta('cancellation_reason', $reason);
            $subscription->set_meta('cancelled_at', current_time('mysql'));
            $subscription->save();

            // Dispara ação para notificações
            do_action('upmkt_subscription_cancelled', $subscription, $reason);

            Logger::instance()->info("Subscription {$subscription->get_id()} cancelled successfully", 'subscriptions');

            return true;

        } catch (\Exception $e) {
            Logger::instance()->error("Subscription cancellation error: " . $e->getMessage(), 'subscriptions');
            return false;
        }
    }

    /**
     * Retorna doações com cobrança pendente
     *
     * @return array
     */
    private function get_subscriptions_due_for_billing(): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscriptions';
        $today = current_time('mysql');

        $subscription_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} 
                 WHERE status = 'active' 
                 AND next_billing_date <= %s 
                 ORDER BY next_billing_date ASC",
                $today
            )
        );

        $subscriptions = [];
        foreach ($subscription_ids as $subscription_id) {
            $subscription = new Subscription($subscription_id);
            if ($subscription->exists()) {
                $subscriptions[] = $subscription;
            }
        }

        return $subscriptions;
    }

    /**
     * Retorna doações com falhas de pagamento
     *
     * @return array
     */
    private function get_subscriptions_with_payment_failures(): array
    {
        global $wpdb;

        $subscriptions_table = $wpdb->prefix . 'upmkt_subscriptions';
        $meta_table = $wpdb->prefix . 'upmkt_subscription_meta';

        $subscription_ids = $wpdb->get_col(
            "SELECT s.id FROM {$subscriptions_table} s
             INNER JOIN {$meta_table} m ON s.id = m.subscription_id
             WHERE s.status = 'active'
             AND m.meta_key = 'payment_retry_count'
             AND m.meta_value > 0
             ORDER BY s.next_billing_date ASC"
        );

        $subscriptions = [];
        foreach ($subscription_ids as $subscription_id) {
            $subscription = new Subscription($subscription_id);
            if ($subscription->exists()) {
                $subscriptions[] = $subscription;
            }
        }

        return $subscriptions;
    }

    /**
     * Calcula a próxima data de cobrança
     *
     * @param mixed $plan
     * @return \DateTime
     */
    private function calculate_next_billing_date($plan): \DateTime
    {
        $next_date = new \DateTime();

        if ($plan instanceof \UPMarket\Subscriptions\Entities\SubscriptionPlan) {
            $period = $plan->get_billing_period();
            $frequency = $plan->get_billing_frequency();

            switch ($period) {
                case 'day':
                    $next_date->modify("+{$frequency} days");
                    break;
                case 'month':
                    $next_date->modify("+{$frequency} months");
                    break;
                case 'year':
                    $next_date->modify("+{$frequency} years");
                    break;
                default:
                    $next_date->modify('+1 month');
            }
        } else {
            // Default: 1 mês
            $next_date->modify('+1 month');
        }

        return $next_date;
    }

    /**
     * Pausa uma doação
     *
     * @param Subscription $subscription
     * @param string $reason
     * @return bool
     */
    public function pause_subscription(Subscription $subscription, string $reason = 'user_request'): bool
    {
        Logger::instance()->info("Pausing subscription {$subscription->get_id()}, reason: {$reason}", 'subscriptions');

        try {
            // Verificar se a doação pode ser pausada
            if (!$subscription->is_active()) {
                throw new \Exception('Apenas doações ativas podem ser pausadas.');
            }

            // Salvar a próxima data de cobrança original para quando for retomada
            $original_next_billing = $subscription->get_next_billing_date();
            $subscription->set_meta('original_next_billing_date', $original_next_billing->format('Y-m-d H:i:s'));

            // Atualizar status para pausado
            $subscription->set_status(Subscription::STATUS_PAUSED);
            $subscription->set_meta('paused_at', current_time('mysql'));
            $subscription->set_meta('paused_reason', $reason);
            $subscription->save();

            // Dispara ação para notificações
            do_action('upmkt_subscription_paused', $subscription, $reason);

            Logger::instance()->info("Subscription {$subscription->get_id()} paused successfully", 'subscriptions');

            return true;

        } catch (\Exception $e) {
            Logger::instance()->error("Subscription pause error: " . $e->getMessage(), 'subscriptions');
            return false;
        }
    }

    /**
     * Retoma uma doação pausada
     *
     * @param Subscription $subscription
     * @param string $reason
     * @return bool
     */
    public function resume_subscription(Subscription $subscription, string $reason = 'user_request'): bool
    {
        Logger::instance()->info("Resuming subscription {$subscription->get_id()}, reason: {$reason}", 'subscriptions');

        try {
            // Verificar se a doação pode ser retomada
            if (!$subscription->is_paused()) {
                throw new \Exception('Apenas doações pausadas podem ser retomadas.');
            }

            // Restaurar a próxima data de cobrança original
            $original_date = $subscription->get_meta('original_next_billing_date');
            if ($original_date) {
                $next_billing_date = new \DateTime($original_date);

                // Se a data original já passou, recalcula a partir de hoje
                if ($next_billing_date < new \DateTime()) {
                    $plan = new \UPMarket\Subscriptions\Entities\SubscriptionPlan($subscription->get_plan_id());
                    $next_billing_date = $this->calculate_next_billing_date($plan);
                }

                $subscription->set_next_billing_date($next_billing_date);
            }

            // Atualizar status para ativo
            $subscription->set_status(Subscription::STATUS_ACTIVE);
            $subscription->set_meta('resumed_at', current_time('mysql'));
            $subscription->set_meta('resumed_reason', $reason);

            // Limpar metadados de pausa
            $subscription->set_meta('original_next_billing_date', null);

            $subscription->save();

            // Dispara ação para notificações
            do_action('upmkt_subscription_resumed', $subscription, $reason);

            Logger::instance()->info("Subscription {$subscription->get_id()} resumed successfully", 'subscriptions');

            return true;

        } catch (\Exception $e) {
            Logger::instance()->error("Subscription resume error: " . $e->getMessage(), 'subscriptions');
            return false;
        }
    }

    /**
     * Manipula webhooks da Rede
     */
    public function handle_rede_webhook(): void
    {
        // TODO: Implementar processamento de webhooks da Rede
        Logger::instance()->info('Rede webhook received', 'webhooks');
    }
}

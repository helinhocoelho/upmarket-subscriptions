<?php

namespace UPMarket\Subscriptions\Handlers;

use UPMarket\Subscriptions\Core\Logger;

/**
 * Handlers para webhooks de gateways
 *
 * @package UPMarket\Subscriptions\Handlers
 */
class WebhookHandlers
{
    /**
     * Construtor
     */
    public function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Inicializa os hooks
     */
    private function init_hooks(): void
    {
        // Webhooks da Rede
        add_action('upmkt_rede_transaction_approved', [$this, 'handle_rede_approved'], 10, 3);
        add_action('upmkt_rede_transaction_denied', [$this, 'handle_rede_denied'], 10, 3);
        add_action('upmkt_rede_transaction_captured', [$this, 'handle_rede_captured'], 10, 2);
    }

    /**
     * Manipula transação aprovada da Rede
     */
    public function handle_rede_approved(string $transaction_id, string $reference, array $data): void
    {
        Logger::instance()->info("Processing Rede approved transaction: {$transaction_id}", 'webhooks');

        // Extrair ID da assinatura da reference
        $subscription_id = $this->extract_subscription_id($reference);

        if ($subscription_id) {
            $this->update_subscription_status($subscription_id, 'active', $transaction_id);
        }
    }

    /**
     * Manipula transação negada da Rede
     */
    public function handle_rede_denied(string $transaction_id, string $reference, array $data): void
    {
        Logger::instance()->warning("Processing Rede denied transaction: {$transaction_id}", 'webhooks');

        $subscription_id = $this->extract_subscription_id($reference);

        if ($subscription_id) {
            $this->update_subscription_status($subscription_id, 'pending', $transaction_id);

            // Notificar usuário sobre falha no pagamento
            do_action('upmkt_payment_failed', $subscription_id, $transaction_id);
        }
    }

    /**
     * Manipula transação capturada da Rede
     */
    public function handle_rede_captured(string $transaction_id, array $data): void
    {
        Logger::instance()->info("Processing Rede captured transaction: {$transaction_id}", 'webhooks');

        // TODO: Implementar lógica para transações capturadas
        // Normalmente usado para confirmação final de pagamento
    }

    /**
     * Extrai ID da assinatura da reference
     */
    private function extract_subscription_id(string $reference): ?int
    {
        if (preg_match('/subscription_(\d+)/', $reference, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    /**
     * Atualiza status da assinatura
     */
    private function update_subscription_status(int $subscription_id, string $status, string $transaction_id): void
    {
        try {
            $subscription = new \UPMarket\Subscriptions\Entities\Subscription($subscription_id);

            if ($subscription->exists()) {
                $subscription->set_status($status);
                $subscription->set_meta('last_transaction_id', $transaction_id);
                $subscription->set_meta('last_webhook_update', current_time('mysql'));
                $subscription->save();

                Logger::instance()->info(
                    "Subscription {$subscription_id} status updated to {$status} via webhook",
                    'webhooks'
                );
            }
        } catch (\Exception $e) {
            Logger::instance()->error(
                "Error updating subscription status: " . $e->getMessage(),
                'webhooks'
            );
        }
    }
}

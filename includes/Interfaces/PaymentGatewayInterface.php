<?php

namespace UPMarket\Subscriptions\Interfaces;

use UPMarket\Subscriptions\Interfaces\SubscriptionInterface;

/**
 * Interface para Gateways de Pagamento
 *
 * @package UPMarket\Subscriptions\Interfaces
 */
interface PaymentGatewayInterface
{
    /**
     * Processa um pagamento inicial
     *
     * @param array $payment_data Dados do pagamento
     * @param SubscriptionInterface $subscription Doação
     * @return array
     */
    public function process_initial_payment(array $payment_data, SubscriptionInterface $subscription): array;

    /**
     * Processa um pagamento recorrente
     *
     * @param SubscriptionInterface $subscription Doação
     * @return array
     */
    public function process_recurring_payment(SubscriptionInterface $subscription): array;

    /**
     * Cancela uma doação no gateway
     *
     * @param SubscriptionInterface $subscription Doação
     * @return bool
     */
    public function cancel_subscription(SubscriptionInterface $subscription): bool;

    /**
     * Verifica o status de um pagamento
     *
     * @param string $transaction_id ID da transação
     * @return array
     */
    public function check_payment_status(string $transaction_id): array;

    /**
     * Processa webhooks do gateway
     *
     * @return void
     */
    public function process_webhook(): void;

    /**
     * Retorna as configurações do gateway
     *
     * @return array
     */
    public function get_settings(): array;

    /**
     * Verifica se o gateway está configurado
     *
     * @return bool
     */
    public function is_configured(): bool;

    /**
     * Retorna o nome amigável do gateway
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Retorna o ID do gateway
     *
     * @return string
     */
    public function get_id(): string;

    /**
     * Retorna os campos de configuração do gateway
     */
    public function get_settings_fields(): array;

    /**
     * Valida as configurações do gateway
     */
    public function validate_settings(array $settings): array;

    /**
     * Testa a conexão com o gateway
     */
    public function test_connection(): array;
}

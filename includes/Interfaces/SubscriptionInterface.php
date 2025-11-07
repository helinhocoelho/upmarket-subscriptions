<?php

namespace UPMarket\Subscriptions\Interfaces;

/**
 * Interface para entidades de Doação
 *
 * @package UPMarket\Subscriptions\Interfaces
 */
interface SubscriptionInterface
{
    /**
     * Retorna o ID da doação
     *
     * @return int
     */
    public function get_id(): int;

    /**
     * Retorna o ID do usuário
     *
     * @return int
     */
    public function get_user_id(): int;

    /**
     * Retorna o ID do plano
     *
     * @return int
     */
    public function get_plan_id(): int;

    /**
     * Retorna o status da doação
     *
     * @return string
     */
    public function get_status(): string;

    /**
     * Define o status da doação
     *
     * @param string $status
     * @return bool
     */
    public function set_status(string $status): bool;

    /**
     * Retorna a data de início
     *
     * @return \DateTime
     */
    public function get_start_date(): \DateTime;

    /**
     * Retorna a próxima data de cobrança
     *
     * @return \DateTime
     */
    public function get_next_billing_date(): \DateTime;

    /**
     * Verifica se a doação está ativa
     *
     * @return bool
     */
    public function is_active(): bool;

    /**
     * Cancela a doação
     *
     * @return bool
     */
    public function cancel(): bool;

    /**
     * Retoma a doação
     *
     * @return bool
     */
    public function resume(): bool;
}

<?php

namespace UPMarket\Subscriptions\Interfaces;

/**
 * Interface para Planos de Doação
 *
 * @package UPMarket\Subscriptions\Interfaces
 */
interface SubscriptionPlanInterface
{
    /**
     * Retorna o ID do plano
     *
     * @return int
     */
    public function get_id(): int;

    /**
     * Retorna o nome do plano
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Retorna a descrição do plano
     *
     * @return string
     */
    public function get_description(): string;

    /**
     * Retorna o preço do plano
     *
     * @return float
     */
    public function get_price(): float;

    /**
     * Retorna o período de cobrança
     *
     * @return string (monthly, yearly, etc)
     */
    public function get_billing_period(): string;

    /**
     * Retorna a frequência do período (ex: 1 para mensal, 3 para trimestral)
     *
     * @return int
     */
    public function get_billing_frequency(): int;

    /**
     * Retorna o período de trial em dias
     *
     * @return int
     */
    public function get_trial_period_days(): int;

    /**
     * Verifica se o plano está ativo
     *
     * @return bool
     */
    public function is_active(): bool;

    /**
     * Retorna os recursos do plano
     *
     * @return array
     */
    public function get_features(): array;

    /**
     * Verifica se o plano tem trial
     *
     * @return bool
     */
    public function has_trial(): bool;
}

<?php

namespace UPMarket\Subscriptions\Abstracts;

use UPMarket\Subscriptions\Interfaces\SubscriptionPlanInterface;

/**
 * Classe abstrata para planos de assinatura
 *
 * @package UPMarket\Subscriptions\Abstracts
 */
abstract class AbstractSubscriptionPlan extends BaseEntity implements SubscriptionPlanInterface
{
    /**
     * Períodos de cobrança disponíveis
     */
    public const PERIOD_DAY = 'day';
    public const PERIOD_WEEK = 'week';
    public const PERIOD_MONTH = 'month';
    public const PERIOD_YEAR = 'year';

    /**
     * @var string Nome do plano
     */
    protected $name = '';

    /**
     * @var string Descrição do plano
     */
    protected $description = '';

    /**
     * @var float Preço do plano
     */
    protected $price = 0.0;

    /**
     * @var string Período de cobrança
     */
    protected $billing_period = self::PERIOD_MONTH;

    /**
     * @var int Frequência do período
     */
    protected $billing_frequency = 1;

    /**
     * @var int Dias de período de trial
     */
    protected $trial_period_days = 0;

    /**
     * @var bool Se o plano está ativo
     */
    protected $is_active = true;

    /**
     * @var array Recursos do plano
     */
    protected $features = [];

    /**
     * Retorna o nome do plano
     *
     * @return string
     */
    public function get_name(): string
    {
        return $this->name;
    }

    /**
     * Retorna a descrição do plano
     *
     * @return string
     */
    public function get_description(): string
    {
        return $this->description;
    }

    /**
     * Retorna o preço do plano
     *
     * @return float
     */
    public function get_price(): float
    {
        return floatval($this->price);
    }

    /**
     * Retorna o período de cobrança
     *
     * @return string
     */
    public function get_billing_period(): string
    {
        return $this->billing_period;
    }

    /**
     * Retorna a frequência do período
     *
     * @return int
     */
    public function get_billing_frequency(): int
    {
        return $this->billing_frequency;
    }

    /**
     * Retorna o período de trial em dias
     *
     * @return int
     */
    public function get_trial_period_days(): int
    {
        return $this->trial_period_days;
    }

    /**
     * Verifica se o plano está ativo
     *
     * @return bool
     */
    public function is_active(): bool
    {
        return $this->is_active;
    }

    /**
     * Retorna os recursos do plano
     *
     * @return array
     */
    public function get_features(): array
    {
        return $this->features;
    }

    /**
     * Verifica se o plano tem trial
     *
     * @return bool
     */
    public function has_trial(): bool
    {
        return $this->trial_period_days > 0;
    }

    /**
     * Calcula o preço formatado
     *
     * @return string
     */
    public function get_formatted_price(): string
    {
        return 'R$ ' . number_format($this->price, 2, ',', '.');
    }

    /**
     * Retorna a descrição do período formatado
     *
     * @return string
     */
    public function get_formatted_period(): string
    {
        $periods = [
            self::PERIOD_DAY => 'dia',
            self::PERIOD_WEEK => 'semana',
            self::PERIOD_MONTH => 'mês',
            self::PERIOD_YEAR => 'ano'
        ];

        $period = $periods[$this->billing_period] ?? $this->billing_period;

        if ($this->billing_frequency > 1) {
            $period = $this->billing_frequency . ' ' . $period . 's';
        }

        return $period;
    }
}

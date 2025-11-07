<?php

namespace UPMarket\Subscriptions\Abstracts;

use UPMarket\Subscriptions\Interfaces\SubscriptionInterface;

/**
 * Classe abstrata para doações
 *
 * @package UPMarket\Subscriptions\Abstracts
 */
abstract class AbstractSubscription extends BaseEntity implements SubscriptionInterface
{
    /**
     * Status disponíveis
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    /**
     * @var int ID do usuário
     */
    protected $user_id = 0;

    /**
     * @var int ID do plano
     */
    protected $plan_id = 0;

    /**
     * @var string Status da doação
     */
    protected $status = self::STATUS_PENDING;

    /**
     * @var \DateTime Data de início
     */
    protected $start_date;

    /**
     * @var \DateTime Próxima data de cobrança
     */
    protected $next_billing_date;

    /**
     * Construtor
     *
     * @param int $id
     */
    public function __construct(int $id = 0)
    {
        $this->start_date = new \DateTime();
        $this->next_billing_date = new \DateTime();
        parent::__construct($id);
    }

    /**
     * Retorna o ID do usuário
     *
     * @return int
     */
    public function get_user_id(): int
    {
        return $this->user_id;
    }

    /**
     * Retorna o ID do plano
     *
     * @return int
     */
    public function get_plan_id(): int
    {
        return $this->plan_id;
    }

    /**
     * Retorna o status da doação
     *
     * @return string
     */
    public function get_status(): string
    {
        return $this->status;
    }

    /**
     * Define o status da doação
     *
     * @param string $status
     * @return bool
     */
    public function set_status(string $status): bool
    {
        $allowed_statuses = [
            self::STATUS_ACTIVE,
            self::STATUS_PAUSED,
            self::STATUS_PENDING,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED
        ];

        if (!in_array($status, $allowed_statuses)) {
            return false;
        }

        $this->status = $status;
        return true;
    }

    /**
     * Retorna a data de início
     *
     * @return \DateTime
     */
    public function get_start_date(): \DateTime
    {
        return $this->start_date;
    }

    /**
     * Retorna a próxima data de cobrança
     *
     * @return \DateTime
     */
    public function get_next_billing_date(): \DateTime
    {
        return $this->next_billing_date;
    }

    /**
     * Verifica se a doação está ativa
     *
     * @return bool
     */
    public function is_active(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Verifica se a doação está pausada
     *
     * @return bool
     */
    public function is_paused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    /**
     * Cancela a doação
     *
     * @return bool
     */
    public function cancel(): bool
    {
        return $this->set_status(self::STATUS_CANCELLED);
    }

    /**
     * Retoma a doação
     *
     * @return bool
     */
    public function resume(): bool
    {
        return $this->set_status(self::STATUS_ACTIVE);
    }

    /**
     * Calcula a próxima data de cobrança
     *
     * @param string $billing_period
     * @param int $billing_frequency
     * @return \DateTime
     */
    protected function calculate_next_billing_date(string $billing_period, int $billing_frequency = 1): \DateTime
    {
        $next_date = clone $this->next_billing_date;

        switch ($billing_period) {
            case 'day':
                $interval = new \DateInterval("P{$billing_frequency}D");
                break;
            case 'month':
                $interval = new \DateInterval("P{$billing_frequency}M");
                break;
            case 'year':
                $interval = new \DateInterval("P{$billing_frequency}Y");
                break;
            default:
                $interval = new \DateInterval('P1M'); // padrão mensal
        }

        return $next_date->add($interval);
    }
}

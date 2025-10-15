<?php

namespace UPMarket\Subscriptions\Entities;

use UPMarket\Subscriptions\Abstracts\AbstractSubscriptionPlan;
use UPMarket\Subscriptions\Core\Logger;

/**
 * Entidade concreta para Planos de Assinatura
 *
 * @package UPMarket\Subscriptions\Entities
 */
class SubscriptionPlan extends AbstractSubscriptionPlan
{
    /**
     * @var string Nome da tabela no banco
     */
    private $table_name;

    /**
     * Construtor
     *
     * @param int $id
     */
    public function __construct(int $id = 0)
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'upmkt_subscription_plans';
        parent::__construct($id);
    }

    /**
     * Carrega os dados do plano
     *
     * @return bool
     */
    protected function load(): bool
    {
        if (!$this->id) {
            return false;
        }

        global $wpdb;

        $plan = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $this->id
            ),
            ARRAY_A
        );

        if (!$plan) {
            return false;
        }

        $this->name = $plan['name'];
        $this->description = $plan['description'];
        $this->price = floatval($plan['price']);
        $this->billing_period = $plan['billing_period'];
        $this->billing_frequency = intval($plan['billing_frequency']);
        $this->trial_period_days = intval($plan['trial_period_days']);
        $this->is_active = boolval($plan['is_active']);

        // Carrega features
        $this->features = maybe_unserialize($plan['features']) ?: [];

        Logger::instance()->debug("Subscription plan {$this->id} loaded", 'entities');

        return true;
    }

    /**
     * Salva o plano
     *
     * @return bool
     */
    public function save(): bool
    {
        global $wpdb;

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'billing_period' => $this->billing_period,
            'billing_frequency' => $this->billing_frequency,
            'trial_period_days' => $this->trial_period_days,
            'is_active' => $this->is_active ? 1 : 0,
            'features' => maybe_serialize($this->features),
            'updated_at' => current_time('mysql')
        ];

        if ($this->id) {
            // Update
            $result = $wpdb->update(
                $this->table_name,
                $data,
                ['id' => $this->id],
                ['%s', '%s', '%f', '%s', '%d', '%d', '%d', '%s', '%s'],
                ['%d']
            );

            if (false === $result) {
                Logger::instance()->error("Failed to update plan {$this->id}: " . $wpdb->last_error, 'entities');
                return false;
            }
        } else {
            // Insert
            $data['created_at'] = current_time('mysql');

            $result = $wpdb->insert(
                $this->table_name,
                $data,
                ['%s', '%s', '%f', '%s', '%d', '%d', '%d', '%s', '%s', '%s']
            );

            if ($result) {
                $this->id = $wpdb->insert_id;
            } else {
                Logger::instance()->error("Failed to create plan: " . $wpdb->last_error, 'entities');
                return false;
            }
        }

        Logger::instance()->debug("Subscription plan {$this->id} saved", 'entities');

        return true;
    }

    /**
     * Cria um novo plano
     *
     * @param string $name
     * @param float $price
     * @param string $billing_period
     * @param array $args
     * @return SubscriptionPlan|null
     */
    public static function create(string $name, float $price, string $billing_period = self::PERIOD_MONTH, array $args = []): ?SubscriptionPlan
    {
        $plan = new self();
        $plan->name = $name;
        $plan->price = $price;
        $plan->billing_period = $billing_period;
        $plan->billing_frequency = $args['billing_frequency'] ?? 1;
        $plan->description = $args['description'] ?? '';
        $plan->trial_period_days = $args['trial_period_days'] ?? 0;
        $plan->is_active = $args['is_active'] ?? true;
        $plan->features = $args['features'] ?? [];

        if ($plan->save()) {
            return $plan;
        }

        return null;
    }

    /**
     * Retorna todos os planos ativos
     *
     * @return array
     */
    public static function get_active_plans(): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'upmkt_subscription_plans';
        $plans_data = $wpdb->get_results(
            "SELECT id FROM {$table_name} WHERE is_active = 1 ORDER BY price ASC",
            ARRAY_A
        );

        $plans = [];
        foreach ($plans_data as $plan_data) {
            $plan = new self($plan_data['id']);
            if ($plan->exists()) {
                $plans[] = $plan;
            }
        }

        return $plans;
    }
}

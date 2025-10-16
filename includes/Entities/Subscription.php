<?php

namespace UPMarket\Subscriptions\Entities;

use UPMarket\Subscriptions\Abstracts\AbstractSubscription;
use UPMarket\Subscriptions\Core\Logger;

/**
 * Entidade concreta para Assinaturas
 *
 * @package UPMarket\Subscriptions\Entities
 */
class Subscription extends AbstractSubscription
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
        $this->table_name = $wpdb->prefix . 'upmkt_subscriptions';
        parent::__construct($id);
    }

    /**
     * Carrega os dados da assinatura
     *
     * @return bool
     */
    protected function load(): bool
    {
        if (!$this->id) {
            return false;
        }

        global $wpdb;

        $subscription = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $this->id
            ),
            ARRAY_A
        );

        if (!$subscription) {
            return false;
        }

        $this->user_id = intval($subscription['user_id']);
        $this->plan_id = intval($subscription['plan_id']);
        $this->status = $subscription['status'];
        $this->start_date = new \DateTime($subscription['start_date']);
        $this->next_billing_date = new \DateTime($subscription['next_billing_date']);

        // Carrega metadados
        $this->load_meta();

        Logger::instance()->debug("Subscription {$this->id} loaded", 'entities');

        return true;
    }

    /**
     * Carrega os metadados da assinatura
     */
    private function load_meta(): void
    {
        global $wpdb;

        $meta_table = $wpdb->prefix . 'upmkt_subscription_meta';
        $metas = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$meta_table} WHERE subscription_id = %d",
                $this->id
            ),
            ARRAY_A
        );

        foreach ($metas as $meta) {
            $this->meta[$meta['meta_key']] = maybe_unserialize($meta['meta_value']);
        }
    }

    /**
     * Salva a assinatura
     *
     * @return bool
     */
    public function save(): bool
    {
        global $wpdb;

        $data = [
            'user_id' => $this->user_id,
            'plan_id' => $this->plan_id,
            'status' => $this->status,
            'start_date' => $this->start_date->format('Y-m-d H:i:s'),
            'next_billing_date' => $this->next_billing_date->format('Y-m-d H:i:s'),
            'updated_at' => current_time('mysql')
        ];

        if ($this->id) {
            // Update
            $result = $wpdb->update(
                $this->table_name,
                $data,
                ['id' => $this->id],
                ['%d', '%d', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            if (false === $result) {
                Logger::instance()->error("Failed to update subscription {$this->id}: " . $wpdb->last_error, 'entities');
                return false;
            }
        } else {
            // Insert
            $data['created_at'] = current_time('mysql');

            $result = $wpdb->insert(
                $this->table_name,
                $data,
                ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
            );

            if ($result) {
                $this->id = $wpdb->insert_id;
            } else {
                Logger::instance()->error("Failed to create subscription: " . $wpdb->last_error, 'entities');
                return false;
            }
        }

        // Salva metadados
        $this->save_meta();

        Logger::instance()->debug("Subscription {$this->id} saved", 'entities');

        return true;
    }

    /**
     * Salva os metadados
     */
    private function save_meta(): void
    {
        global $wpdb;

        $meta_table = $wpdb->prefix . 'upmkt_subscription_meta';

        // Remove metadados antigos
        $wpdb->delete(
            $meta_table,
            ['subscription_id' => $this->id],
            ['%d']
        );

        // Insere novos metadados
        foreach ($this->meta as $key => $value) {
            $wpdb->insert(
                $meta_table,
                [
                    'subscription_id' => $this->id,
                    'meta_key' => $key,
                    'meta_value' => maybe_serialize($value)
                ],
                ['%d', '%s', '%s']
            );
        }
    }

    /**
     * Cria uma nova assinatura
     *
     * @param int $user_id
     * @param int $plan_id
     * @param array $args
     * @return Subscription|null
     */
    public static function create(int $user_id, int $plan_id, array $args = []): ?Subscription
    {
        $subscription = new self();
        $subscription->user_id = $user_id;
        $subscription->plan_id = $plan_id;
        $subscription->status = self::STATUS_PENDING;
        $subscription->start_date = new \DateTime($args['start_date'] ?? current_time('mysql'));
        $subscription->next_billing_date = new \DateTime($args['next_billing_date'] ?? current_time('mysql'));

        // Metadados adicionais
        if (!empty($args['meta'])) {
            foreach ($args['meta'] as $key => $value) {
                $subscription->set_meta($key, $value);
            }
        }

        if ($subscription->save()) {
            return $subscription;
        }

        return null;
    }

    /**
     * Verifica se a assinatura existe
     */
    public function exists(): bool
    {
        return $this->id > 0 && !empty($this->status);
    }

    /**
     * Define o ID do plano
     */
    public function set_plan_id(int $plan_id): void
    {
        $this->plan_id = $plan_id;
    }

    /**
     * Define a data de início
     */
    public function set_start_date(\DateTime $date): void
    {
        $this->start_date = $date;
    }

    /**
     * Define a próxima data de cobrança
     */
    public function set_next_billing_date(\DateTime $date): void
    {
        $this->next_billing_date = $date;
    }

    /**
     * Obtém metadado
     */
    public function get_meta(string $key, $default = null)
    {
        return $this->meta[$key] ?? $default;
    }

    /**
     * Define metadado
     */
    public function set_meta(string $key, $value): void
    {
        $this->meta[$key] = $value;
    }

}

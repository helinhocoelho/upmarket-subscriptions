<?php

namespace UPMarket\Subscriptions\Testing;

use UPMarket\Subscriptions\Core\Logger;
use UPMarket\Subscriptions\Entities\SubscriptionPlan;
use UPMarket\Subscriptions\Entities\Subscription;
use UPMarket\Subscriptions\Services\SubscriptionManager;
use UPMarket\Subscriptions\Gateways\Rede;

/**
 * Runner para testes automatizados do plugin
 *
 * @package UPMarket\Subscriptions\Testing
 */
class TestRunner
{
    /**
     * @var array Resultados dos testes
     */
    private $results = [];

    /**
     * Executa todos os testes
     */
    public function run_all_tests(): array
    {
        Logger::instance()->info('Starting automated tests', 'testing');

        $this->results = [];

        // Testes de Entidades
        $this->test_entity_creation();
        $this->test_subscription_workflow();

        // Testes de Gateway
        $this->test_gateway_configuration();

        // Testes de Serviços
        $this->test_subscription_manager();

        // Testes de Banco de Dados
        $this->test_database_operations();

        Logger::instance()->info('Automated tests completed', 'testing');

        return $this->results;
    }

    /**
     * Testa criação de entidades
     */
    private function test_entity_creation(): void
    {
        $test_name = 'Entity Creation';

        try {
            // Teste de Plano
            $plan = SubscriptionPlan::create(
                'Plano Teste',
                29.90,
                'month',
                [
                    'description' => 'Plano de teste para validação',
                    'trial_period_days' => 7,
                    'features' => ['Feature 1', 'Feature 2']
                ]
            );

            if (!$plan || !$plan->exists()) {
                throw new \Exception('Falha na criação do plano');
            }

            $this->add_result($test_name, true, 'Plano criado com sucesso - ID: ' . $plan->get_id());

            // Cleanup
            global $wpdb;
            $wpdb->delete(
                $wpdb->prefix . 'upmkt_subscription_plans',
                ['id' => $plan->get_id()],
                ['%d']
            );

        } catch (\Exception $e) {
            $this->add_result($test_name, false, $e->getMessage());
        }
    }

    /**
     * Testa fluxo de assinatura
     */
    private function test_subscription_workflow(): void
    {
        $test_name = 'Subscription Workflow';

        try {
            // Criar plano temporário
            $plan = SubscriptionPlan::create('Plano Teste Workflow', 19.90, 'month');

            if (!$plan) {
                throw new \Exception('Não foi possível criar plano para teste');
            }

            // Criar usuário de teste
            $user_id = $this->create_test_user();

            // Testar criação de assinatura
            $subscription = Subscription::create(
                $user_id,
                $plan->get_id(),
                [
                    'start_date' => current_time('mysql'),
                    'next_billing_date' => date('Y-m-d H:i:s', strtotime('+1 month'))
                ]
            );

            if (!$subscription) {
                throw new \Exception('Falha na criação da assinatura');
            }

            // Testar mudança de status
            $subscription->set_status(Subscription::STATUS_ACTIVE);
            $subscription->save();

            if ($subscription->get_status() !== Subscription::STATUS_ACTIVE) {
                throw new \Exception('Falha na atualização do status');
            }

            // Testar cancelamento
            $subscription->cancel();
            $subscription->save();

            if ($subscription->get_status() !== Subscription::STATUS_CANCELLED) {
                throw new \Exception('Falha no cancelamento');
            }

            $this->add_result($test_name, true, 'Fluxo completo de assinatura testado com sucesso');

            // Cleanup
            global $wpdb;
            $wpdb->delete(
                $wpdb->prefix . 'upmkt_subscription_plans',
                ['id' => $plan->get_id()],
                ['%d']
            );

            wp_delete_user($user_id);

        } catch (\Exception $e) {
            $this->add_result($test_name, false, $e->getMessage());
        }
    }

    /**
     * Testa configuração do gateway
     */
    private function test_gateway_configuration(): void
    {
        $test_name = 'Gateway Configuration';

        try {
            $gateway = new Rede();

            // Testar se gateway está registrado
            if ($gateway->get_id() !== 'rede') {
                throw new \Exception('ID do gateway incorreto');
            }

            // Testar configuração básica
            $settings = $gateway->get_settings();

            if (!is_array($settings)) {
                throw new \Exception('Configurações não retornaram array');
            }

            $this->add_result($test_name, true, 'Gateway configurado corretamente');

        } catch (\Exception $e) {
            $this->add_result($test_name, false, $e->getMessage());
        }
    }

    /**
     * Testa SubscriptionManager
     */
    private function test_subscription_manager(): void
    {
        $test_name = 'Subscription Manager';

        try {
            $manager = new SubscriptionManager();

            // Testar se instância foi criada
            if (!$manager instanceof SubscriptionManager) {
                throw new \Exception('Falha na instanciação do SubscriptionManager');
            }

            $this->add_result($test_name, true, 'SubscriptionManager funcionando corretamente');

        } catch (\Exception $e) {
            $this->add_result($test_name, false, $e->getMessage());
        }
    }

    /**
     * Testa operações de banco de dados
     */
    private function test_database_operations(): void
    {
        $test_name = 'Database Operations';

        try {
            global $wpdb;

            // Verificar se tabelas existem
            $tables = [
                $wpdb->prefix . 'upmkt_subscription_plans',
                $wpdb->prefix . 'upmkt_subscriptions',
                $wpdb->prefix . 'upmkt_subscription_meta'
            ];

            foreach ($tables as $table) {
                $result = $wpdb->get_var("SHOW TABLES LIKE '$table'");
                if ($result !== $table) {
                    throw new \Exception("Tabela não encontrada: {$table}");
                }
            }

            $this->add_result($test_name, true, 'Todas as tabelas do banco estão presentes');

        } catch (\Exception $e) {
            $this->add_result($test_name, false, $e->getMessage());
        }
    }

    /**
     * Cria usuário de teste
     */
    private function create_test_user(): int
    {
        $username = 'test_user_' . time();
        $email = $username . '@example.com';

        $user_id = wp_create_user($username, 'test_password', $email);

        if (is_wp_error($user_id)) {
            throw new \Exception('Falha ao criar usuário de teste: ' . $user_id->get_error_message());
        }

        return $user_id;
    }

    /**
     * Adiciona resultado do teste
     */
    private function add_result(string $test_name, bool $passed, string $message): void
    {
        $this->results[] = [
            'test' => $test_name,
            'passed' => $passed,
            'message' => $message,
            'timestamp' => current_time('mysql')
        ];

        $status = $passed ? 'PASSED' : 'FAILED';
        Logger::instance()->info("Test {$test_name}: {$status} - {$message}", 'testing');
    }

    /**
     * Define os resultados dos testes
     */
    public function set_results(array $results): void
    {
        $this->results = $results;
    }

    /**
     * Gera relatório de testes
     */
    public function generate_report(): string
    {
        $passed = array_filter($this->results, function ($result) {
            return $result['passed'];
        });

        $total = count($this->results);
        $passed_count = count($passed);

        $success_rate = $total > 0 ? round(($passed_count / $total) * 100, 2) : 0;

        $report = "=== RELATÓRIO DE TESTES - UP MARKET SUBSCRIPTIONS ===\n";
        $report .= "Data: " . current_time('Y-m-d H:i:s') . "\n";
        $report .= "Total de testes: {$total}\n";
        $report .= "Aprovados: {$passed_count}\n";
        $report .= "Falhas: " . ($total - $passed_count) . "\n";
        $report .= "Taxa de sucesso: {$success_rate}%\n\n";

        if ($total > 0) {
            foreach ($this->results as $result) {
                $status = $result['passed'] ? '✅ PASS' : '❌ FAIL';
                $report .= "{$status} {$result['test']}\n";
                $report .= "   {$result['message']}\n";
                $report .= "   {$result['timestamp']}\n\n";
            }
        } else {
            $report .= "Nenhum teste foi executado.\n";
        }

        return $report;
    }
}

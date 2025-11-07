<?php

namespace UPMarket\Subscriptions\Gateways;

use UPMarket\Subscriptions\Abstracts\AbstractPaymentGateway;
use UPMarket\Subscriptions\Interfaces\SubscriptionInterface;
use UPMarket\Subscriptions\Core\Logger;

/**
 * Gateway de pagamento da Rede
 */
class Rede extends AbstractPaymentGateway
{
    private $api_url;
    private $pv;
    private $token;
    private $sandbox;

    public function __construct()
    {
        $this->id = 'rede';
        $this->name = 'Rede';
        $this->init_settings();

        $this->setup_api_config();
        parent::__construct();
    }

    /**
     * Configura API baseado nas settings
     */
    private function setup_api_config(): void
    {
        $this->sandbox = $this->get_setting('environment') === 'sandbox';
        $this->api_url = $this->sandbox
            ? 'https://sandbox-erede.useredecloud.com.br' // Sandbox
            : 'https://api.userede.com.br'; // Produção
        $this->pv = $this->get_setting('pv');
        $this->token = $this->get_setting('token');
    }

    /**
     * =========================================================================
     * MÉTODOS OBRIGATÓRIOS DA INTERFACE
     * =========================================================================
     */

    /**
     * Processa um pagamento inicial
     */
    public function process_initial_payment(array $payment_data, SubscriptionInterface $subscription): array
    {
        Logger::instance()->info(
            "Processing initial payment for subscription {$subscription->get_id()}",
            'gateways'
        );

        $validation_errors = $this->validate_payment_data($payment_data);
        if (!empty($validation_errors)) {
            return $this->error_response($validation_errors);
        }

        try {
            $transaction_data = $this->prepare_transaction_data($payment_data, $subscription);
            $response = $this->create_transaction($transaction_data);

            if ($response['success']) {
                $this->save_card_token($subscription, $response['card_token'] ?? '');
                Logger::instance()->info(
                    "Initial payment successful for subscription {$subscription->get_id()}",
                    'gateways'
                );
                return $this->success_response($response);
            }

            Logger::instance()->error(
                "Initial payment failed for subscription {$subscription->get_id()}: " .
                ($response['message'] ?? 'Unknown error'),
                'gateways'
            );
            return $this->error_response([$response['message'] ?? 'Erro ao processar pagamento']);

        } catch (\Exception $e) {
            Logger::instance()->error("Rede gateway error: " . $e->getMessage(), 'gateways');
            return $this->error_response(['Erro ao processar pagamento: ' . $e->getMessage()]);
        }
    }

    /**
     * Processa um pagamento recorrente
     */
    public function process_recurring_payment(SubscriptionInterface $subscription): array
    {
        Logger::instance()->info(
            "Processing recurring payment for subscription {$subscription->get_id()}",
            'gateways'
        );

        $card_token = $subscription->get_meta('rede_card_token');
        if (empty($card_token)) {
            return $this->error_response(['Token do cartão não encontrado para cobrança recorrente']);
        }

        try {
            $amount = $this->get_subscription_amount($subscription);
            $transaction_data = $this->prepare_recurring_transaction_data($subscription, $amount, $card_token);
            $response = $this->create_transaction($transaction_data);

            if ($response['success']) {
                Logger::instance()->info(
                    "Recurring payment successful for subscription {$subscription->get_id()}",
                    'gateways'
                );
                return $this->success_response($response);
            }

            Logger::instance()->warning(
                "Recurring payment failed for subscription {$subscription->get_id()}: " .
                ($response['message'] ?? 'Unknown error'),
                'gateways'
            );
            return $this->error_response([$response['message'] ?? 'Erro ao processar cobrança recorrente']);

        } catch (\Exception $e) {
            Logger::instance()->error("Rede recurring payment error: " . $e->getMessage(), 'gateways');
            return $this->error_response(['Erro ao processar cobrança recorrente: ' . $e->getMessage()]);
        }
    }

    /**
     * Cancela uma doação no gateway
         * Transações futuras não serão processadas
     */
    public function cancel_subscription(SubscriptionInterface $subscription): bool
    {
        Logger::instance()->info(
            "Canceling subscription {$subscription->get_id()} in Rede",
            'gateways'
        );

        return true;
    }

    /**
     * Verifica o status de um pagamento
     */
    public function check_payment_status(string $transaction_id): array
    {
        try {
            $response = $this->get_transaction_status($transaction_id);
            return [
                'success' => true,
                'status' => $response['status'],
                'message' => $response['message'] ?? 'Status verificado'
            ];
        } catch (\Exception $e) {
            Logger::instance()->error("Rede status check error: " . $e->getMessage(), 'gateways');
            return $this->error_response(['Erro ao verificar status: ' . $e->getMessage()]);
        }
    }

    /**
     * Processa webhooks da Rede
     */
    public function process_webhook(array $webhook_data = []): void
    {
        // Usa dados do sistema dinâmico
        $input = $webhook_data['body'] ?? file_get_contents('php://input');
        $headers = $webhook_data['headers'] ?? [];

        $data = json_decode($input, true);

        Logger::instance()->info('Rede webhook received: ' . $input, 'webhooks');

        if (empty($data)) {
            status_header(400);
            exit;
        }

        try {
            if (!$this->verify_webhook_signature($headers)) {
                Logger::instance()->error('Invalid webhook signature', 'webhooks');
                status_header(401);
                exit;
            }

            $this->handle_webhook_notification($data);
            status_header(200);
            echo 'OK';

        } catch (\Exception $e) {
            Logger::instance()->error('Webhook processing error: ' . $e->getMessage(), 'webhooks');
            status_header(500);
        }

        exit;
    }

    /**
     * Retorna as configurações do gateway
     */
    public function get_settings(): array
    {
        return $this->settings;
    }

    /**
     * Verifica se o gateway está configurado
     */
    public function is_configured(): bool
    {
        return !empty($this->pv) && !empty($this->token);
    }

    /**
     * Retorna o nome amigável do gateway
     */
    public function get_name(): string
    {
        return $this->name;
    }

    /**
     * Retorna o ID do gateway
     */
    public function get_id(): string
    {
        return $this->id;
    }

    /**
     * Retorna os campos de configuração do gateway
     */
    public function get_settings_fields(): array
    {
        $parent_fields = parent::get_settings_fields();

        $rede_fields = [
            'environment' => [
                'title' => 'Ambiente',
                'type' => 'select',
                'options' => [
                    'sandbox' => 'Sandbox (Testes)',
                    'production' => 'Produção'
                ],
                'default' => 'sandbox',
                'description' => 'Use Sandbox para testes e Produção para ambiente real.'
            ],
            'pv' => [
                'title' => 'PV',
                'type' => 'text',
                'default' => '',
                'description' => 'Número do PV fornecido pela Rede.',
                'class' => 'regular-text',
                'required' => true
            ],
            'token' => [
                'title' => 'Token',
                'type' => 'password',
                'default' => '',
                'description' => 'Token de autenticação fornecido pela Rede.',
                'class' => 'regular-text',
                'required' => true
            ]
        ];

        // Insere campos específicos após "enabled"
        $final_fields = [];
        foreach ($parent_fields as $key => $field) {
            $final_fields[$key] = $field;
            if ($key === 'enabled') {
                $final_fields = array_merge($final_fields, $rede_fields);
            }
        }

        return $final_fields;
    }

    /**
     * Valida as configurações do gateway
     */
    public function validate_settings(array $settings): array
    {
        $errors = [];

        if (!empty($settings['enabled']) && $settings['enabled'] === 'yes') {
            if (empty($settings['pv'])) {
                $errors[] = 'PV é obrigatório.';
            }

            if (empty($settings['token'])) {
                $errors[] = 'Token é obrigatório.';
            }

            if (!empty($settings['pv']) && !preg_match('/^\d+$/', $settings['pv'])) {
                $errors[] = 'PV deve conter apenas números.';
            }
        }

        return $errors;
    }

    /**
     * Teste a conexão com o gateway
     */
    public function test_connection(): array
    {
        try {
            $test_url = $this->api_url . '/v1/transactions?reference=g241025104139038';

            $response = wp_remote_get($test_url, [
                'headers' => $this->get_api_headers(),
                'timeout' => 15
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $status_code = wp_remote_retrieve_response_code($response);

            if (in_array($status_code, [200, 404])) {
                return [
                    'success' => true,
                    'message' => 'Conexão com a API da Rede (Sandbox) estabelecida com sucesso! Código: ' . $status_code
                ];
            }

            $body = wp_remote_retrieve_body($response);
            return [
                'success' => false,
                'message' => "Erro na conexão: Código {$status_code} — {$body}"
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Falha na conexão: ' . $e->getMessage()
            ];
        }
    }


    /**
     * =========================================================================
     * MÉTODOS AUXILIARES
     * =========================================================================
     */

    /**
     * Prepara dados da transação inicial
     */
    private function prepare_transaction_data(array $payment_data, SubscriptionInterface $subscription): array
    {
        $amount = $this->get_subscription_amount($subscription);
        $reference = substr('subs'.$subscription->get_id().'date'.upmkt_get_current_timestamp(), 0, 50);

        return [
            'capture' => true,
            'kind' => 'credit',
            'reference' => $reference,
            'amount' => (int)($amount * 100),
            'installments' => 1,
            'cardholderName' => $payment_data['card_holder'],
            'cardNumber' => preg_replace('/\s+/', '', $payment_data['card_number']),
            'expirationMonth' => substr($payment_data['card_expiry'], 0, 2),
            'expirationYear' => '20' . substr($payment_data['card_expiry'], 3, 2),
            'securityCode' => $payment_data['card_cvv'],
            'subscription' => true,
            'origin' => 1,
            'softDescriptor' => 'UP Market Sub'
        ];
    }

    /**
     * Prepara dados para transação recorrente
     */
    private function prepare_recurring_transaction_data(SubscriptionInterface $subscription, float $amount, string $card_token): array
    {
        $reference = substr('subs'.$subscription->get_id().'date'.upmkt_get_current_timestamp(), 0, 50);

        return [
            'capture' => true,
            'kind' => 'credit',
            'reference' => $reference,
            'amount' => (int)($amount * 100),
            'currency' => 'BRL',
            'cardToken' => $card_token,
            'subscription' => [
                'subscriptionId' => (string)$subscription->get_id()
            ]
        ];
    }

    /**
     * Obtém valor da doação
     */
    private function get_subscription_amount(SubscriptionInterface $subscription): float
    {
        $plan = new \UPMarket\Subscriptions\Entities\SubscriptionPlan($subscription->get_plan_id());
        return $plan->exists() ? $plan->get_price() : 0;
    }

    /**
     * Salva token do cartão para cobranças futuras
     */
    private function save_card_token(SubscriptionInterface $subscription, string $card_token): void
    {
        if (!empty($card_token)) {
            $subscription->set_meta('rede_card_token', $card_token);
            $subscription->save();
        }
    }

    /**
     * Resposta de sucesso padronizada
     */
    private function success_response(array $response): array
    {
        return [
            'success' => true,
            'transaction_id' => $response['transaction_id'],
            'message' => $response['message'] ?? 'Operação realizada com sucesso'
        ];
    }

    /**
     * Resposta de erro padronizada
     */
    private function error_response(array $errors): array
    {
        return [
            'success' => false,
            'errors' => $errors
        ];
    }

    /**
     * Validação de dados de pagamento — Cartão de crédito (classe derivada)
     */
    protected function validate_payment_data(array $payment_data): array
    {
        // Herdando as validações básicas da classe pai (amount e currency)
        $errors = parent::validate_payment_data($payment_data);

        // Número do cartão
        if (empty($payment_data['card_number'])) {
            $errors[] = __('Número do cartão é obrigatório.', 'upmarket-subscriptions');
        } else {
            $card_number = preg_replace('/\s+/', '', $payment_data['card_number']);

            if (!preg_match('/^\d{13,19}$/', $card_number)) {
                $errors[] = __('Número do cartão inválido.', 'upmarket-subscriptions');
            } elseif (!$this->sandbox && !$this->is_valid_luhn($card_number)) {
                $errors[] = __('Número do cartão não passou na validação Luhn.', 'upmarket-subscriptions');
            }
        }

        // Data de validade
        if (empty($payment_data['card_expiry'])) {
            $errors[] = __('Data de validade é obrigatória.', 'upmarket-subscriptions');
        } elseif (!preg_match('/^\d{2}\/\d{2}$/', $payment_data['card_expiry'])) {
            $errors[] = __('Formato da validade inválido. Use MM/AA.', 'upmarket-subscriptions');
        } else {
            [$month, $year] = explode('/', $payment_data['card_expiry']);
            $month = (int)$month;
            $year = (int)('20' . $year);

            if ($month < 1 || $month > 12) {
                $errors[] = __('Mês de validade inválido.', 'upmarket-subscriptions');
            } else {
                $expiry = (new \DateTime())->setDate($year, $month, 1)->modify('last day of this month');
                $now = new \DateTime();
                if ($expiry < $now) {
                    $errors[] = __('O cartão informado está expirado.', 'upmarket-subscriptions');
                }
            }
        }

        // CVV
        if (empty($payment_data['card_cvv'])) {
            $errors[] = __('CVV é obrigatório.', 'upmarket-subscriptions');
        } elseif (!preg_match('/^\d{3,4}$/', $payment_data['card_cvv'])) {
            $errors[] = __('CVV inválido.', 'upmarket-subscriptions');
        }

        // Nome do titular
        if (empty($payment_data['card_holder'])) {
            $errors[] = __('Nome do titular do cartão é obrigatório.', 'upmarket-subscriptions');
        } elseif (strlen($payment_data['card_holder']) < 3) {
            $errors[] = __('Nome do titular muito curto.', 'upmarket-subscriptions');
        }

        return $errors;
    }

    /**
     * Validação Luhn — garante que o número do cartão é matematicamente válido.
     */
    private function is_valid_luhn(string $number): bool
    {
        $sum = 0;
        $alt = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int)$number[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = !$alt;
        }

        return $sum % 10 === 0;
    }

    /**
     * =========================================================================
     * API METHODS
     * =========================================================================
     */

    /**
     * Cria transação na Rede
     */
    private function create_transaction(array $data): array
    {
        $url = $this->api_url . '/v1/transactions';

        $response = wp_remote_post($url, [
            'headers' => $this->get_api_headers(),
            'body' => json_encode($data),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        return $this->parse_api_response($response);
    }

    /**
     * Busca status da transação
     */
    private function get_transaction_status(string $transaction_id): array
    {
        $url = $this->api_url . '/v1/transactions/' . $transaction_id;

        $response = wp_remote_get($url, [
            'headers' => $this->get_api_headers(),
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        return [
            'status' => $result['authorization']['status'] ?? 'unknown',
            'message' => $result['returnMessage'] ?? ''
        ];
    }

    /**
     * Headers padrão para API
     */
    private function get_api_headers(): array
    {
        $authorization = 'Basic ' . base64_encode($this->pv . ':' . $this->token);

        return [
            'Authorization' => $authorization,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'User-Agent'    => 'UP Market Subscriptions/' . UPMKT_VERSION
        ];
    }

    /**
     * Parse da resposta da API
     */
    private function parse_api_response($response): array
    {
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        $status_code = wp_remote_retrieve_response_code($response);

        Logger::instance()->debug('Rede API Response: ' . $body, 'gateways');

        if ($status_code === 200 || $status_code === 201) {
            return [
                'success' => true,
                'transaction_id' => $result['tid'] ?? '',
                'card_token' => $result['cardToken'] ?? '',
                'message' => $result['returnMessage'] ?? 'Transação criada com sucesso'
            ];
        }

        return [
            'success' => false,
            'message' => $result['returnMessage'] ?? 'Erro na transação',
            'error_code' => $result['returnCode'] ?? ''
        ];
    }

    /**
     * =========================================================================
     * WEBHOOK HANDLERS ATUALIZADOS
     * =========================================================================
     */

    /**
     * CORREÇÃO: Verificação de doação com headers dinâmicos
     */
    private function verify_webhook_signature(array $headers = []): bool
    {
        // TODO: Implementar verificação de doação se necessário
        // Agora recebe headers do sistema dinâmico
        Logger::instance()->info("Webhook signature verification for Rede", 'webhooks');
        return true;
    }

    /**
     * CORREÇÃO: Handler principal de webhook
     */
    private function handle_webhook_notification(array $data): void
    {
        $type = $data['event'] ?? '';
        $transaction_id = $data['transaction']['tid'] ?? '';

        $handlers = [
            'transaction_approved' => 'handle_transaction_approved',
            'transaction_denied' => 'handle_transaction_denied',
            'transaction_captured' => 'handle_transaction_captured'
        ];

        if (isset($handlers[$type])) {
            $this->{$handlers[$type]}($data);

            // NOVO: Dispara evento estruturado para o sistema dinâmico
            $this->handle_webhook_event($type, $data);
        } else {
            Logger::instance()->info("Unhandled webhook event: {$type}", 'webhooks');
        }
    }

    /**
     * NOVO: Integração com sistema dinâmico de webhooks
     */
    private function handle_transaction_approved(array $data): void
    {
        $transaction_id = $data['transaction']['tid'] ?? '';
        $reference = $data['transaction']['reference'] ?? '';

        Logger::instance()->info("Transaction approved: {$transaction_id}", 'webhooks');

        // Mantém compatibilidade com handlers existentes
        do_action('upmkt_rede_transaction_approved', $transaction_id, $reference, $data);
    }

    private function handle_transaction_denied(array $data): void
    {
        $transaction_id = $data['transaction']['tid'] ?? '';
        $reference = $data['transaction']['reference'] ?? '';

        Logger::instance()->warning("Transaction denied: {$transaction_id}", 'webhooks');
        do_action('upmkt_rede_transaction_denied', $transaction_id, $reference, $data);
    }

    private function handle_transaction_captured(array $data): void
    {
        $transaction_id = $data['transaction']['tid'] ?? '';
        Logger::instance()->info("Transaction captured: {$transaction_id}", 'webhooks');
        do_action('upmkt_rede_transaction_captured', $transaction_id, $data);
    }
}

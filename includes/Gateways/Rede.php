<?php

namespace UPMarket\Subscriptions\Gateways;

use UPMarket\Subscriptions\Abstracts\AbstractPaymentGateway;
use UPMarket\Subscriptions\Interfaces\SubscriptionInterface;
use UPMarket\Subscriptions\Core\Logger;
use UPMarket\Subscriptions\Services\OAuthService;

/**
 * Gateway de pagamento da Rede (API v2 com OAuth 2.0)
 */
class Rede extends AbstractPaymentGateway
{
    private $api_url;
    private $pv;
    private $token;
    private $sandbox;
    private $oauth_service;

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
            ? 'https://sandbox-erede.useredecloud.com.br' // Sandbox v2
            : 'https://api.userede.com.br/erede'; // Produção v2
        $this->pv = $this->get_setting('pv');
        $this->token = $this->get_setting('token');

        // Inicializa serviço OAuth
        $this->oauth_service = new OAuthService($this->pv, $this->token, $this->sandbox);
    }

    /**
     * =========================================================================
     * MÉTODOS OBRIGATÓRIOS DA INTERFACE
     * =========================================================================
     */

    /**
     * Processa um pagamento inicial com tokenização
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
            // ⭐ 1. PRIMEIRO: TOKENIZAR O CARTÃO (Cofre de Cartões)
            $tokenization_result = $this->tokenize_card($payment_data);

            if (!$tokenization_result['success']) {
                return $this->error_response(['Falha ao tokenizar cartão: ' . $tokenization_result['message']]);
            }

            $card_token = $tokenization_result['tokenization_id'];

            // ⭐ 2. SEGUNDO: FAZER TRANSAÇÃO COM O TOKEN
            $transaction_data = $this->prepare_transaction_data($subscription, $card_token);
            $response = $this->create_transaction($transaction_data);

            if ($response['success']) {
                // ⭐ 3. SALVAR TOKEN PARA RECORRÊNCIAS
                $subscription->set_meta('rede_card_token', $card_token);
                $subscription->set_meta('card_last_four', substr($payment_data['card_number'], -4));
                $subscription->set_meta('gateway_transaction_id', $response['transaction_id']);

                $subscription->save();

                Logger::instance()->info(
                    "Initial payment successful - Token: {$card_token} for subscription {$subscription->get_id()}",
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
     * Processa um pagamento recorrente com token
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
            $transaction_data = $this->prepare_transaction_data($subscription, $card_token);
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
     * Busca status da assinatura no gateway
     */
    public function get_subscription_status(string $transaction_id): array
    {
        try {
            $response = $this->get_transaction_status($transaction_id);

            $gateway_status = $response['status'] ?? 'unknown';

            // Mapear status da Rede para status do plugin
            $status_mapping = [
                'approved' => 'active',
                'denied' => 'cancelled',
                'canceled' => 'cancelled',
                'pending' => 'pending',
                'confirmed' => 'active'
            ];

            $local_status = $status_mapping[$gateway_status] ?? $gateway_status;

            return [
                'success' => true,
                'data' => [
                    'status' => $local_status,
                    'gateway_status' => $gateway_status,
                    'message' => $response['message'] ?? '',
                    'transaction_id' => $transaction_id
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro na comunicação com a Rede: ' . $e->getMessage()
            ];
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
     * =========================================================================
     * MÉTODOS DE TOKENIZAÇÃO
     * =========================================================================
     */

    /**
     * Tokeniza o cartão na Rede (Cofre de Cartões)
     */
    private function tokenize_card(array $payment_data): array
    {
        $url = 'https://rl7-sandbox-api.useredecloud.com.br/token-service/oauth/v2/tokenization';

        $customer_email = $payment_data['customer_email'] ?? '';
        if (empty($customer_email)) {
            $user = wp_get_current_user();
            $customer_email = $user->user_email;
        }

        $card_data = [
            'email' => $customer_email,
            'cardNumber' => preg_replace('/\s+/', '', $payment_data['card_number']),
            'kind' => 'credit',
            'expirationMonth' => substr($payment_data['card_expiry'], 0, 2),
            'expirationYear' => '20' . substr($payment_data['card_expiry'], 3, 2),
            'securityCode' => $payment_data['card_cvv'],
            'cardholderName' => $payment_data['card_holder'],
            'storageCard' => '0',
            'embeddedZeroDollar' => true
        ];

        $response = wp_remote_post($url, [
            'headers' => $this->get_api_headers(),
            'body' => json_encode($card_data),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        return $this->parse_tokenization_response($response);
    }

    /**
     * Parse da resposta de tokenização
     */
    private function parse_tokenization_response($response): array
    {
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        $status_code = wp_remote_retrieve_response_code($response);

        Logger::instance()->debug('Rede Tokenization Response: ' . $body, 'gateways');

        if ($status_code === 200 || $status_code === 201) {
            return [
                'success' => true,
                'tokenization_id' => $result['tokenizationId'] ?? '',
                'return_code' => $result['returnCode'] ?? '',
                'message' => $result['returnMessage'] ?? 'Cartão tokenizado com sucesso'
            ];
        }

        return [
            'success' => false,
            'message' => $result['returnMessage'] ?? 'Erro na tokenização do cartão',
            'error_code' => $result['returnCode'] ?? ''
        ];
    }

    /**
     * =========================================================================
     * MÉTODOS DE PREPARAÇÃO DE DADOS
     * =========================================================================
     */

    /**
     * Prepara dados para transação (inicial e recorrente)
     */
    private function prepare_transaction_data(
        SubscriptionInterface $subscription,
        string $card_token
    ): array {
        $amount = $this->get_subscription_amount($subscription);
        $reference = upmkt_build_reference($subscription->get_id());

        return [
            'capture' => true,
            'kind' => 'credit',
            'reference' => $reference,
            'amount' => (int)($amount * 100),
            'installments' => 1,
            'cardToken' => $card_token,
            'subscription' => true,
            'origin' => 1,
            'softDescriptor' => 'UP Market Sub'
        ];
    }

    /**
     * =========================================================================
     * MÉTODOS DA API
     * =========================================================================
     */

    /**
     * Cria transação na Rede (API v2)
     */
    private function create_transaction(array $data): array
    {
        $url = $this->api_url . '/v2/transactions';

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
     * Busca status da transação (API v2)
     */
    private function get_transaction_status(string $transaction_id): array
    {
        $url = $this->api_url . '/v2/transactions/' . $transaction_id;

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
     * Headers atualizados para API v2 com OAuth
     */
    private function get_api_headers(): array
    {
        // Tenta carregar token do cache primeiro
        if (!$this->oauth_service->load_cached_token()) {
            $access_token = $this->oauth_service->get_access_token();
        } else {
            $access_token = $this->oauth_service->get_access_token();
        }

        return [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'User-Agent'    => 'UP Market Subscriptions/' . UPMKT_VERSION
        ];
    }

    /**
     * Parse da resposta da API v2
     */
    private function parse_api_response($response): array
    {
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        $status_code = wp_remote_retrieve_response_code($response);

        Logger::instance()->debug('Rede API v2 Response: ' . $body, 'gateways');

        if ($status_code === 200 || $status_code === 201) {
            return [
                'success' => true,
                'transaction_id' => $result['tid'] ?? '',
                'brand_tid' => $result['brandTid'] ?? '',
                'last_four' => $result['last4'] ?? '',
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
     * WEBHOOK HANDLERS
     * =========================================================================
     */

    /**
     * Verificação de webhook
     * Ajustado para verificação simbólica do mTLS (deve ser validado no servidor web)
     */
    private function verify_webhook_signature(array $headers = []): bool
    {
        // Exemplo de verificação - o real controle da validação do certificado cliente deve ocorrer no servidor web (nginx, Apache)
        // Pode verificar variáveis passadas ao PHP como $_SERVER['SSL_CLIENT_VERIFY'], por exemplo

        if (!isset($_SERVER['SSL_CLIENT_VERIFY']) || $_SERVER['SSL_CLIENT_VERIFY'] !== 'SUCCESS') {
            Logger::instance()->error("Webhook SSL_CLIENT_VERIFY failed or missing", 'webhooks');
            return false;
        }

        // Outras validações podem ser feitas aqui conforme necessidade, como verificar CN do certificado, data, etc.

        Logger::instance()->info("Webhook SSL_CLIENT_VERIFY passed", 'webhooks');
        return true;
    }

    /**
     * Handler principal de webhook
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
        } else {
            Logger::instance()->info("Unhandled webhook event: {$type}", 'webhooks');
        }
    }

    private function handle_transaction_approved(array $data): void
    {
        $transaction_id = $data['transaction']['tid'] ?? '';
        $reference = $data['transaction']['reference'] ?? '';

        Logger::instance()->info("Transaction approved: {$transaction_id}", 'webhooks');
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


    /**
     * =========================================================================
     * CONFIGURAÇÕES E TESTES
     * =========================================================================
     */

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
            Logger::instance()->info("Testing Rede OAuth connection", 'gateways');

            // Tenta obter um token OAuth primeiro
            $access_token = $this->oauth_service->get_access_token();

            Logger::instance()->info("OAuth token obtained successfully: " . substr($access_token, 0, 20) . "...", 'gateways');

            return [
                'success' => true,
                'message' => 'Conexão com a API da Rede (v2) estabelecida com sucesso! Autenticação OAuth 2.0 funcionando.'
            ];

        } catch (\Exception $e) {
            Logger::instance()->error("Rede connection test failed: " . $e->getMessage(), 'gateways');
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
     * Obtém valor da doação
     */
    private function get_subscription_amount(SubscriptionInterface $subscription): float
    {
        $plan = new \UPMarket\Subscriptions\Entities\SubscriptionPlan($subscription->get_plan_id());
        return $plan->exists() ? $plan->get_price() : 0;
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
     * Validação de dados de pagamento
     */
    protected function validate_payment_data(array $payment_data): array
    {
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
     * Validação Luhn
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
}

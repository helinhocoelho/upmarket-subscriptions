<?php

namespace UPMarket\Subscriptions\Shortcodes;

use UPMarket\Subscriptions\Entities\SubscriptionPlan;
use UPMarket\Subscriptions\Core\Logger;

/**
 * Shortcode para checkout de assinatura
 *
 * @package UPMarket\Subscriptions\Shortcodes
 */
class CheckoutShortcode
{
    /**
     * Construtor
     */
    public function __construct()
    {
        add_shortcode('upmkt_checkout', [$this, 'render_checkout']);
        add_action('wp_ajax_upmkt_process_checkout', [$this, 'process_checkout']);
        add_action('wp_ajax_nopriv_upmkt_process_checkout', [$this, 'process_checkout']);
        add_action('wp_ajax_upmkt_process_registration', [$this, 'process_registration']);
        add_action('wp_ajax_nopriv_upmkt_process_registration', [$this, 'process_registration']);
        add_action('wp_ajax_upmkt_process_login', [$this, 'process_login']);
        add_action('wp_ajax_nopriv_upmkt_process_login', [$this, 'process_login']);

    }

    /**
     * Renderiza o formulário de checkout
     *
     * @param array $atts
     * @return string
     */
    public function render_checkout($atts): string
    {
        $plan_id = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : 0;

        if (!$plan_id) {
            $plans_url = get_plans_page('planos');
            return '<p>Plano não especificado. <a href="' . esc_url($plans_url) . '">Escolha um plano para continuar!</a>.</p>';
        }

        $plan = new SubscriptionPlan($plan_id);

        if (!$plan->exists() || !$plan->is_active()) {
            return '<p>Plano não encontrado ou indisponível.</p>';
        }

        // Carrega os scripts do front-end
        wp_enqueue_style('upmkt-front-css');
        wp_enqueue_script('upmkt-front-js');

        // Verifica se usuário está logado
        if (!is_user_logged_in()) {
            return $this->render_registration_form($plan_id);
        }

        ob_start();
        ?>
    <div class="upmkt-checkout">
        <div class="upmkt-checkout-summary">
            <h3>Resumo da Assinatura</h3>
            <div class="upmkt-plan-summary">
                <strong><?php echo esc_html($plan->get_name()); ?></strong><br>
                <?php echo esc_html($plan->get_formatted_price()); ?> / <?php echo esc_html($plan->get_formatted_period()); ?>
                
                <?php if ($plan->has_trial()): ?>
                    <div class="upmkt-trial-info">
                        <?php echo esc_html($plan->get_trial_period_days()); ?> dias de teste grátis
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <form id="upmkt-checkout-form" class="upmkt-checkout-form" method="POST">
            <?php wp_nonce_field('upmkt_process_checkout', 'upmkt_nonce'); ?>
            <input type="hidden" name="action" value="upmkt_process_checkout">
            <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan_id); ?>">
            
            <div class="upmkt-payment-methods">
                <h3>Método de Pagamento</h3>
                
                <div class="upmkt-payment-method">
                    <input type="radio" id="payment_rede" name="payment_method" value="rede" checked>
                    <label for="payment_rede">Cartão de Crédito (Rede)</label>
                    
                    <div class="upmkt-payment-details" id="rede-details">
                        <div class="upmkt-form-group">
                            <label for="card_number">Número do Cartão</label>
                            <input type="text" id="card_number" name="card_number" placeholder="0000 0000 0000 0000" required>
                        </div>
                        
                        <div class="upmkt-form-row">
                            <div class="upmkt-form-group">
                                <label for="card_expiry">Validade</label>
                                <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/AA" required>
                            </div>
                            
                            <div class="upmkt-form-group">
                                <label for="card_cvv">CVV</label>
                                <input type="text" id="card_cvv" name="card_cvv" placeholder="***" required>
                            </div>
                        </div>
                        
                        <div class="upmkt-form-group">
                            <label for="card_holder">Nome no Cartão</label>
                            <input type="text" id="card_holder" name="card_holder" placeholder="Como está no cartão" required>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="upmkt-form-actions">
                <button type="submit" class="upmkt-submit-button">
                    Finalizar Assinatura
                </button>
                
                <div class="upmkt-loading" style="display: none;">
                    Processando pagamento...
                </div>
            </div>
            
            <div class="upmkt-messages"></div>
        </form>
    </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Renderiza formulário de registro personalizado
     *
     * @param int $plan_id
     * @return string
     */
    private function render_registration_form(int $plan_id): string
    {
        $plan = new SubscriptionPlan($plan_id);

        // Gera nonces únicos para cada formulário
        $register_nonce = wp_create_nonce('upmkt_process_registration');
        $login_nonce = wp_create_nonce('upmkt_process_login');

        ob_start();
        ?>
    <div class="upmkt-registration-form">
        <div class="upmkt-registration-summary">
            <h3>Assinar: <?php echo esc_html($plan->get_name()); ?></h3>
            <div class="upmkt-plan-summary">
                <?php echo esc_html($plan->get_formatted_price()); ?> / <?php echo esc_html($plan->get_formatted_period()); ?>
                <?php if ($plan->has_trial()): ?>
                    <div class="upmkt-trial-info">
                        <?php echo esc_html($plan->get_trial_period_days()); ?> dias de teste grátis
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="upmkt-form-tabs">
            <div class="upmkt-tab active" data-tab="tab-register">Criar Conta</div>
            <div class="upmkt-tab" data-tab="tab-login">Fazer Login</div>
        </div>

        <!-- Formulário de Registro -->
        <div id="tab-register" class="upmkt-tab-content active">
            <form id="upmkt-registration-form" class="upmkt-checkout-form">
                <input type="hidden" id="upmkt_register_nonce" name="upmkt_nonce" value="<?php echo esc_attr($register_nonce); ?>">
                <input type="hidden" name="action" value="upmkt_process_registration">
                <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan_id); ?>">
                
                <div class="upmkt-form-group">
                    <label for="upmkt_document_type">Tipo de Documento</label>
                    <div class="upmkt-document-type">
                        <label>
                            <input type="radio" name="document_type" value="cpf" checked>
                            CPF
                        </label>
                        <label>
                            <input type="radio" name="document_type" value="cnpj">
                            CNPJ
                        </label>
                    </div>
                </div>

                <div class="upmkt-form-group">
                    <label for="upmkt_document">CPF/CNPJ</label>
                    <input type="text" id="upmkt_document" name="document" placeholder="000.000.000-00" required>
                </div>

                <div class="upmkt-form-row">
                    <div class="upmkt-form-group">
                        <label for="upmkt_first_name">Nome</label>
                        <input type="text" id="upmkt_first_name" name="first_name" placeholder="Seu nome" required>
                    </div>
                    
                    <div class="upmkt-form-group">
                        <label for="upmkt_last_name">Sobrenome</label>
                        <input type="text" id="upmkt_last_name" name="last_name" placeholder="Seu sobrenome" required>
                    </div>
                </div>

                <div class="upmkt-form-group">
                    <label for="upmkt_email">E-mail</label>
                    <input type="email" id="upmkt_email" name="email" placeholder="seu@email.com" required>
                </div>

                <div class="upmkt-terms">
                    <label>
                        <input type="checkbox" name="terms" required>
                        <div class="upmkt-terms-text">
                            Concordo com os <a href="<?php echo esc_url(get_permalink(get_option('wp_page_for_privacy_policy'))); ?>" target="_blank">Termos e Condições</a> 
                            e <a href="<?php echo esc_url(get_permalink(get_option('wp_page_for_privacy_policy'))); ?>" target="_blank">Política de Privacidade</a>
                        </div>
                    </label>
                </div>
                
                <div class="upmkt-form-actions">
                    <button type="submit" class="upmkt-submit-button">
                        Criar Conta e Continuar
                    </button>
                    
                    <div class="upmkt-loading" style="display: none;">
                        Criando sua conta...
                    </div>
                </div>
                
                <div class="upmkt-messages"></div>
            </form>
        </div>

        <!-- Formulário de Login -->
        <div id="tab-login" class="upmkt-tab-content">
            <form id="upmkt-login-form" class="upmkt-checkout-form">
                <input type="hidden" id="upmkt_login_nonce" name="upmkt_nonce" value="<?php echo esc_attr($login_nonce); ?>">
                <input type="hidden" name="action" value="upmkt_process_login">
                <input type="hidden" name="plan_id" value="<?php echo esc_attr($plan_id); ?>">
                
                <div class="upmkt-form-group">
                    <label for="upmkt_login_email">E-mail</label>
                    <input type="email" id="upmkt_login_email" name="email" placeholder="seu@email.com" required>
                </div>

                <div class="upmkt-form-group">
                    <label for="upmkt_login_password">Senha</label>
                    <input type="password" id="upmkt_login_password" name="password" placeholder="Sua senha" required>
                </div>
                
                <div class="upmkt-form-actions">
                    <button type="submit" class="upmkt-submit-button">
                        Fazer Login
                    </button>
                    
                    <div class="upmkt-loading" style="display: none;">
                        Efetuando login...
                    </div>
                </div>
                
                <div class="upmkt-messages"></div>
            </form>
        </div>
    </div>
    <?php
        return ob_get_clean();
    }

    /**
     * Processa o registro de novo usuário via AJAX
     */
    public function process_registration(): void
    {
        check_ajax_referer('upmkt_process_registration', 'upmkt_nonce');

        $document_type = sanitize_text_field($_POST['document_type'] ?? 'cpf');
        $document = sanitize_text_field($_POST['document'] ?? '');
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $terms = isset($_POST['terms']) ? true : false;
        $plan_id = intval($_POST['plan_id'] ?? 0);

        $errors = [];

        // Validações básicas
        if (empty($document)) {
            $errors[] = 'CPF/CNPJ é obrigatório';
        }

        if (empty($first_name)) {
            $errors[] = 'Nome é obrigatório';
        }

        if (empty($last_name)) {
            $errors[] = 'Sobrenome é obrigatório';
        }

        if (empty($email) || !is_email($email)) {
            $errors[] = 'E-mail válido é obrigatório';
        }

        if (!$terms) {
            $errors[] = 'Você deve aceitar os Termos e Condições';
        }

        // Validação de e-mail duplicado
        if (email_exists($email)) {
            $errors[] = 'Este e-mail já está cadastrado. Faça login ou use outro e-mail.';
        }

        // Validações de CPF/CNPJ
        if (!empty($document)) {
            $document_clean = preg_replace('/[^0-9]/', '', $document);

            if ($document_type === 'cpf') {
                if (!$this->validate_cpf($document_clean)) {
                    $errors[] = 'CPF inválido. Verifique os dígitos.';
                }
            } else {
                if (!$this->validate_cnpj($document_clean)) {
                    $errors[] = 'CNPJ inválido. Verifique os dígitos.';
                }
            }

            // Validação de duplicidade de CPF/CNPJ
            if ($this->document_exists($document_clean, $document_type)) {
                if ($document_type === 'cpf') {
                    $errors[] = 'Este CPF já está cadastrado em nossa base.';
                } else {
                    $errors[] = 'Este CNPJ já está cadastrado em nossa base.';
                }
            }
        }

        if (!empty($errors)) {
            wp_send_json_error(['errors' => $errors]);
            return;
        }

        try {
            // Cria o usuário (username será o email)
            $user_id = wp_create_user($email, wp_generate_password(12), $email);

            if (is_wp_error($user_id)) {
                throw new \Exception($user_id->get_error_message());
            }

            // Atualiza informações do usuário
            wp_update_user([
                'ID' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $first_name . ' ' . $last_name
            ]);

            // Salva metadados customizados
            update_user_meta($user_id, 'upmkt_document_type', $document_type);
            update_user_meta($user_id, 'upmkt_document', $document_clean); // Salva sem formatação
            update_user_meta($user_id, 'upmkt_document_formatted', $document); // Salva com formatação

            // Loga o usuário automaticamente
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);

            // Envia e-mail para definir senha
            wp_new_user_notification($user_id, null, 'both');

            Logger::instance()->info("New user registered via checkout: {$email} - {$document_type}: {$document_clean}", 'registration');

            wp_send_json_success([
                'message' => 'Conta criada com sucesso! Redirecionando para o checkout...'
            ]);

        } catch (\Exception $e) {
            Logger::instance()->error('Registration error: ' . $e->getMessage(), 'registration');
            wp_send_json_error(['errors' => ['Erro ao criar conta: ' . $e->getMessage()]]);
        }
    }

    /**
     * Valida CPF (dígitos verificadores)
     *
     * @param string $cpf
     * @return bool
     */
    private function validate_cpf(string $cpf): bool
    {
        // Remove caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        // Verifica se tem 11 dígitos
        if (strlen($cpf) != 11) {
            return false;
        }

        // Verifica se é uma sequência de dígitos repetidos
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Calcula o primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += $cpf[$i] * (10 - $i);
        }
        $remainder = $sum % 11;
        $digit1 = ($remainder < 2) ? 0 : 11 - $remainder;

        // Verifica o primeiro dígito
        if ($cpf[9] != $digit1) {
            return false;
        }

        // Calcula o segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += $cpf[$i] * (11 - $i);
        }
        $remainder = $sum % 11;
        $digit2 = ($remainder < 2) ? 0 : 11 - $remainder;

        // Verifica o segundo dígito
        return $cpf[10] == $digit2;
    }

    /**
     * Valida CNPJ (dígitos verificadores)
     *
     * @param string $cnpj
     * @return bool
     */
    private function validate_cnpj(string $cnpj): bool
    {
        // Remove caracteres não numéricos
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

        // Verifica se tem 14 dígitos
        if (strlen($cnpj) != 14) {
            return false;
        }

        // Verifica se é uma sequência de dígitos repetidos
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        // Pesos para o primeiro dígito
        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        // Calcula o primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += $cnpj[$i] * $weights1[$i];
        }
        $remainder = $sum % 11;
        $digit1 = ($remainder < 2) ? 0 : 11 - $remainder;

        // Verifica o primeiro dígito
        if ($cnpj[12] != $digit1) {
            return false;
        }

        // Pesos para o segundo dígito
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        // Calcula o segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += $cnpj[$i] * $weights2[$i];
        }
        $remainder = $sum % 11;
        $digit2 = ($remainder < 2) ? 0 : 11 - $remainder;

        // Verifica o segundo dígito
        return $cnpj[13] == $digit2;
    }

    /**
     * Verifica se um documento (CPF/CNPJ) já existe no sistema
     *
     * @param string $document
     * @param string $type
     * @return bool
     */
    private function document_exists(string $document, string $type = 'cpf'): bool
    {
        global $wpdb;

        $document_clean = preg_replace('/[^0-9]/', '', $document);

        // Busca por documentos duplicados (considerando que pode ter formatação diferente)
        $results = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = 'upmkt_document' 
             AND meta_value = %s",
                $document_clean
            )
        );

        return $results > 0;
    }

    /**
     * Processa o checkout via AJAX
     */
    public function process_checkout(): void
    {
        check_ajax_referer('upmkt_process_checkout', 'upmkt_nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['errors' => ['Usuário não logado']]);
            return;
        }

        $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : 0;
        $payment_method = sanitize_text_field($_POST['payment_method'] ?? '');

        if (!$plan_id) {
            wp_send_json_error(['errors' => ['Plano não especificado']]);
            return;
        }

        try {
            $user_id = get_current_user_id();

            $plan = new SubscriptionPlan($plan_id);

            if (!$plan->exists() || !$plan->is_active()) {
                wp_send_json_error(['errors' => ['Plano não encontrado ou indisponível']]);
                return;
            }

            // Prepara dados do pagamento
            $payment_data = [
                'payment_method' => $payment_method,
                'card_number' => sanitize_text_field($_POST['card_number'] ?? ''),
                'card_expiry' => sanitize_text_field($_POST['card_expiry'] ?? ''),
                'card_cvv' => sanitize_text_field($_POST['card_cvv'] ?? ''),
                'card_holder' => sanitize_text_field($_POST['card_holder'] ?? ''),
                'amount' => 0
            ];

            // Validações básicas dos dados do cartão
            $validation_errors = $this->validate_payment_data($payment_data);
            if (!empty($validation_errors)) {
                wp_send_json_error(['errors' => $validation_errors]);
                return;
            }

            // Processa a assinatura via SubscriptionManager
            $subscription_manager = new \UPMarket\Subscriptions\Services\SubscriptionManager();
            $result = $subscription_manager->create_subscription($user_id, $plan_id, $payment_data, $payment_method);

            if ($result['success']) {
                wp_send_json_success([
                    'message' => 'Assinatura criada com sucesso! Redirecionando...',
                    'redirect_url' => $this->get_success_url($result['subscription_id'])
                ]);
            } else {
                wp_send_json_error(['errors' => $result['errors']]);
            }

        } catch (\Exception $e) {
            wp_send_json_error(['errors' => ['Erro interno: ' . $e->getMessage()]]);
        }
    }

    /**
     * Valida dados do pagamento
     *
     * @param array $payment_data
     * @return array
     */
    private function validate_payment_data(array $payment_data): array
    {
        $errors = [];

        if (empty($payment_data['card_number'])) {
            $errors[] = 'Número do cartão é obrigatório';
        } else {
            $card_number = preg_replace('/\s+/', '', $payment_data['card_number']);
            if (!preg_match('/^\d{13,19}$/', $card_number)) {
                $errors[] = 'Número do cartão inválido';
            }
        }

        if (empty($payment_data['card_expiry'])) {
            $errors[] = 'Data de validade é obrigatória';
        } else {
            if (!preg_match('/^\d{2}\/\d{2}$/', $payment_data['card_expiry'])) {
                $errors[] = 'Formato da validade inválido (use MM/AA)';
            }
        }

        if (empty($payment_data['card_cvv'])) {
            $errors[] = 'CVV é obrigatório';
        } else {
            if (!preg_match('/^\d{3,4}$/', $payment_data['card_cvv'])) {
                $errors[] = 'CVV inválido';
            }
        }

        if (empty($payment_data['card_holder'])) {
            $errors[] = 'Nome no cartão é obrigatório';
        }

        return $errors;
    }

    /**
     * Processa o login via AJAX
     */
    public function process_login(): void
    {
        check_ajax_referer('upmkt_process_login', 'upmkt_nonce');

        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $plan_id = intval($_POST['plan_id'] ?? 0);

        $errors = [];

        // Validações
        if (empty($email) || !is_email($email)) {
            $errors[] = 'E-mail válido é obrigatório';
        }

        if (empty($password)) {
            $errors[] = 'Senha é obrigatória';
        }

        if (!empty($errors)) {
            wp_send_json_error(['errors' => $errors]);
            return;
        }

        try {
            // Tenta fazer login
            $user = wp_authenticate($email, $password);

            if (is_wp_error($user)) {
                $errors[] = 'E-mail ou senha inválidos';
                wp_send_json_error(['errors' => $errors]);
                return;
            }

            // Loga o usuário
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);

            Logger::instance()->info("User logged in via checkout: {$email}", 'login');

            wp_send_json_success([
                'message' => 'Login realizado com sucesso! Redirecionando...'
            ]);

        } catch (\Exception $e) {
            Logger::instance()->error('Login error: ' . $e->getMessage(), 'login');
            wp_send_json_error(['errors' => ['Erro ao fazer login: ' . $e->getMessage()]]);
        }
    }

    /**
     * Retorna URL de sucesso
     *
     * @param int $subscription_id
     * @return string
     */
    private function get_success_url(int $subscription_id): string
    {
        // Busca a página de área do cliente ou usa a página atual
        $customer_area_page = get_customer_area_page();

        return add_query_arg([
            'subscription_id' => $subscription_id,
            'upmkt_checkout' => 'success'
        ], $customer_area_page);
    }

}

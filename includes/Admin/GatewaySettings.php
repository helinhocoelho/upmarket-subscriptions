<?php

namespace UPMarket\Subscriptions\Admin;

use UPMarket\Subscriptions\Core\GatewayManager;
use UPMarket\Subscriptions\Core\Logger;

/**
 * Configurações centralizadas para todos os gateways
 *
 * @package UPMarket\Subscriptions\Admin
 */
class GatewaySettings
{
    /**
     * @var GatewayManager
     */
    private $gateway_manager;

    /**
     * Construtor
     */
    public function __construct()
    {
        $this->gateway_manager = GatewayManager::instance();
        $this->init_hooks();
    }

    /**
     * Inicializa os hooks
     */
    private function init_hooks(): void
    {
        add_action('admin_init', [$this, 'register_gateway_settings']);
        add_action('upmkt_admin_settings_tabs', [$this, 'add_settings_tab']);
        add_action('upmkt_admin_settings_content', [$this, 'render_settings_content']);
        add_action('wp_ajax_upmkt_test_gateway_connection', [$this, 'test_gateway_connection']);
    }

    /**
     * Registra configurações INDIVIDUAIS para cada gateway
     */
    public function register_gateway_settings(): void
    {
        $gateways = $this->gateway_manager->get_gateways();

        foreach ($gateways as $gateway_id => $gateway) {
            // Cada gateway tem SEU PRÓPRIO option no banco
            register_setting(
                "upmkt_gateway_{$gateway_id}_settings",
                "upmkt_gateway_{$gateway_id}_settings"
            );
        }
    }

    /**
     * Adiciona aba de configurações
     */
    public function add_settings_tab(): void
    {
        ?>
        <a href="#gateways" class="nav-tab">Gateways de Pagamento</a>
        <?php
    }

    /**
     * Renderiza conteúdo das configurações
     */
    public function render_settings_content(): void
    {
        $gateways = $this->gateway_manager->get_gateways();
        ?>
        <div id="gateways" class="tab-content">
            <div class="upmkt-card">
                <h2>Configurações dos Gateways de Pagamento</h2>
                <p>Configure as integrações com os gateways de pagamento suportados.</p>
            </div>
            
            <?php foreach ($gateways as $gateway_id => $gateway): ?>
                <?php $this->render_gateway_settings($gateway); ?>
            <?php endforeach; ?>
            
            <?php if (empty($gateways)): ?>
                <div class="upmkt-card">
                    <p>Nenhum gateway de pagamento registrado.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renderiza configurações de um gateway específico
     */
    private function render_gateway_settings($gateway): void
    {
        $gateway_id = $gateway->get_id();
        $gateway_name = $gateway->get_name();

        // CADA GATEWAY BUSCA SEUS PRÓPRIOS DADOS
        $gateway_settings = get_option("upmkt_gateway_{$gateway_id}_settings", []);
        $is_configured = $gateway->is_configured();
        ?>
        <div class="upmkt-card upmkt-gateway-settings" data-gateway="<?php echo esc_attr($gateway_id); ?>">
            <h3>
                <?php echo esc_html($gateway_name); ?>
                <span class="upmkt-gateway-status <?php echo $is_configured ? 'status-active' : 'status-inactive'; ?>">
                    <?php echo $is_configured ? '✓ Configurado' : '⚙️ Não Configurado'; ?>
                </span>
            </h3>
            
            <form method="post" action="options.php" class="upmkt-gateway-form">
                <?php
                // SETTINGS FIELDS INDIVIDUAL PARA CADA GATEWAY
                settings_fields("upmkt_gateway_{$gateway_id}_settings");
        ?>
                
                <table class="form-table">
                    <tbody>
                        <!-- Campo Enabled -->
                        <tr>
                            <th scope="row">Habilitar Gateway</th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                           name="upmkt_gateway_<?php echo esc_attr($gateway_id); ?>_settings[enabled]" 
                                           value="yes" 
                                           <?php checked($gateway_settings['enabled'] ?? '', 'yes'); ?>>
                                    Ativar este gateway
                                </label>
                            </td>
                        </tr>
                        
                        <!-- Campos específicos por gateway -->
                        <?php $this->render_gateway_specific_fields($gateway_id, $gateway_settings); ?>
                        
                        <!-- Campo Título -->
                        <tr>
                            <th scope="row">Título</th>
                            <td>
                                <input type="text" 
                                       name="upmkt_gateway_<?php echo esc_attr($gateway_id); ?>_settings[title]" 
                                       value="<?php echo esc_attr($gateway_settings['title'] ?? $gateway_name); ?>" 
                                       class="regular-text">
                                <p class="description">Título que o cliente verá durante o checkout.</p>
                            </td>
                        </tr>
                        
                        <!-- Campo Descrição -->
                        <tr>
                            <th scope="row">Descrição</th>
                            <td>
                                <textarea name="upmkt_gateway_<?php echo esc_attr($gateway_id); ?>_settings[description]" 
                                          class="large-text" 
                                          rows="3"><?php echo esc_textarea($gateway_settings['description'] ?? ''); ?></textarea>
                                <p class="description">Descrição que o cliente verá durante o checkout.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="upmkt-gateway-actions">
                    <?php submit_button('Salvar Configurações', 'primary', "submit_{$gateway_id}"); ?>
                    
                    <?php if ($is_configured): ?>
                        <button type="button" 
                                class="button button-secondary upmkt-test-connection" 
                                data-gateway="<?php echo esc_attr($gateway_id); ?>">
                            Testar Conexão
                        </button>
                    <?php endif; ?>
                </div>
                
                <div class="upmkt-test-result" id="upmkt-test-result-<?php echo esc_attr($gateway_id); ?>" style="display: none;"></div>
            </form>
        </div>
        <?php
    }

    /**
     * Renderiza campos específicos de cada gateway
     */
    private function render_gateway_specific_fields(string $gateway_id, array $settings): void
    {
        switch ($gateway_id) {
            case 'rede':
                $this->render_rede_fields($settings);
                break;

                // Adicione outros gateways aqui no futuro
            default:
                do_action("upmkt_render_gateway_fields_{$gateway_id}", $settings);
                break;
        }
    }

    /**
     * Renderiza campos específicos da Rede
     */
    private function render_rede_fields(array $settings): void
    {
        ?>
        <tr>
            <th scope="row">Ambiente</th>
            <td>
                <select name="upmkt_gateway_rede_settings[environment]">
                    <option value="sandbox" <?php selected($settings['environment'] ?? '', 'sandbox'); ?>>Sandbox (Testes)</option>
                    <option value="production" <?php selected($settings['environment'] ?? '', 'production'); ?>>Produção</option>
                </select>
                <p class="description">Use Sandbox para testes e Produção para ambiente real.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">PV (Affiliation)</th>
            <td>
                <input type="text" 
                       name="upmkt_gateway_rede_settings[pv]" 
                       value="<?php echo esc_attr($settings['pv'] ?? ''); ?>" 
                       class="regular-text">
                <p class="description">Número do PV (affiliation) fornecido pela Rede.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">Token</th>
            <td>
                <input type="password" 
                       name="upmkt_gateway_rede_settings[token]" 
                       value="<?php echo esc_attr($settings['token'] ?? ''); ?>" 
                       class="regular-text">
                <p class="description">Token de autenticação fornecido pela Rede.</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">URL do Webhook</th>
            <td>
                <code><?php echo esc_url(home_url('/upmkt-webhook/rede/')); ?></code>
                <p class="description">Configure este URL no painel da Rede para receber notificações.</p>
            </td>
        </tr>
        <?php
    }

    /**
     * Testa conexão com gateway via AJAX
     */
    public function test_gateway_connection(): void
    {
        check_ajax_referer('upmkt_admin_nonce', 'nonce');

        if (!current_user_can('manage_upmkt_subscriptions')) {
            wp_send_json_error(['message' => 'Sem permissão.']);
            return;
        }

        $gateway_id = sanitize_text_field($_POST['gateway_id'] ?? '');

        if (empty($gateway_id)) {
            wp_send_json_error(['message' => 'Gateway não especificado.']);
            return;
        }

        try {
            $gateway = $this->gateway_manager->get_gateway($gateway_id);

            if (!$gateway) {
                wp_send_json_error(['message' => 'Gateway não encontrado.']);
                return;
            }

            $result = $this->perform_gateway_test($gateway);

            if ($result['success']) {
                wp_send_json_success(['message' => $result['message']]);
            } else {
                wp_send_json_error(['message' => $result['message']]);
            }

        } catch (\Exception $e) {
            Logger::instance()->error("Gateway connection test failed: " . $e->getMessage(), 'admin');
            wp_send_json_error(['message' => 'Erro: ' . $e->getMessage()]);
        }
    }

    /**
     * Executa teste específico para cada gateway
     */
    private function perform_gateway_test($gateway): array
    {
        $gateway_id = $gateway->get_id();

        switch ($gateway_id) {
            case 'rede':
                return $this->test_rede_connection($gateway);

            default:
                return [
                    'success' => false,
                    'message' => 'Teste não implementado para este gateway.'
                ];
        }
    }

    /**
     * Testa conexão com a Rede
     */
    private function test_rede_connection($gateway): array
    {
        try {
            $test_transaction_id = 'test_connection';
            $status_result = $gateway->check_payment_status($test_transaction_id);

            return [
                'success' => true,
                'message' => 'Conexão com a API da Rede estabelecida com sucesso!'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Falha na conexão: ' . $e->getMessage()
            ];
        }
    }
}

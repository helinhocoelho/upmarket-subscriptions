<?php

namespace UPMarket\Subscriptions\Admin;

use UPMarket\Subscriptions\Core\GatewayManager;
use UPMarket\Subscriptions\Core\Logger;

/**
 * Configurações dinâmicas para gateways
 */
class GatewaySettings
{
    private $gateway_manager;
    private static $tabs_added = false;

    public function __construct()
    {
        $this->gateway_manager = GatewayManager::instance();
        $this->init_hooks();
    }

    private function init_hooks(): void
    {
        add_action('admin_init', [$this, 'register_gateway_settings']);
        add_action('upmkt_admin_settings_tabs', [$this, 'add_settings_tab']);
        add_action('upmkt_admin_settings_content', [$this, 'render_settings_content']);
        add_action('wp_ajax_upmkt_test_gateway_connection', [$this, 'test_gateway_connection']);
    }

    public function register_gateway_settings(): void
    {
        $gateways = $this->gateway_manager->get_gateways();

        foreach ($gateways as $gateway_id => $gateway) {
            register_setting(
                "upmkt_gateway_{$gateway_id}_settings",
                "upmkt_gateway_{$gateway_id}_settings",
                [$this, 'validate_gateway_settings']
            );
        }
    }

    /**
     * Validação dinâmica 	baseada no gateway
     */
    public function validate_gateway_settings(array $settings): array
    {
        $gateway_id = sanitize_text_field($_POST['gateway_id'] ?? '');

        if (empty($gateway_id)) {
            $option_page = sanitize_text_field($_POST['option_page'] ?? '');
            if (strpos($option_page, 'upmkt_gateway_') === 0) {
                $gateway_id = str_replace(['upmkt_gateway_', '_settings'], '', $option_page);
            }
        }

        if (empty($gateway_id)) {
            return $settings;
        }

        $gateway = $this->gateway_manager->get_gateway($gateway_id);

        if ($gateway) {
            $errors = $gateway->validate_settings($settings);

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    add_settings_error(
                        "upmkt_gateway_{$gateway_id}_settings",
                        "upmkt_gateway_{$gateway_id}_error",
                        $error
                    );
                }
            }
        }

        return $settings;
    }

    public function add_settings_tab(): void
    {
        if (self::$tabs_added) {
            return;
        }
        self::$tabs_added = true;
        ?>
        <a href="#gateways" class="nav-tab">Gateways de Pagamento</a>
        <?php
    }

    public function render_settings_content(): void
    {
        $gateways = $this->gateway_manager->get_gateways();
        ?>
				<div id="gateways" class="tab-content">
						<?php foreach ($gateways as $gateway_id => $gateway): ?>
								<?php $this->render_gateway_settings_form($gateway); ?>
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
     * Renderiza formulário dinâmico baseado nos campos do gateway
     */
    private function render_gateway_settings_form($gateway): void
    {
        $gateway_id = $gateway->get_id();
        $gateway_name = $gateway->get_name();
        $settings = get_option("upmkt_gateway_{$gateway_id}_settings", []);
        $is_configured = $gateway->is_configured();
        $is_enabled = ($settings['enabled'] ?? '') === 'yes';
        $fields = $gateway->get_settings_fields();

        static $first_gateway = true;
        $is_open = $first_gateway;
        $first_gateway = false;
        ?>
					<div class="upmkt-card upmkt-gateway-accordion" data-gateway="<?php echo esc_attr($gateway_id); ?>">
							<div class="upmkt-accordion-header">
									<h3 class="upmkt-accordion-title">
											<button type="button" class="upmkt-accordion-toggle" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
													<span class="upmkt-accordion-icon"><?php echo $is_open ? '−' : '+'; ?></span>
													<?php echo esc_html($gateway_name); ?>
													
													<!-- Status de ativação do gateway -->
													<span class="upmkt-gateway-status upmkt-gateway-enabled-status <?php echo $is_enabled ? 'status-enabled' : 'status-disabled'; ?>">
															<?php echo $is_enabled ? '🟢 Ativo' : '🔴 Inativo'; ?>
													</span>
													
													<!-- Status de configuração -->
													<span class="upmkt-gateway-status <?php echo $is_configured ? 'status-active' : 'status-inactive'; ?>">
															<?php echo $is_configured ? '✓ Configurado' : '⚙️ Não Configurado'; ?>
													</span>
											</button>
									</h3>
							</div>
							
							<div class="upmkt-accordion-content" <?php echo $is_open ? '' : 'style="display: none;"'; ?>>
									<form method="post" action="options.php" class="upmkt-gateway-form">
											<?php
                                                    // CORREÇÃO: Processar settings_fields de forma limpa
                                                    ob_start();
        settings_fields("upmkt_gateway_{$gateway_id}_settings");
        $settings_fields = ob_get_clean();

        // CORREÇÃO: Remover todos os IDs problemáticos
        $settings_fields = preg_replace('/\sid="[^"]*"/', '', $settings_fields);
        $settings_fields = str_replace(
            [
                        'name="_wp_http_referer"',
                        'value="' . esc_attr(wp_unslash($_SERVER['REQUEST_URI'])) . '"'
                ],
            [
                        'name="' . esc_attr("{$gateway_id}_wp_http_referer") . '"',
                        'value="' . esc_attr(wp_unslash($_SERVER['REQUEST_URI'])) . '"'
                ],
            $settings_fields
        );

        echo $settings_fields;
        ?>
											<input type="hidden" name="gateway_id" value="<?php echo esc_attr($gateway_id); ?>">
											
											<table class="form-table">
													<tbody>
															<?php foreach ($fields as $field_key => $field_config): ?>
																	<?php $this->render_settings_field($field_key, $field_config, $settings, $gateway_id); ?>
															<?php endforeach; ?>
													</tbody>
											</table>
											
											<div class="upmkt-gateway-actions">
													<?php
                submit_button('Salvar Configurações', 'primary', "submit_{$gateway_id}", false, [
                        'id' => ''
                ]);
        ?>
													
													<?php if ($is_configured && $is_enabled): ?>
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
					</div>
				<?php
    }

    /**
     * Renderiza campo de configuração dinamicamente
     */
    private function render_settings_field(string $field_key, array $field_config, array $settings, string $gateway_id): void
    {
        $current_value = $settings[$field_key] ?? $field_config['default'] ?? '';
        $field_name = "upmkt_gateway_{$gateway_id}_settings[{$field_key}]";
        $required = isset($field_config['required']) && $field_config['required'] ? 'required' : '';

        ?>
        <tr>
            <th scope="row"><?php echo esc_html($field_config['title'] ?? $field_key); ?></th>
            <td>
                <?php switch ($field_config['type'] ?? 'text'):
                    case 'checkbox': ?>
                        <label>
                            <input type="checkbox" 
                                   name="<?php echo esc_attr($field_name); ?>" 
                                   value="yes" 
                                   <?php checked($current_value, 'yes'); ?>
                                   <?php echo $required; ?>>
                            <?php echo esc_html($field_config['label'] ?? 'Ativar'); ?>
                        </label>
                        <?php break;

                    case 'select': ?>
                        <select name="<?php echo esc_attr($field_name); ?>" class="<?php echo esc_attr($field_config['class'] ?? ''); ?>" <?php echo $required; ?>>
                            <?php foreach ($field_config['options'] ?? [] as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_value, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php break;

                    case 'textarea': ?>
                        <textarea name="<?php echo esc_attr($field_name); ?>" 
                                  class="<?php echo esc_attr($field_config['class'] ?? 'large-text'); ?>" 
                                  rows="<?php echo esc_attr($field_config['rows'] ?? 3); ?>"
                                  <?php echo $required; ?>><?php echo esc_textarea($current_value); ?></textarea>
                        <?php break;

                    case 'password': ?>
                        <input type="password" 
                               name="<?php echo esc_attr($field_name); ?>" 
                               value="<?php echo esc_attr($current_value); ?>" 
                               class="<?php echo esc_attr($field_config['class'] ?? 'regular-text'); ?>"
                               <?php echo $required; ?>>
                        <?php break;

                    case 'custom':
                        if (isset($field_config['render_callback']) && is_callable($field_config['render_callback'])) {
                            call_user_func($field_config['render_callback'], $settings);
                        }
                        break;

                    default: ?>
                        <input type="<?php echo esc_attr($field_config['type'] ?? 'text'); ?>" 
                               name="<?php echo esc_attr($field_name); ?>" 
                               value="<?php echo esc_attr($current_value); ?>" 
                               class="<?php echo esc_attr($field_config['class'] ?? 'regular-text'); ?>"
                               <?php echo $required; ?>>
                <?php endswitch; ?>
                
                <?php if (!empty($field_config['description'])): ?>
                    <p class="description"><?php echo esc_html($field_config['description']); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Teste de conexão via AJAX
     */
    public function test_gateway_connection(): void
    {
        check_ajax_referer('upmkt_admin_nonce', 'nonce');

        if (!current_user_can('manage_upmkt_subscriptions')) {
            wp_send_json_error(['message' => 'Sem permissão.']);
        }

        $gateway_id = sanitize_text_field($_POST['gateway_id'] ?? '');
        $gateway = $this->gateway_manager->get_gateway($gateway_id);

        if (!$gateway) {
            wp_send_json_error(['message' => 'Gateway não encontrado.']);
        }

        try {
            $result = $gateway->test_connection();

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
}

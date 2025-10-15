<?php
/**
 * Plugin Name: UP Market Subscriptions
 * Plugin URI: https://upmarket.com.br
 * Description: Sistema de assinaturas recorrentes personalizado
 * Version: 1.0.0
 * Author: UP Market - Hélio Coelho
 * Text Domain: upmarket-subscriptions
 * Domain Path: /languages
 * Requires PHP: 7.4
 */

// Previne acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Define constantes do plugin
define('UPMKT_PLUGIN_FILE', __FILE__);
define('UPMKT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('UPMKT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UPMKT_VERSION', '1.0.0');

// Verifica se Composer está carregado
if (!file_exists(UPMKT_PLUGIN_PATH . 'vendor/autoload.php')) {
    add_action('admin_notices', function () {
        ?>
        <div class="notice notice-error">
            <p>
                <strong>UP Market Subscriptions:</strong> 
                Dependências do Composer não encontradas. Execute <code>composer install</code>.
            </p>
        </div>
        <?php
    });
    return;
}

// Carrega o autoloader do Composer
if (file_exists(UPMKT_PLUGIN_PATH . 'vendor/autoload.php')) {
    require_once UPMKT_PLUGIN_PATH . 'vendor/autoload.php';
} else {
    require_once UPMKT_PLUGIN_PATH . 'includes/autoload.php';
}

// Inicializa o plugin
add_action('plugins_loaded', function () {
    // Carreva traduções
    load_plugin_textdomain(
        'upmarket-subscriptions',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

    // Inicializa o core do plugin
    UPMarket\Subscriptions\Core\Plugin::instance();
});

// Registra ativação e desativação
register_activation_hook(__FILE__, ['UPMarket\Subscriptions\Core\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['UPMarket\Subscriptions\Core\Deactivator', 'deactivate']);

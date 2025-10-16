<?php

namespace UPMarket\Subscriptions\Providers;

use UPMarket\Subscriptions\Core\GatewayManager;
use UPMarket\Subscriptions\Gateways\Rede;

/**
 * Service Provider para Gateways de Pagamento
 *
 * @package UPMarket\Subscriptions\Providers
 */
class GatewayServiceProvider
{
    /**
     * Registra todos os gateways
     *
     * @param GatewayManager $gateway_manager
     */
    public function register(GatewayManager $gateway_manager): void
    {
        // CORREÇÃO: Registro manual mantido para compatibilidade
        $gateway_manager->register_gateway(new Rede());

        // NOVO: Descoberta automática de gateways
        $this->auto_discover_gateways($gateway_manager);

        // Hook para outros plugins registrarem gateways
        do_action('upmkt_register_gateways', $gateway_manager);

        $this->log("Gateway registration completed. Total: " . count($gateway_manager->get_gateways()));
    }

    /**
     * NOVO: Descobre e registra gateways automaticamente
     *
     * @param GatewayManager $gateway_manager
     */
    private function auto_discover_gateways(GatewayManager $gateway_manager): void
    {
        $gateways_path = UPMKT_PLUGIN_PATH . 'includes/Gateways/';

        if (!file_exists($gateways_path)) {
            $this->log("Gateways directory not found: {$gateways_path}", 'warning');
            return;
        }

        $files = glob($gateways_path . '*.php');

        if (empty($files)) {
            $this->log("No gateway files found in: {$gateways_path}", 'warning');
            return;
        }

        $registered_count = 0;

        foreach ($files as $file) {
            $filename = basename($file, '.php');

            // Pula arquivos que não são classes de gateway
            if ($filename === 'index' || strpos($filename, '.') === 0) {
                continue;
            }

            $class_name = 'UPMarket\\Subscriptions\\Gateways\\' . $filename;

            if (!class_exists($class_name)) {
                $this->log("Gateway class not found: {$class_name}", 'warning');
                continue;
            }

            // Verifica se é uma subclasse de AbstractPaymentGateway
            if (!is_subclass_of($class_name, 'UPMarket\\Subscriptions\\Abstracts\\AbstractPaymentGateway')) {
                $this->log("Class {$class_name} is not a payment gateway", 'warning');
                continue;
            }

            try {
                $gateway = new $class_name();

                if ($gateway_manager->register_gateway($gateway)) {
                    $registered_count++;
                    $this->log("Auto-discovered gateway: {$filename}");
                }

            } catch (\Exception $e) {
                $this->log("Failed to instantiate gateway {$filename}: " . $e->getMessage(), 'error');
            }
        }

        $this->log("Auto-discovery completed. Registered: {$registered_count} gateways");
    }

    /**
     * NOVO: Logging helper
     */
    private function log(string $message, string $level = 'info'): void
    {
        $logger = \UPMarket\Subscriptions\Core\Logger::instance();
        $logger->log($level, "[GatewayServiceProvider] {$message}", 'gateways');
    }
}

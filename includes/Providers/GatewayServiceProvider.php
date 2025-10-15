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
        // Registra o gateway da Rede
        $rede_gateway = new Rede();
        $gateway_manager->register_gateway($rede_gateway);

        // Hook para outros gateways serem registrados
        do_action('upmkt_register_gateways', $gateway_manager);
    }
}

<?php

/**
 * =========================================================================
 * FUNÇÕES GLOBAIS
 * =========================================================================
 */

if (!function_exists('upmkt_get_current_timestamp')) {
    /**
     * Retorna o timestamp atual considerando o fuso horário do Brasil
     *
     * @return int
     */
    function upmkt_get_current_timestamp(): int
    {
        $dt = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        $offset = $dt->getOffset();
        return $dt->getTimestamp() + $offset;
    }
}

if (!function_exists('upmkt_get_current_datetime')) {
    /**
     * Retorna a data/hora atual formatada (Y-m-d H:i:s) no horário do Brasil
     *
     * @return string
     */
    function upmkt_get_current_datetime(): string
    {
        $date = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
        return $date->format('Y-m-d H:i:s');
    }
}

/**
 * Gera um nome de arquivo para exportação de doações
 *
 * @return string Nome do arquivo CSV
 */
function upmkt_generate_csv_filename(): string
{
    $dt = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));

    $dia = $dt->format('d');
    $ano = $dt->format('Y');
    $hora = $dt->format('H');
    $minuto = $dt->format('i');

    $meses_pt = [
        'JAN','FEV','MAR','ABR','MAI','JUN',
        'JUL','AGO','SET','OUT','NOV','DEZ'
    ];
    $mes_num = (int) $dt->format('n'); // 1-12
    $mes = $meses_pt[$mes_num - 1];

    return sprintf('doacoes_%s%s%s_%sh%s.csv', $dia, $mes, $ano, $hora, $minuto);
}


/**
 * Retorna a página Planos com parâmetros opcionais
 *
 * @param string $anchor Âncora para adicionar à URL
 * @return string
 */
function get_plans_page(string $anchor = ''): string
{
    $plans_page_id = get_option('upmkt_plans_page_id');

    if ($plans_page_id && get_post_status($plans_page_id) === 'publish') {
        $url = get_permalink($plans_page_id);

        if (!empty($anchor)) {
            $anchor = ltrim($anchor, '#');
            $url .= '#' . $anchor;
        }

        return $url;
    }

    return home_url();
}

/**
 * Retorna a página Pagamento
 *
 */
function get_checkout_page(): string
{
    $checkout_page_id = get_option('upmkt_checkout_page_id');

    if ($checkout_page_id && get_post_status($checkout_page_id) === 'publish') {
        return get_permalink($checkout_page_id);
    }

    return home_url();
}

/**
 * Retorna a página Minha Área
 *
 */
function get_customer_area_page(): string
{
    $customer_area_page_id = get_option('upmkt_customer_area_page_id');

    if ($customer_area_page_id && get_post_status($customer_area_page_id) === 'publish') {
        return get_permalink($customer_area_page_id);
    }

    return home_url();
}

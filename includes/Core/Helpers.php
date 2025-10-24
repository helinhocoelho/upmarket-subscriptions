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
 * Gera um nome de arquivo para exportação de assinaturas
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

    return sprintf('assinaturas_%s%s%s_%sh%s.csv', $dia, $mes, $ano, $hora, $minuto);
}

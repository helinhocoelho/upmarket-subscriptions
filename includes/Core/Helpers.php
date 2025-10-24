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

<?php

namespace Model\Relatorio;

use DateTimeImmutable;

class RelatorioFinanceiro extends Relatorio
{
    public function gerar(Periodo $periodo, DateTimeImmutable $referencia): Array
    {
        $inicioAtual = $periodo->inicio($referencia);
        $inicioAnterior = $periodo->avancar($inicioAtual, -1);

        $atual = $this->getTotaisConcluidos($periodo, $inicioAtual);
        $anterior = $this->getTotaisConcluidos($periodo, $inicioAnterior);
        $hoje = new DateTimeImmutable("today");

        return [
            "periodo" => $periodo->value,
            "periodoEmAndamento" => $hoje >= $inicioAtual && $hoje < $periodo->avancar($inicioAtual, 1),
            "atual" => $atual,
            "anterior" => $anterior,
            "variacao" => [
                "faturamento" => self::variacaoPercentual($atual["faturamento"], $anterior["faturamento"]),
                "lucro" => self::variacaoPercentual($atual["lucro"], $anterior["lucro"]),
                "pedidos" => self::variacaoPercentual($atual["pedidos"], $anterior["pedidos"]),
                "margemLucroPontos" => round($atual["margemLucro"] - $anterior["margemLucro"], 2),
            ],
            "tendencia" => self::getTendencia($atual["lucro"], $anterior["lucro"]),
        ];
    }

    private static function variacaoPercentual(float $atual, float $anterior): ?float
    {
        if ($anterior == 0.0) {
            return null;
        }

        return round(($atual - $anterior) / abs($anterior) * 100, 2);
    }
}

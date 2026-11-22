<?php

namespace Model\Relatorio;

use DateTimeImmutable;

class EstimativaVendas extends Relatorio
{
    private const MINIMO_PERIODOS_COM_VENDA = 2;

    public function gerar(Periodo $periodo, int $quantidadePeriodos): Array
    {
        $inicioAtual = $periodo->inicio(new DateTimeImmutable());
        $historico = [];

        for ($indice = $quantidadePeriodos; $indice >= 1; $indice--) {
            $historico[] = $this->getTotaisConcluidos($periodo, $periodo->avancar($inicioAtual, -$indice));
        }

        $realizadoAtual = $this->getTotaisConcluidos($periodo, $inicioAtual);
        $periodosComVenda = count(array_filter($historico, fn (Array $item) => $item["pedidos"] > 0));

        $resposta = [
            "periodo" => $periodo->value,
            "historico" => $historico,
            "periodoEstimado" => [
                "rotulo" => $realizadoAtual["rotulo"],
                "inicio" => $realizadoAtual["inicio"],
                "fim" => $realizadoAtual["fim"],
            ],
            "realizadoAteAgora" => $realizadoAtual,
            "estimativa" => null,
            "tendencia" => null,
            "aviso" => null,
        ];

        if ($periodosComVenda < self::MINIMO_PERIODOS_COM_VENDA) {
            $resposta["aviso"] = "São necessários pelo menos " . self::MINIMO_PERIODOS_COM_VENDA . " períodos com vendas concluídas para calcular a estimativa.";
            return $resposta;
        }

        $faturamento = self::regressaoLinear(array_column($historico, "faturamento"));
        $lucro = self::regressaoLinear(array_column($historico, "lucro"));
        $pedidos = self::regressaoLinear(array_column($historico, "pedidos"));

        $resposta["estimativa"] = [
            "faturamento" => round(max(0.0, $faturamento["previsao"]), 2),
            "lucro" => round($lucro["previsao"], 2),
            "pedidos" => (int) round(max(0.0, $pedidos["previsao"])),
        ];
        $resposta["tendencia"] = self::classificarInclinacao($faturamento["inclinacao"], $faturamento["media"]);

        return $resposta;
    }

    private static function regressaoLinear(Array $valores): Array
    {
        $quantidade = count($valores);
        $mediaX = ($quantidade - 1) / 2;
        $mediaY = array_sum($valores) / $quantidade;
        $numerador = 0.0;
        $denominador = 0.0;

        foreach (array_values($valores) as $x => $y) {
            $numerador += ($x - $mediaX) * ($y - $mediaY);
            $denominador += ($x - $mediaX) ** 2;
        }

        $inclinacao = $denominador > 0 ? $numerador / $denominador : 0.0;

        return [
            "previsao" => $mediaY + $inclinacao * ($quantidade - $mediaX),
            "inclinacao" => $inclinacao,
            "media" => $mediaY,
        ];
    }

    private static function classificarInclinacao(float $inclinacao, float $media): string
    {
        $tolerancia = abs($media) * 0.01;

        if ($inclinacao > $tolerancia) {
            return "crescimento";
        }

        if ($inclinacao < -$tolerancia) {
            return "queda";
        }

        return "estavel";
    }
}

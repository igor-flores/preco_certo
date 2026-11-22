<?php

namespace Model\Relatorio;

use DateTimeImmutable;
use Model\Model;
use Model\Pedido\SituacaoPedido;
use Utils\Utils;

abstract class Relatorio extends Model
{
    protected ?int $idUsuario = null;

    protected function getTotaisConcluidos(Periodo $periodo, DateTimeImmutable $inicio): Array
    {
        $this->checkIdUsuario();

        $fim = $periodo->avancar($inicio, 1);

        $sql = "SELECT
                    COUNT(DISTINCT p.id) AS pedidos,
                    COALESCE(SUM(ip.quantidade * ip.preco_unitario), 0) AS faturamento,
                    COALESCE(SUM(ip.quantidade * ip.custo_unitario), 0) AS custo
                FROM
                    pedido p
                    JOIN item_pedido ip ON ip.pedido_id = p.id
                WHERE
                    p.usuario_id = :idUsuario AND
                    p.situacao = :situacao AND
                    p.data_pedido >= :inicio AND
                    p.data_pedido < :fim
        ";
        $this->prepare($sql);
        $this->execute([
            ":idUsuario" => $this->idUsuario,
            ":situacao" => SituacaoPedido::Concluido->value,
            ":inicio" => $inicio->format("Y-m-d H:i:s"),
            ":fim" => $fim->format("Y-m-d H:i:s")
        ]);
        $totais = $this->fetch();

        $faturamento = (float) $totais->faturamento;
        $custo = (float) $totais->custo;
        $lucro = $faturamento - $custo;

        return [
            "rotulo" => $periodo->rotulo($inicio),
            "inicio" => $inicio->format("Y-m-d"),
            "fim" => $fim->modify("-1 day")->format("Y-m-d"),
            "pedidos" => (int) $totais->pedidos,
            "faturamento" => Utils::dinheiro($faturamento),
            "custo" => Utils::dinheiro($custo),
            "lucro" => Utils::dinheiro($lucro),
            "margemLucro" => Utils::percentual($lucro, $faturamento),
        ];
    }

    protected static function getTendencia(float $atual, float $anterior): string
    {
        if ($atual > $anterior) {
            return "crescimento";
        }

        if ($atual < $anterior) {
            return "queda";
        }

        return "estavel";
    }

    public function getIdUsuario(): ?int
    {
        return $this->idUsuario;
    }

    public function setIdUsuario(int $idUsuario): self
    {
        $this->idUsuario = $idUsuario;

        return $this;
    }
}

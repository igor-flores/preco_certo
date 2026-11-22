<?php

namespace Model\Venda;

use Model\Model;
use Model\Pedido\SituacaoPedido;
use Utils\FiltrosPadrao;
use Utils\Utils;

class HistoricoVendas extends Model
{
    protected ?int $idUsuario = null;

    public function getAll(?FiltrosPadrao $FiltrosPadrao = null): Object
    {
        $this->checkIdUsuario();

        if (!$FiltrosPadrao) {
            $FiltrosPadrao = new FiltrosPadrao();
        }

        [$where, $bind] = $this->getCondicoes($FiltrosPadrao->getEspecificos());

        $sqlAgrupado = "SELECT
                            p.id,
                            p.data_pedido AS dataPedido,
                            p.situacao,
                            p.origem,
                            p.valor_total AS valorTotal,
                            p.taxa_entrega AS taxaEntrega,
                            c.id AS idCliente,
                            c.nome AS clienteNome,
                            SUM(ip.quantidade * ip.preco_unitario) AS valorItens,
                            SUM(ip.quantidade * ip.custo_unitario) AS custo
                        FROM
                            pedido p
                            JOIN cliente c ON c.id = p.cliente_id
                            JOIN item_pedido ip ON ip.pedido_id = p.id
                        WHERE
                            {$where}
                        GROUP BY
                            p.id,
                            c.id,
                            c.nome
        ";

        $resumo = $this->getResumo($sqlAgrupado, $bind);

        $sql = "{$sqlAgrupado}
                ORDER BY
                    p.data_pedido DESC,
                    p.id DESC
        ";
        $response = $this->fixGetAll($sql, $FiltrosPadrao, $bind);

        $ids = array_map(fn (Object $venda) => (int) $venda->id, $response->data);
        $itensPorPedido = $this->getItens($ids);

        $response->data = array_map(function (Object $venda) use ($itensPorPedido): Object {
            $valorItens = (float) $venda->valorItens;
            $custo = (float) $venda->custo;
            $lucro = $valorItens - $custo;

            return (Object) [
                "idPedido" => (int) $venda->id,
                "dataPedido" => $venda->dataPedido,
                "situacao" => $venda->situacao,
                "rotuloSituacao" => SituacaoPedido::from($venda->situacao)->rotulo(),
                "origem" => $venda->origem,
                "cliente" => (Object) [
                    "id" => (int) $venda->idCliente,
                    "nome" => $venda->clienteNome,
                ],
                "itens" => $itensPorPedido[$venda->id] ?? [],
                "valorItens" => Utils::dinheiro($valorItens),
                "taxaEntrega" => (float) $venda->taxaEntrega,
                "valorTotal" => (float) $venda->valorTotal,
                "custo" => Utils::dinheiro($custo),
                "lucro" => Utils::dinheiro($lucro),
                "margemLucro" => Utils::percentual($lucro, $valorItens),
            ];
        }, $response->data);

        $response->resumo = $resumo;

        return $response;
    }

    private function getCondicoes(Object $especificos): Array
    {
        $where = ["p.usuario_id = :idUsuario"];
        $bind = [":idUsuario" => $this->idUsuario];

        if (isset($especificos->situacao)) {
            $where[] = "p.situacao = :situacao";
            $bind[":situacao"] = $especificos->situacao;
        } else {
            $where[] = "p.situacao <> :situacaoCancelado";
            $bind[":situacaoCancelado"] = SituacaoPedido::Cancelado->value;
        }

        if (isset($especificos->idCliente)) {
            $where[] = "p.cliente_id = :idCliente";
            $bind[":idCliente"] = $especificos->idCliente;
        }

        if (isset($especificos->nomeCliente)) {
            $nomeCliente = Utils::escapeLike($especificos->nomeCliente);
            $where[] = "c.nome LIKE :nomeCliente";
            $bind[":nomeCliente"] = "%{$nomeCliente}%";
        }

        if (isset($especificos->idProduto)) {
            $where[] = "EXISTS (SELECT 1 FROM item_pedido filtro WHERE filtro.pedido_id = p.id AND filtro.produto_id = :idProduto)";
            $bind[":idProduto"] = $especificos->idProduto;
        }

        if (isset($especificos->dataInicio)) {
            $where[] = "p.data_pedido >= :dataInicio";
            $bind[":dataInicio"] = $especificos->dataInicio->format("Y-m-d 00:00:00");
        }

        if (isset($especificos->dataFim)) {
            $where[] = "p.data_pedido < :dataFim";
            $bind[":dataFim"] = $especificos->dataFim->modify("+1 day")->format("Y-m-d 00:00:00");
        }

        return [implode(" AND". PHP_EOL, $where), $bind];
    }

    private function getResumo(string $sqlAgrupado, Array $bind): Object
    {
        $sql = "SELECT
                    COUNT(*) AS pedidos,
                    COALESCE(SUM(valorTotal), 0) AS valorTotal,
                    COALESCE(SUM(valorItens), 0) AS valorItens,
                    COALESCE(SUM(custo), 0) AS custo
                FROM
                    ({$sqlAgrupado}) vendas
        ";
        $this->prepare($sql);
        $this->execute($bind);
        $resumo = $this->fetch();

        $valorItens = (float) $resumo->valorItens;
        $custo = (float) $resumo->custo;
        $lucro = $valorItens - $custo;

        return (Object) [
            "pedidos" => (int) $resumo->pedidos,
            "valorTotal" => Utils::dinheiro((float) $resumo->valorTotal),
            "valorItens" => Utils::dinheiro($valorItens),
            "custo" => Utils::dinheiro($custo),
            "lucro" => Utils::dinheiro($lucro),
            "margemLucro" => Utils::percentual($lucro, $valorItens),
        ];
    }

    private function getItens(Array $idsPedidos): Array
    {
        if (!$idsPedidos) {
            return [];
        }

        $bind = [];
        $marcadores = self::bindList("idPedido", $idsPedidos, $bind);

        $sql = "SELECT
                    ip.pedido_id AS idPedido,
                    ip.produto_id AS idProduto,
                    pr.nome,
                    ip.quantidade
                FROM
                    item_pedido ip
                    JOIN produto pr ON pr.id = ip.produto_id
                WHERE
                    ip.pedido_id IN ({$marcadores})
                ORDER BY
                    ip.id
        ";
        $this->prepare($sql);
        $this->execute($bind);

        $itens = [];
        foreach ($this->fetchAll() as $item) {
            $itens[(int) $item->idPedido][] = (Object) [
                "idProduto" => (int) $item->idProduto,
                "nome" => $item->nome,
                "quantidade" => (int) $item->quantidade,
            ];
        }

        return $itens;
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

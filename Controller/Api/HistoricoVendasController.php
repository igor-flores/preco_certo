<?php

namespace Controller\Api;

use Model\Exception\ValidacaoException;
use Model\Pedido\SituacaoPedido;
use Model\Venda\HistoricoVendas;
use Utils\FiltrosPadrao;

class HistoricoVendasController extends ApiController
{
    protected function get(): Array
    {
        $Query = $this->query();
        $dataInicio = $Query->data("dataInicio", "data inicial");
        $dataFim = $Query->data("dataFim", "data final");

        if ($dataInicio !== null && $dataFim !== null && $dataInicio > $dataFim) {
            throw new ValidacaoException("A data inicial deve ser anterior ou igual à data final.", "dataInicio");
        }

        $FiltrosPadrao = (new FiltrosPadrao())
            ->setLimit($Query->inteiro("limite", "limite", false, 1, 100) ?? 50)
            ->setOffset($Query->inteiro("offset", "offset", false, 0) ?? 0)
            ->setEspecifico("idCliente", $Query->inteiro("idCliente", "cliente", false))
            ->setEspecifico("nomeCliente", $Query->texto("cliente", "nome do cliente", false, 120))
            ->setEspecifico("idProduto", $Query->inteiro("idProduto", "produto", false))
            ->setEspecifico("dataInicio", $dataInicio)
            ->setEspecifico("dataFim", $dataFim)
            ->setEspecifico("situacao", $Query->enum("situacao", "situação", SituacaoPedido::class, false)?->value);

        $response = (new HistoricoVendas())->setIdUsuario($this->idUsuario())->getAll($FiltrosPadrao);

        return [
            "vendas" => $response->data,
            "resumo" => $response->resumo,
            "limite" => $response->limit,
            "offset" => $response->offset,
        ];
    }
}

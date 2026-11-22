<?php

namespace Controller\Api;

use Model\Pedido\OrigemPedido;
use Model\Pedido\Pedido;
use Model\Pedido\SituacaoPedido;
use Utils\FiltrosPadrao;

class PedidoController extends ApiController
{
    use DadosPedido;

    protected function get(): Array|Object
    {
        $Query = $this->query();
        $Pedido = (new Pedido())->setIdUsuario($this->idUsuario());

        if ($Query->tem("id")) {
            return $Pedido->setId($this->idDaQuery())->getDetalhado();
        }

        $FiltrosPadrao = (new FiltrosPadrao())
            ->setLimit($Query->inteiro("limite", "limite", false, 1, 100) ?? 50)
            ->setOffset($Query->inteiro("offset", "offset", false, 0) ?? 0)
            ->setEspecifico("situacao", $Query->enum("situacao", "situação", SituacaoPedido::class, false)?->value)
            ->setEspecifico("origem", $Query->enum("origem", "origem", OrigemPedido::class, false)?->value);

        $response = $Pedido->getAll($FiltrosPadrao);

        return [
            "pedidos" => $response->data,
            "total" => $response->total,
            "limite" => $response->limit,
            "offset" => $response->offset,
        ];
    }

    protected function post(): Object
    {
        $Corpo = $this->corpo();

        $Pedido = (new Pedido())
            ->setIdUsuario($this->idUsuario())
            ->setOrigem(OrigemPedido::Manual)
            ->setTaxaEntrega($Corpo->decimal("taxaEntrega", "taxa de entrega", false, true))
            ->setSituacao($Corpo->enum("situacao", "situação", SituacaoPedido::class, false) ?? SituacaoPedido::AguardandoConfirmacao)
            ->setDataPedido($Corpo->dataHora("dataPedido", "data do pedido"));

        $this->preencherPedido($Pedido, $Corpo)->insert();

        return $this->criado($Pedido->getDetalhado());
    }

    protected function put(): Object
    {
        $Pedido = (new Pedido($this->idDaQuery()))->setIdUsuario($this->idUsuario());
        $Pedido->alterarSituacao($this->corpo()->enum("situacao", "situação", SituacaoPedido::class));

        return $Pedido->getDetalhado();
    }
}

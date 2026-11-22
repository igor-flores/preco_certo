<?php

namespace Controller\Api;

use Model\Cardapio\Cardapio;
use Model\Exception\AppException;
use Model\Pedido\OrigemPedido;
use Model\Pedido\Pedido;

class PedidoPublicoController extends ApiController
{
    use DadosPedido;

    protected bool $publico = true;

    protected function get(): Object
    {
        $hash = $this->query()->texto("hash", "código de acompanhamento", true, 64);

        return (new Pedido())->setHashAcompanhamento($hash)->getAcompanhamento();
    }

    protected function post(): Object
    {
        $link = $this->query()->texto("link", "link do cardápio", true, 80);
        $cardapio = (new Cardapio())->setLinkPublico($link)->get();

        if (!isset($cardapio->id)) {
            throw new AppException("Cardápio não encontrado.", 404);
        }

        $Pedido = (new Pedido())
            ->setIdUsuario($cardapio->idUsuario)
            ->setOrigem(OrigemPedido::Cardapio);

        $this->preencherPedido($Pedido, $this->corpo())->insert();

        return $this->criado((new Pedido())->setHashAcompanhamento($Pedido->getHashAcompanhamento())->getAcompanhamento());
    }
}

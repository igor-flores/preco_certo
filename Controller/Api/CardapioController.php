<?php

namespace Controller\Api;

use Model\Cardapio\Cardapio;

class CardapioController extends ApiController
{
    protected function get(): Object
    {
        return (new Cardapio())->setIdUsuario($this->idUsuario())->getPainel();
    }

    protected function put(): Object
    {
        $Corpo = $this->corpo();

        $Cardapio = new Cardapio();
        $Cardapio->setIdUsuario($this->idUsuario())
            ->setCorPrimaria($Corpo->cor("corPrimaria", "cor primária"))
            ->setCorSecundaria($Corpo->cor("corSecundaria", "cor secundária"))
            ->setEndereco($Corpo->texto("endereco", "endereço", false, 255))
            ->setFormasPagamento($Corpo->listaTextos("formasPagamento", "formas de pagamento", 10, 40))
            ->setTaxaEntrega($Corpo->decimal("taxaEntrega", "taxa de entrega", false, true) ?? 0.0)
            ->setProdutos($Corpo->listaInteiros("produtos", "produtos"))
            ->save();

        return $Cardapio->getPainel();
    }
}

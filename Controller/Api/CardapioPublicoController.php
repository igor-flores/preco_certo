<?php

namespace Controller\Api;

use Model\Cardapio\Cardapio;

class CardapioPublicoController extends ApiController
{
    protected bool $publico = true;

    protected function get(): Object
    {
        $link = $this->query()->texto("link", "link do cardápio", true, 80);

        return (new Cardapio())->setLinkPublico($link)->getPublico();
    }
}

<?php

namespace Controller\Cardapio;

use Controller\Controller;
use Render\Render;

class CardapioController extends Controller
{
    public function index(): Render
    {
        return $this->pagina("cardapio", "Cardápio", "_painel");
    }
}

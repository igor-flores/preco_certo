<?php

namespace Controller\Vendas;

use Controller\Controller;
use Render\Render;

class VendasController extends Controller
{
    public function index(): Render
    {
        return $this->pagina("vendas", "Histórico de vendas", "_painel");
    }
}

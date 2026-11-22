<?php

namespace Controller\Produtos;

use Controller\Controller;
use Render\Render;

class ProdutosController extends Controller
{
    public function index(): Render
    {
        return $this->pagina("produtos", "Produtos", "_painel");
    }
}

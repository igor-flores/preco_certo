<?php

namespace Controller\Relatorios;

use Controller\Controller;
use Render\Render;

class RelatoriosController extends Controller
{
    public function index(): Render
    {
        return $this->pagina("relatorios", "Relatórios", "_painel");
    }
}

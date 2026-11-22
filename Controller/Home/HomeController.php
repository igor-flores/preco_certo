<?php

namespace Controller\Home;

use Controller\Controller;
use Render\Render;

class HomeController extends Controller
{
    public function index(): Render
    {
        return $this->pagina("inicio", "Início", "_painel");
    }
}

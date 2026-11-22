<?php

namespace Controller\MateriasPrimas;

use Controller\Controller;
use Render\Render;

class MateriasPrimasController extends Controller
{
    public function index(): Render
    {
        return $this->pagina("materias-primas", "Matérias-primas", "_painel");
    }
}

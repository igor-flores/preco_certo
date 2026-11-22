<?php

namespace Controller;

use Render\Render;

abstract class Controller
{
    public function runMiddleware(string $method, array $args): ?Render
    {
        foreach ($this->middleware($method) as $Middleware) {
            $Middleware = new $Middleware($this, $method, $args);

            $result = $Middleware();
            if ($result instanceof Render) {
                return $result;
            }
        }

        return null;
    }

    protected function middleware(?string $method = null): array
    {
        return [];
    }

    protected function pagina(string $view, string $titulo, string $template = "_painel"): Render
    {
        return (new Render($view, $template))->setTitulo($titulo);
    }
}

<?php

namespace Middleware;

use Controller\Controller;
use Render\Render;

abstract class Middleware
{
    protected Controller $controller;
    protected string $route;
    protected array $args;

    public function __construct(Controller $controller, string $route, array $args)
    {
        $this->controller = $controller;
        $this->route = $route;
        $this->args = $args;
    }

    public abstract function __invoke(): ?Render;
}

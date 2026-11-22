<?php

namespace Core;

use Controller\Controller;
use Model\Configuration\Configuration;
use Model\Exception\AppException;
use Model\Exception\EndPointException;
use Render\Render;
use Throwable;
use Utils\Format;
use Utils\Sessao;
use Utils\Utils;

class Core
{
    private const MODULO_API = "\\Controller\\Api\\";

    private const MODULOS_PUBLICOS = [
        "\\Controller\\Api\\",
        "\\Controller\\Loja\\",
        "\\Controller\\Acompanhar\\",
    ];

    private const MODULOS_ACESSO = [
        "\\Controller\\Entrar\\",
        "\\Controller\\Cadastro\\",
    ];

    private string $module = "\\Controller\\Home\\";
    private string $controller = "HomeController";
    private string $method = "index";
    private Array $args = [];

    public function setModule(string $module): self
    {
        if (!$module) {
            return $this;
        }

        $module = Format::fixCamelCase($module);
        $module = ucfirst($module);
        $this->module = "\Controller\\{$module}\\";
        $this->setController($module);

        return $this;
    }

    public function setController(string $controller): self
    {
        if (!$controller) {
            $controller = str_replace(["\Controller", "\\"], "", $this->module);
        }

        $controller = Format::fixCamelCase($controller);
        $controller = ucfirst($controller);
        $this->controller = "{$controller}Controller";

        return $this;
    }

    public function setMethod(string $method): self
    {
        if (!$method) {
            return $this;
        }

        $method = Format::fixCamelCase($method);
        $this->method = $method;

        return $this;
    }

    public function setArgs(Array $args): self
    {
        if (!$args) {
            return $this;
        }
        $this->args = $args;

        return $this;
    }

    public function execute(): void
    {
        try {
            $this->checkLogin();

            $Controller = "{$this->module}{$this->controller}";

            if (str_starts_with($this->method, "__") || !method_exists($Controller, $this->method)) {
                throw new EndPointException();
            }

            $Controller = new $Controller;

            if (!is_callable([$Controller, $this->method])) {
                throw new EndPointException();
            }

            if ($Controller instanceof Controller) {
                $preprocessed = $Controller->runMiddleware($this->method, $this->args);
                if ($preprocessed) {
                    echo $preprocessed->show();
                    return;
                }
            }

            $response = $Controller->{$this->method}($this->args);
            $this->responder($response);
        } catch (Throwable $e) {
            $this->responderErro($e);
        }
    }

    public function checkLogin(): void
    {
        if (in_array($this->module, self::MODULOS_PUBLICOS, true)) {
            return;
        }

        $hasLogin = Sessao::idUsuario() !== null;
        $moduloAcesso = in_array($this->module, self::MODULOS_ACESSO, true);

        if ($hasLogin && $moduloAcesso) {
            header("Location: ". _PATH);
            exit;
        }

        if (!$hasLogin && !$moduloAcesso) {
            header("Location: ". _PATH. "entrar/");
            exit;
        }
    }

    public function autoSetByURL(?string $url): self
    {
        if (!$url) {
            return $this;
        }

        $url = explode("/", $url);

        $moduleBefore = $this->module;
        if (isset($url[0])) {
            $moduleBefore = $url[0];
            $this->setModule(array_shift($url));
        }

        if (isset($url[0])) {

            $urlBefore = $url[0];
            $this->setController(array_shift($url));
            $caminho = __DIR__. "/..". str_replace("\\", "/", $this->module). "{$this->controller}.php";
            if (!file_exists($caminho)) {
                $this->setController($moduleBefore);
                array_unshift($url, $urlBefore);
            }
        }

        if (isset($url[0])) {
            $method = $url[0];
            $shift = true;
            if (strpos(strtoupper($this->module), "\\API\\") === false) {
                $Controller = "{$this->module}{$this->controller}";

                if (!method_exists($Controller, Format::fixCamelCase($method))) {
                    $method = "index";
                    $shift = false;
                }
            }

            if ($shift) {
                array_shift($url);
            }

            $this->setMethod($method);
        }

        if (isset($url[0])) {
            $this->setArgs($url);
        }

        return $this;
    }

    private function responder(mixed $response): void
    {
        if ($response instanceof Render) {
            header("Content-Type: text/html; charset=UTF-8");
            echo $response->show();
            return;
        }

        header("Content-Type: application/json; charset=UTF-8");

        if ($response === null) {
            http_response_code(204);
            return;
        }

        if (is_string($response)) {
            $response = ["message" => $response];
        }

        echo Utils::sendJSON($response);
    }

    private function responderErro(Throwable $e): void
    {
        if ($this->module !== self::MODULO_API && $e instanceof AppException && $e->getCode() === 404) {
            http_response_code(404);
            header("Content-Type: text/html; charset=UTF-8");
            echo (new Render("404", "_acesso"))->setTitulo("Página não encontrada")->show();
            return;
        }

        header("Content-Type: application/json; charset=UTF-8");

        if ($e instanceof AppException) {
            http_response_code($e->getCode());
            echo Utils::sendJSON($e->toArray());
            return;
        }

        $origem = "{$e->getFile()}:{$e->getLine()}";
        Utils::logError([
            "error" => $e->getMessage(),
            "backtrace" => $origem,
            "allTrace" => explode("\n", $e->getTraceAsString()),
        ]);

        http_response_code(500);
        $response = ["error" => "Erro inesperado. Tente novamente em instantes."];

        if (Configuration::get()->isLocal()) {
            $response["debug"] = [
                "error" => $e->getMessage(),
                "backtrace" => $origem,
                "allTrace" => explode("\n", $e->getTraceAsString()),
            ];
        }

        echo Utils::sendJSON($response);
    }
}

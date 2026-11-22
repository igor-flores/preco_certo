<?php

namespace Render;

use Model\Exception\AppException;

class Render
{
    private string $titulo = "";

    public function __construct(
        private string $view,
        private string $template = "_painel"
    ) {}

    public function setTitulo(string $titulo): self
    {
        $this->titulo = $titulo;

        return $this;
    }

    public function show(): string
    {
        $template = $this->lerArquivo($this->template);
        $conteudo = $this->lerArquivo($this->view);

        $html = str_replace("{{CONTEUDO}}", $conteudo, $template);

        return strtr($html, [
            "{{TITULO}}" => htmlspecialchars($this->titulo, ENT_QUOTES),
            "{{PAGINA}}" => htmlspecialchars($this->view, ENT_QUOTES),
            "{{PATH}}" => htmlspecialchars(_PATH, ENT_QUOTES),
            "{{VERSAO}}" => (string) _VERSION_CACHE,
        ]);
    }

    private function lerArquivo(string $nome): string
    {
        $caminho = dirname(__DIR__) . "/View/{$nome}.html";

        if (!is_file($caminho)) {
            throw new AppException("Página não encontrada.", 404);
        }

        return (string) file_get_contents($caminho);
    }
}

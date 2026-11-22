<?php

namespace Utils;

use Model\Configuration\Configuration;

final class Sessao
{
    private const NOME = "precocerto";
    private const TEMPO_INATIVIDADE = 10800;

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set("session.use_strict_mode", "1");
        ini_set("session.gc_maxlifetime", (string) self::TEMPO_INATIVIDADE);

        session_name(self::NOME);
        session_set_cookie_params([
            "lifetime" => 0,
            "path" => "/",
            "secure" => Configuration::isHttps(),
            "httponly" => true,
            "samesite" => "Lax",
        ]);
        session_start();

        self::controlarInatividade();
    }

    public static function entrar(int $idUsuario): void
    {
        self::iniciar();
        session_regenerate_id(true);

        $_SESSION["idUsuario"] = $idUsuario;
        $_SESSION["ultimaAtividade"] = time();
    }

    public static function sair(): void
    {
        self::iniciar();
        $_SESSION = [];

        $parametros = session_get_cookie_params();
        setcookie(session_name(), "", [
            "expires" => time() - 3600,
            "path" => $parametros["path"],
            "secure" => $parametros["secure"],
            "httponly" => true,
            "samesite" => "Lax",
        ]);

        session_destroy();
    }

    public static function idUsuario(): ?int
    {
        self::iniciar();
        $idUsuario = $_SESSION["idUsuario"] ?? null;

        return is_int($idUsuario) ? $idUsuario : null;
    }

    private static function controlarInatividade(): void
    {
        if (!isset($_SESSION["idUsuario"])) {
            return;
        }

        $ultimaAtividade = $_SESSION["ultimaAtividade"] ?? 0;

        if (time() - $ultimaAtividade > self::TEMPO_INATIVIDADE) {
            $_SESSION = [];
            session_regenerate_id(true);
            return;
        }

        $_SESSION["ultimaAtividade"] = time();
    }
}

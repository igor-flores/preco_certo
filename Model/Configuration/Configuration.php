<?php

namespace Model\Configuration;

final class Configuration
{
    private static ?self $Instancia = null;

    private function __construct(
        public readonly string $appEnv,
        public readonly string $dbHost,
        public readonly int $dbPort,
        public readonly string $dbName,
        public readonly string $dbUser,
        public readonly string $dbPass,
        public readonly string $pathLogs,
    ) {}

    public static function get(): self
    {
        return self::$Instancia ??= new self(
            self::env("APP_ENV", "production"),
            self::env("DB_HOST", "localhost"),
            (int) self::env("DB_PORT", "3306"),
            self::env("DB_NAME", "preco_certo"),
            self::env("DB_USER", "root"),
            self::env("DB_PASS", ""),
            dirname(__DIR__, 2) . "/Logs/",
        );
    }

    public function isLocal(): bool
    {
        return $this->appEnv === "local";
    }

    public static function isHttps(): bool
    {
        $https = $_SERVER["HTTPS"] ?? "";
        $protocoloProxy = $_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "";

        return ($https !== "" && $https !== "off") || strtolower($protocoloProxy) === "https";
    }

    private static function env(string $nome, string $padrao): string
    {
        $valor = getenv($nome);

        if ($valor === false || $valor === "") {
            return $padrao;
        }

        return $valor;
    }
}

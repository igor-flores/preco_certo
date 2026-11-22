<?php

use Model\Configuration\Configuration;
use Utils\Utils;

require_once __DIR__ . "/autoload.php";

date_default_timezone_set("America/Sao_Paulo");
error_reporting(E_ALL);
ini_set("display_errors", Configuration::get()->isLocal() ? "1" : "0");

header("Content-Type: text/html; charset=UTF-8");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: same-origin");

if (Configuration::isHttps()) {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

$path = str_replace("index.php", "", $_SERVER["PHP_SELF"]);

define("_PATH", $path);
define("_VERSION_CACHE", time());

register_shutdown_function(function (): void {
    $errosFatais = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    $erro = error_get_last();

    if ($erro === null || !in_array($erro["type"], $errosFatais, true)) {
        return;
    }

    Utils::logError(["lastError" => $erro]);
});

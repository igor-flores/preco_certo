<?php

require_once __DIR__ . "/config.php";

$Core = new Core\Core();
$Core->autoSetByURL($_GET["pag"] ?? null);
$Core->execute();

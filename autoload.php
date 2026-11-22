<?php

spl_autoload_register(function ($className) {

    $className = str_replace('\\', '/', $className);
    $className = __DIR__. "/{$className}.php";

    if (!file_exists($className)) {
        return;
    }

    require_once $className;
});
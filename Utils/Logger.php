<?php

namespace Utils;

class Logger
{
    public function __construct(
        private string $filename = "log",
        private string $format = "txt"
    ) {}

    public function __invoke(mixed $message, bool $append = false): void
    {
        file_put_contents($this->getPath(), print_r($message, true) . PHP_EOL, $append ? 8 : 0);
    }

    public function clear(): void
    {
        file_put_contents($this->getPath(), "");
    }

    private function getPath(): String
    {
        return "Logs/{$this->filename}.{$this->format}";
    }

    public static function log(mixed $message, bool $append = false): void
    {
        $Logger = new Logger();
        $Logger($message, $append);
    }

    public static function clearLog(): void
    {
        $Logger = new Logger();
        $Logger->clear();
    }
}

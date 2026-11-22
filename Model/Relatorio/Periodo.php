<?php

namespace Model\Relatorio;

use DateTimeImmutable;

enum Periodo: string
{
    case Semana = "semana";
    case Mes = "mes";
    case Ano = "ano";

    public function inicio(DateTimeImmutable $data): DateTimeImmutable
    {
        return match ($this) {
            self::Semana => $data->modify("monday this week")->setTime(0, 0),
            self::Mes => $data->modify("first day of this month")->setTime(0, 0),
            self::Ano => $data->setDate((int) $data->format("Y"), 1, 1)->setTime(0, 0),
        };
    }

    public function avancar(DateTimeImmutable $inicio, int $quantidade): DateTimeImmutable
    {
        $unidade = match ($this) {
            self::Semana => "week",
            self::Mes => "month",
            self::Ano => "year",
        };

        return $inicio->modify("{$quantidade} {$unidade}");
    }

    public function rotulo(DateTimeImmutable $inicio): string
    {
        $data = $inicio->format("d/m/Y");

        return match ($this) {
            self::Semana => "Semana de {$data}",
            self::Mes => $inicio->format("m/Y"),
            self::Ano => $inicio->format("Y"),
        };
    }
}

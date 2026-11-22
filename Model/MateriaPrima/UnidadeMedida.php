<?php

namespace Model\MateriaPrima;

enum UnidadeMedida: string
{
    case Miligrama = "mg";
    case Grama = "g";
    case Quilograma = "kg";
    case Mililitro = "ml";
    case Litro = "l";
    case Unidade = "un";
    case Duzia = "dz";

    public function grupo(): string
    {
        return match ($this) {
            self::Miligrama, self::Grama, self::Quilograma => "massa",
            self::Mililitro, self::Litro => "volume",
            self::Unidade, self::Duzia => "unidade",
        };
    }

    public function fator(): float
    {
        return match ($this) {
            self::Miligrama => 0.001,
            self::Grama, self::Mililitro, self::Unidade => 1.0,
            self::Quilograma, self::Litro => 1000.0,
            self::Duzia => 12.0,
        };
    }

    public function compativelCom(self $outra): bool
    {
        return $this->grupo() === $outra->grupo();
    }

    public function converter(float $quantidade, self $destino): float
    {
        return $quantidade * $this->fator() / $destino->fator();
    }
}

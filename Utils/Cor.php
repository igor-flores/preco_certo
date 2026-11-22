<?php

namespace Utils;

final class Cor
{
    private const BRANCO = "#FFFFFF";
    private const PRETO = "#000000";

    public static function textoLegivel(string $corFundo): string
    {
        $contrasteBranco = self::contraste($corFundo, self::BRANCO);
        $contrastePreto = self::contraste($corFundo, self::PRETO);

        return $contrasteBranco >= $contrastePreto ? self::BRANCO : self::PRETO;
    }

    public static function contraste(string $cor1, string $cor2): float
    {
        $luminancia1 = self::luminancia($cor1);
        $luminancia2 = self::luminancia($cor2);

        $maior = max($luminancia1, $luminancia2);
        $menor = min($luminancia1, $luminancia2);

        return round(($maior + 0.05) / ($menor + 0.05), 2);
    }

    private static function luminancia(string $cor): float
    {
        $hex = ltrim($cor, "#");
        $canais = [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];

        $linear = array_map(function (int $canal): float {
            $valor = $canal / 255;

            return $valor <= 0.03928 ? $valor / 12.92 : (($valor + 0.055) / 1.055) ** 2.4;
        }, $canais);

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}

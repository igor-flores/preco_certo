<?php

namespace Utils;

class Format
{
    public static function amount(int|string $amount): string
    {
        return number_format($amount, 2, ",", ".");
    }

    public static function amountPretty(int|string $amount): string
    {
        $amount = self::amount($amount);

        list($amount, $cents) = explode(",", $amount);
        return "
            <span class=\"amount\">
                <span>R$</span>
                <span>{$amount}</span><span>,{$cents}</span>
            </span>
        ";
    }

    public static function date(string $data): string
    {
        if (strpos($data, "/") !== false) {
            return $data;
        }

        if ($data == "0000-00-00" || $data == "") {
            return "";
        }

        list($ano, $mes, $dia) = explode("-", $data);
        return "{$dia}/{$mes}/{$ano}";
    }

    public static function dateTime(string $dateTime): string
    {
        $dateTime = strtotime($dateTime);

        return date("d/m/Y H:i", $dateTime);
    }

    public static function phone(string $phone): string
    {
        $phone = self::onlyNumbers($phone);

        $phone = preg_replace("/(\d{2})(.*)/m", "($1) $2", $phone);
        $phone = preg_replace("/ \d{5}/m", "$0-", $phone);

        return $phone;
    }

    public static function compileString(string $string, int $size): string
    {
        if (strlen($string) <= $size) {
            return $string;
        }

        $stringCompile = "[...]";
        $size = ($size - strlen($stringCompile));
        $string = substr($string, 0, $size);

        $string = explode(" ", $string);
        array_pop($string);

        $string = implode(" ", $string);
        
        return $string. $stringCompile;
    }

    public static function onlyNumbers(string $string): string
    {
        return preg_replace("/[^\d]/m", "", $string);
    }

    public static function fixCamelCase(string $word): string
    {
        if (strpos($word, "-") !== false) {
            $words = explode("-", $word);
        } elseif (strpos($word, "_") !== false) {
            $words = explode("_", $word);
        } else {
            return $word;
        }

        foreach ($words as $key => &$word) {
            $word = strtolower($word);
            $word = $key == 0 ? $word : ucfirst($word);
        }

        $word = implode("", $words);

        return $word;
    }

    public static function slug(string $texto, int $tamanhoMaximo = 60): string
    {
        $acentos = [
            "á" => "a", "à" => "a", "â" => "a", "ã" => "a", "ä" => "a",
            "é" => "e", "è" => "e", "ê" => "e", "ë" => "e",
            "í" => "i", "ì" => "i", "î" => "i", "ï" => "i",
            "ó" => "o", "ò" => "o", "ô" => "o", "õ" => "o", "ö" => "o",
            "ú" => "u", "ù" => "u", "û" => "u", "ü" => "u",
            "ç" => "c", "ñ" => "n",
        ];

        $texto = strtr(mb_strtolower($texto), $acentos);
        $texto = preg_replace("/[^a-z0-9]+/", "-", $texto);
        $texto = trim(substr(trim($texto, "-"), 0, $tamanhoMaximo), "-");

        return $texto ?: "cardapio";
    }

    public static function CPFCNPJ(string $value): string
    {
        $value = ltrim($value, '0');
        $value = preg_replace("/\D/", '', $value);

        if (strlen($value) <= 11) {
            return preg_replace("/(\d{3})(\d{3})(\d{3})(\d{2})/", "\$1.\$2.\$3-\$4", str_pad($value, 11, "0", STR_PAD_LEFT));
        }

        if (strlen($value) <= 14) {
            return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "\$1.\$2.\$3/\$4-\$5", str_pad($value, 14, "0", STR_PAD_LEFT));
        }

        return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "\$1.\$2.\$3/\$4-\$5", substr($value, strlen($value) - 14));
    }
}

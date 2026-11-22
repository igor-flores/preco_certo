<?php

namespace Utils;

use Model\Configuration\Configuration;

class Utils
{
    public static function reorderKeysRecursively(Array $array): Array
    {
        $i = 0;
        $newArray = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = Utils::reorderKeysRecursively($value);
            }

            if (is_int($key)) {
                $newArray[$i] = $value;
                $i++;
            } else {
                $newArray[$key] = $value;
            }
        }

        return $newArray;
    }

    public static function sendJSON(Array|Object $data = []): string
    {
        return json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION|JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public static function dump($array = []): string
    {
        $array = print_r($array, true);
        return "<pre>{$array}</pre>";
    }

    public static function randomLetter(bool $upper = false): string
    {
        $minChar = $upper ? 65 : 97;
        $maxChar = $upper ? 90 : 122;

        return chr(rand($minChar, $maxChar));
    }

    public static function randomSymbol(): string
    {
        return chr(rand(42, 47));
    }

    public static function randomBoolean(): bool
    {
        return rand(0, 1);
    }

    public static function randomColor(): string
    {
        $chars = "ABCDEF0123456789";
        $color = "#";

        for ($i = 0; $i < 6; $i++) {
            $color .= $chars[rand(0, strlen($chars) - 1)];
        }

        return $color;
    }

    public static function darkColor($color): string
    {
        if (strpos($color, "#") === false) {
            $color = "#{$color}";
        }
        
        $percAjuste = -0.5; //-1 - +1 // valores proximos ao extremo distorcem a cor

        $newhexColor = [];
        $hexColor = [
            base_convert(substr($color, 1, 2), 16, 10),
            base_convert(substr($color, 3, 2), 16, 10),
            base_convert(substr($color, 5, 2), 16, 10)
        ];

        foreach ($hexColor as $key => &$hex) {
            $limiteAjuste = 255 - $hex;
            $quantiaAjuste = $limiteAjuste * $percAjuste;
            $hex = round($hex + $quantiaAjuste);
            $newhexColor[$key] = base_convert(
                str_pad(round($hex + $quantiaAjuste), 2, "0", STR_PAD_RIGHT),
                10, 16
            );
        }

        $color = substr(implode('', $newhexColor), 0, 6);

        return "#{$color}";
    }

    public static function getFirstName(string $name): string
    {
        $name = explode(' ', $name);
        return $name[0];
    }

    public static function joinLines(string $string = ""): string
    {
        $string = str_replace(["\r","\n"], "", $string);
        $string = explode(" ", $string);
        $string = array_diff($string, [""]);
        $string = implode(" ", $string);

        return $string;
    }

    public static function getMonthYear(string $dateTime): string
    {
        $dateTime = explode(" ", $dateTime);
        $dateTime = explode("-", $dateTime[0]);

        $year = intval($dateTime[0]);
        $month = self::getMonth($dateTime[1]);

        return "{$month}/{$year}";
    }

    public static function getMonth(int $month): ?string
    {
        $month = intval($month);
        switch ($month) {
            case 1: return "Janeiro";
            case 2: return "Fevereiro";
            case 3: return "Março";
            case 4: return "Abril";
            case 5: return "Maio";
            case 6: return "Junho";
            case 7: return "Julho";
            case 8: return "Agosto";
            case 9: return "Setembro";
            case 10: return "Outubro";
            case 11: return "Novembro";
            case 12: return "Dezembro";
        }

        return null;
    }

    public static function getDatesInThisWeek(): Object
    {
        $lastDayWeek = 6;
        $nowDayWeek = date('w');

        $dateNow = date("Y-m-d");
        $daysToEndWeek = $lastDayWeek - $nowDayWeek;

        return (Object) [
            "begin" => date("Y-m-d", strtotime($dateNow. "- {$nowDayWeek} days")),
            "end" => date("Y-m-d", strtotime($dateNow. "+ {$daysToEndWeek} days"))
        ];
    }

    public static function diffHorasMinutos(string $hora1, string $hora2): string
    {
        if (strpos($hora1, ":") === false) {
            $hora1 = "{$hora1}:00:00";
        }
        if (strpos($hora2, ":") === false) {
            $hora2 = "{$hora2}:00:00";
        }

        list($h1, $m1, $s1) = explode(':', $hora1);
        list($h2, $m2, $s2) = explode(':', $hora2);

        $date1 = mktime($h1, $m1, 0, 1, 1, 2000);
        $date2 = mktime($h2, $m2, 0, 1, 1, 2000);

        $diffSec = $date2 - $date1;
        $totalMin = floor($diffSec / 60);

        $horas = floor($totalMin / 60);
        if ($horas < 0) {
            $horas++;
        }
        $minutos = $totalMin % 60;

        $segundos = $s2 - $s1;
        if ($totalMin < 0) {
            $minutos++;
            $s1 += 60;
            $segundos = $s1 - $s2;

        }

        if ($segundos) {
            $segundos = "{$segundos}s";
        }

        if ($minutos >= 0 && $minutos < 10) {
            $minutos = "0{$minutos}";
        }
        if ($segundos === 0) {
            $segundos = "";
        }

        $minutosMostrar = "";
        if ($minutos) {
            if ($horas) {
                $minutos = abs($minutos);
            }
            $minutosMostrar = "{$minutos}min{$segundos}";
        }

        if ($horas == 0) {
            if (substr($minutosMostrar, 0, 1) !== "-") {
                return "+{$minutosMostrar}";
            }
            return $minutosMostrar;
        }

        if (substr($horas, 0, 1) !== "-") {
            return "+{$horas}h{$minutosMostrar}";
        }

        return "{$horas}h{$minutosMostrar}";
    }

    public static function logError(string|Array|Object $information): void
    {
        $pasta = Configuration::get()->pathLogs;
        if (!is_dir($pasta)) {
            @mkdir($pasta, 0775, true);
        }

        $data = [
            "dateTime" => date("Y-m-d H:i:s"),
            "idUsuario" => $_SESSION["idUsuario"] ?? null,
            "uri" => $_SERVER["REQUEST_URI"] ?? null,
            "information" => $information,
        ];
        $data = json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        @file_put_contents("{$pasta}error.log", $data. PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public static function getIp(): ?string
    {
        $headerIPs = [
            "HTTP_VIA",
            "HTTP_X_FORWARDED_FOR",
            "HTTP_FORWARDED_FOR",
            "HTTP_X_FORWARDED",
            "HTTP_FORWARDED",
            "HTTP_CLIENT_IP",
            "HTTP_FORWARDED_FOR_IP",
            "VIA",
            "X_FORWARDED_FOR",
            "FORWARDED_FOR",
            "X_FORWARDED",
            "FORWARDED",
            "CLIENT_IP",
            "FORWARDED_FOR_IP",
            "HTTP_PROXY_CONNECTION",
            "REMOTE_ADDR"
        ];

        foreach ($headerIPs as $ipKey) {
            if (!isset($_SERVER[$ipKey])) {
                continue;
            }

            return $_SERVER[$ipKey];
        }

        return null;
    }

    public static function camelToSnake(string $string, string $separator = "_"): string
    {
        $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1' . $separator . '$2', $string));
        return $snake;
    }

    public static function snakeToCamel(string $string, string $separator = "_"): string
    {
        $camel = lcfirst(str_replace($separator, "", ucwords($string, $separator)));
        return $camel;
    }

    public static function escapeLike(string $texto): string
    {
        return addcslashes($texto, "%_\\");
    }

    public static function dinheiro(float $valor): float
    {
        return round($valor, 2);
    }

    public static function percentual(float $parte, float $total): float
    {
        if ($total == 0.0) {
            return 0.0;
        }

        return round($parte / $total * 100, 2);
    }

    public static function isJson(string $string): bool
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
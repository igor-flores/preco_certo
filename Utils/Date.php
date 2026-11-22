<?php
namespace Utils;

use DateTime;

class Date
{
    public static function format(string $data): string
    {
        if (strpos($data, '/') !== false) {
            return $data;
        }

        if (strpos(strtoupper($data), 'T') !== false) {
            $data = explode("T", $data);
            $data = $data[0];
        }

        if ($data == '0000-00-00' || $data == '') {
            return '';
        }

        list($ano, $mes, $dia) = explode('-', $data);
        return "{$dia}/{$mes}/{$ano}";
    }

    public static function dateDifference(string $inicialDate, string $finalDate)
    {   
        $inicio = new DateTime($inicialDate);
        $fim = new DateTime($finalDate);
        
        return date_diff($inicio, $fim);
    }

    public static function sumDate(string $data, int $nDias, int $nMeses, int$nAnos): string
    {
        list($ano,$mes,$dia) = explode("-",$data);
        $date = mktime(0,0,0, $mes + $nMeses, $dia + $nDias, $ano + $nAnos);
        return date("Y-m-d", $date);
    }

    public static function diff(string $inicialDate, string $finalDate, $expr, $unit): bool
    {   
        if (strpos($inicialDate, '/') !== false) {
            $inicialDate = Cast::date($inicialDate);
        }

        if (strpos($finalDate, '/') !== false) {
            $finalDate = Cast::date($finalDate);
        }

        $inicialDate = strtotime($inicialDate);
        $finalDate = strtotime($finalDate);

        $op = "+";
        if ($inicialDate <= $finalDate) {
            $op = "-";
        }

        $finalDate = date("Y-m-d", strtotime("{$op}{$expr} {$unit}", $finalDate));
        $finalDate = strtotime($finalDate);

        return $inicialDate <= $finalDate;
    }

    public static function nominalMonth(string $data): string
    {
        switch ($data){
            case '01':
                $month = "janeiro";
                break;
            case '02':
                $month = "fevereiro";
                break;
            case '03':
                $month = "março";
                break;
            case '04':
                $month = "abril";
                break;
            case '05':
                $month = "maio";
                break;
            case '06':
                $month = "junho";
                break;
            case '07':
                $month = "julho";
                break;
            case '08':
                $month = "agosto";
                break;
            case '09':
                $month = "setembro";
                break;
            case '10':
                $month = "outubro";
                break;
            case '11':
                $month = "novembro";
                break;
            case '12':
                $month = "dezembro";
                break;
            default:
                $month = "(indefinido)";
                break;
        }
        return $month;
    }

    public static function dateTime(string $dateTime): string
    {
        $dateTime = strtotime($dateTime);

        return date('d/m/Y H:i', $dateTime);
    } 

    public static function getWeek(string $date): string
    {
        $dataReferencia = new DateTime($date ? $date : date('Y-m-d'));
        $dataReferencia->modify("-" . $dataReferencia->format('w') . " days");

        $semana = [];
        for ($i = 0; $i < 7; $i++) {
            $semana[] = $dataReferencia->format('Y-m-d');
            $dataReferencia->modify('+1 day');
        }

        return $semana;
    }
    
    public static function isValid(string $dateTime, string $format = "Y-m-d H:i:s"): string
    {
        $date = DateTime::createFromFormat($format, $dateTime);
        return $date && $date->format($format) === $dateTime;
    }

    public static function diffTime(string $dateTimeInitial, string $dateTimeFinal): string
    {
        $dateTimeInitial = strtotime($dateTimeInitial);
        $dateTimeFinal = strtotime($dateTimeFinal);

        if ($dateTimeInitial < $dateTimeFinal) {
            $dateAux = $dateTimeFinal;

            $dateTimeFinal = $dateTimeInitial;
            $dateTimeInitial = $dateAux;
        }

        $diff = $dateTimeInitial - $dateTimeFinal;

        $hour = floor($diff / 3600);
        $minutes = floor(($diff % 3600) / 60);
        $seconds = $diff % 60;

        return sprintf('%02d:%02d:%02d', $hour, $minutes, $seconds);
    }
}
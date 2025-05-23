<?php

namespace App\Http\Helper;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Laraindo\RupiahFormat;

class FormatHelper
{
    public static function formatFileName($file)
    {
        $dateNow = Carbon::now();
        $ext = 'png';
        if (!empty(explode(".", $file->getClientOriginalName())[1])) {
            $ext = explode(".", $file->getClientOriginalName())[1];
        }
        $name                       = str_replace(":", "_", explode(".", $file->getClientOriginalName())[0] . "_" . $dateNow . "." . $ext);
        $name                       = str_replace(" ", "_", $name);
        $name                       = str_replace("-", "_", $name);
        return $name;
    }

    public static function contains(string $string, $value): bool
    {
        switch (gettype($value)) {
            case 'array':
                return in_array($string, $value);
                // $ptn = '';
                // foreach ($value as $s) {
                //     if ($ptn != '') $ptn .= '|';
                //     $ptn .= preg_quote($s, '/');
                // }
                // return preg_match("/$ptn/i", $string);
                // break;
            case 'string':
                return str_contains($value, $string);
                break;
            default:
                return false;
        }
    }

    public static function isEmail($email): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        return false;
    }

    public static function getNumberOfMonth(String $value)
    {
        $result = '';
        switch ($value) {
            case ('JAN'):
                $result = '01';
                break;
            case ('FEB'):
                $result = '02';
                break;
            case ('MAR'):
                $result = '03';
                break;
            case ('APR'):
                $result = '04';
                break;
            case ('MEI'):
                $result = '05';
                break;
            case ('JUN'):
                $result = '06';
                break;
            case ('JUL'):
                $result = '07';
                break;
            case ('AGU'):
                $result = '08';
                break;
            case ('SEP'):
                $result = '09';
                break;
            case ('OKT'):
                $result = '10';
                break;
            case ('NOV'):
                $result = '11';
                break;
            case ('DES'):
                $result = '12';
                break;
            default:
                $result = '';
        }

        return $result;
    }

    public static function getPeriode($datenow)
    {

        $month      = date('M', strtotime($datenow));
        $year       = date('Y', strtotime($datenow));
        $result     = strtoupper($month) . '-' . $year;
        return $result;
    }

    public static function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    // public static function rupiahFormat($value)
    // {

    //     return RupiahFormat::currency($value);
    // }

    static function isValidXml($content)
    {
        $content = trim($content);
        if (empty($content)) {
            return false;
        }
        //html go to hell!
        if (stripos($content, '<!DOCTYPE html>') !== false) {
            return false;
        }

        libxml_use_internal_errors(true);
        simplexml_load_string($content);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        return empty($errors);
    }

    static function isNotEmpty($value, bool $isNeedDD = false): bool
    {
        if ($isNeedDD) {
            dd($value);
        }
        if (!isset($value)) {
            return false;
        }

        switch (gettype($value)) {
            case 'NULL':
                return false;
            case 'string':
                if ($value != '') {
                    return true;
                }
                break;
            case 'array':
                if (!empty(array_filter($value))) {
                    return true;
                }
                break;
            case 'object':

                if ($value instanceof \Illuminate\Database\Eloquent\Model) {
                    return !empty($value->toArray()); // include attributes + appends
                }
                // if (!empty(get_object_vars($value))) {
                //     return true;
                // }
                $arr = (array)$value;
                if (!empty($arr)) {
                    return true;
                }
                break;
            case 'double':
            case 'int':
            case 'integer':
                return true;
            default:
                # code...
                break;
        }
        return false;
    }
}

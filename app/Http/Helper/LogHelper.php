<?php

namespace App\Http\Helper;

use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogHelper
{
    public static function sendErrorLog(
        Exception $ex,
        string $idProject = '',
        string $key = '',
    ) {
        $error['line']      = $ex->getLine();
        $error['ip']        = LogHelper::getClientIP();
        $error['message']   = $ex->getMessage();
        $error['file']      = $ex->getFile();
        if (!empty($idProject)) {
            $dateNow                    = date("Y-m-d H:i:s");
            $dataLog['ip']              = LogHelper::getClientIP();
            $dataLog['key']             = $key;
            $dataLog['value']           = json_encode($error);
            $dataLog['created_at']      = $dateNow;
            $dataLog['updated_at']      = $dateNow;
            DB::table('log__' . $idProject)->insert($dataLog);
        }
        Log::error($error);
    }

    public static function sendLog(
        String $msg,
        $data = '',
        string $idProject = '',
        string $key = '',
    ) {
        $info['ip']        = LogHelper::getClientIP();
        $info['key']       = $key;
        $info['message']   = $msg;
        $info['data']      = $data;
        if (!empty($idProject)) {
            $dateNow                    = date("Y-m-d H:i:s");
            $dataLog['ip']              = LogHelper::getClientIP();
            $dataLog['key']             = $key;
            $dataLog['value']           = json_encode($data);
            $dataLog['created_at']      = $dateNow;
            $dataLog['updated_at']      = $dateNow;
            DB::table('log__' . $idProject)->insert($dataLog);
        }
        Log::info($info);
    }

    public static function getClientIP()
    {
        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if (getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if (getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if (getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if (getenv('HTTP_FORWARDED'))
            $ipaddress = getenv('HTTP_FORWARDED');
        else if (getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }
}

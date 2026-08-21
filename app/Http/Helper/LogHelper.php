<?php

namespace App\Http\Helper;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogHelper
{
    private const SENSITIVE_KEYS = [
        // 'password',
        // 'card_number',
        // 'cardnumber',
        // 'number',
        // 'cvv',
        // 'cvc',
        // 'card_cvc',
        // 'card_cvv',
        // 'token',
        // 'secret',
        // 'stripe_secretkey',
        // 'stripe_webhooksecret',
        // 'midtrans_serverkey',
        // 'midtrans_clientkey',
        // 'spnpay_secretkey',
        // 'spnpay_token',
        // 'xendit_secretkey',
        // 'xendit_publickey',
        // 'xendit_tokencallback',
        // 'duitku_mk',
        // 'duitku_mc',
        // 'authorization',
    ];

    public static function sendErrorLog(
        Exception $ex,
        string $idProject = '',
        string $key = ''
    ): void {
        $error['line']      = $ex->getLine();
        $error['ip']        = LogHelper::getClientIP();
        $error['message']   = $ex->getMessage();
        $error['file']      = $ex->getFile();
        if (!empty($idProject)) {
            $dateNow                    = date("Y-m-d H:i:s");
            $dataLog['ip']              = LogHelper::getClientIP();
            $dataLog['key']             = $key;
            $dataLog['value']           = json_encode(self::redactSensitiveData($error));
            $dataLog['created_at']      = $dateNow;
            $dataLog['updated_at']      = $dateNow;
            DB::table('log__' . $idProject)->insert($dataLog);
        }
        Log::error(self::redactSensitiveData($error));
    }

    public static function sendLog(
        string $msg,
        mixed $data = '',
        string $idProject = '',
        string $key = ''
    ): void {
        $sanitizedData = self::redactSensitiveData($data);
        $info['ip']        = LogHelper::getClientIP();
        $info['key']       = $key;
        $info['message']   = $msg;
        $info['data']      = $sanitizedData;
        if (!empty($idProject)) {
            $dateNow                    = date("Y-m-d H:i:s");
            $dataLog['ip']              = LogHelper::getClientIP();
            $dataLog['key']             = $key;
            $dataLog['value']           = is_string($sanitizedData) ? $sanitizedData : json_encode($sanitizedData);
            $dataLog['created_at']      = $dateNow;
            $dataLog['updated_at']      = $dateNow;
            DB::table('log__' . $idProject)->insert($dataLog);
        }
        Log::info($info);
    }

    public static function redactSensitiveData(mixed $data): mixed
    {
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                    $cleaned[$key] = '***REDACTED***';
                } else {
                    $cleaned[$key] = self::redactSensitiveData($value);
                }
            }
            return $cleaned;
        }

        if (is_object($data)) {
            $array = (array) $data;
            return (object) self::redactSensitiveData($array);
        }

        if (is_string($data) && (str_starts_with(trim($data), '{') || str_starts_with(trim($data), '['))) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return json_encode(self::redactSensitiveData($decoded));
            }
        }

        return $data;
    }

    public static function getClientIP(): string
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

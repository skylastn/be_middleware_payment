<?php

namespace App\Http\Helper;

use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
        // 'api_secret',
        // 'api_key',
        // 'x-signature',
        // 'x-partner-id',
        // 'signature',
        // 'client_secret',
        // 'client_key',
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
            try {
                $dateNow                    = date("Y-m-d H:i:s");
                $dataLog['ip']              = LogHelper::getClientIP();
                $dataLog['key']             = $key;
                $dataLog['value']           = json_encode(self::redactSensitiveData($error));
                $dataLog['created_at']      = $dateNow;
                $dataLog['updated_at']      = $dateNow;
                try {
                    DB::table('z__log__' . $idProject)->insert($dataLog);
                } catch (\Exception) {
                    DB::table('log__' . $idProject)->insert($dataLog);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to insert error log to project table: ' . $e->getMessage());
            }
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
            try {
                $dateNow                    = date("Y-m-d H:i:s");
                $dataLog['ip']              = LogHelper::getClientIP();
                $dataLog['key']             = $key;
                $dataLog['value']           = is_string($sanitizedData) ? $sanitizedData : json_encode($sanitizedData);
                $dataLog['created_at']      = $dateNow;
                $dataLog['updated_at']      = $dateNow;
                try {
                    DB::table('z__log__' . $idProject)->insert($dataLog);
                } catch (\Exception) {
                    DB::table('log__' . $idProject)->insert($dataLog);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to insert log to project table: ' . $e->getMessage());
            }
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
        $request = request();
        if ($request->ip()) {
            return $request->ip();
        }
        return 'UNKNOWN';
    }
}

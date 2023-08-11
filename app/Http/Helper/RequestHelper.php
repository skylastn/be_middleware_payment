<?php

namespace App\Http\Helper;

use Illuminate\Support\Facades\Log;

class RequestHelper
{
    public static function sendCallback($token, $params, $urlCallback)
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $urlCallback,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => array(
                "Token: $token",
                'Content-Type: application/json'
            ),
        ));
        Log::info("Sending Request", [$urlCallback, $params, $token]);
        $response = curl_exec($curl);

        curl_close($curl);

        Log::info("Result Callback", [$response]);
        return json_decode($response);
        // echo $response;

    }
}

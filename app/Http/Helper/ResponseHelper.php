<?php

namespace App\Http\Helper;

class ResponseHelper
{
    public static function formatPagination($result)
    {
        $response = [
            'status'        => true,
            'code'          => 200,
            'message'       => "Success",
            'total'         => $result->total(),
            'perPage'       => $result->perPage(),
            'currentPage'   => $result->currentPage(),
            'data'          => $result->getCollection(),
        ];

        return response()->json($response, 200);
    }

    public static function successResponse($data, String $msg = 'Success', $code = 200)
    {
        $response = [
            'status'        => true,
            'code'          => $code,
            'message'       => $msg,
            'data'          => $data,
        ];
        return response()->json($response, 200);
    }

    public static function failedResponse($data, String $msg = 'Failed', $code = 400, $line = 0)
    {
        $response = [
            'status'        => true,
            'code'          => $code,
            'message'       => $msg,
            'line'          => $line,
            'data'          => $data,
        ];
        return response()->json($response, 400);
    }

    public static function unauthorizedResponse($data, String $msg = 'Unauthorized')
    {
        $response = [
            'status'        => true,
            'code'          => 403,
            'message'       => $msg,
            'data'          => $data,
        ];
        return response()->json($response, 403);
    }
}

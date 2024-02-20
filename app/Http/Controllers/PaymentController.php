<?php

namespace App\Http\Controllers;

use App\Http\Helper\ResponseHelper;
use App\Models\PaymentCategory;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Exception;

class PaymentController extends Controller
{
    function getPaymentCategory()
    {
        return ResponseHelper::successResponse(PaymentCategory::get());
    }

    function getPaymentMethod(Request $request)
    {
        $result = PaymentMethod::where('from', 'spnpay')->get();
        return ResponseHelper::successResponse($result);
    }
}

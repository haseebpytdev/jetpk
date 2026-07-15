<?php

namespace App\Services\Payments\Contracts;

use App\Models\PaymentTransaction;
use App\Services\Payments\DTO\PaymentGatewayCallbackResult;
use App\Services\Payments\DTO\PaymentGatewayCreateResult;
use App\Services\Payments\DTO\PaymentGatewayVerifyResult;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    public function createPayment(PaymentTransaction $transaction): PaymentGatewayCreateResult;

    public function verifyPayment(PaymentTransaction $transaction): PaymentGatewayVerifyResult;

    public function handleCallback(Request $request): PaymentGatewayCallbackResult;
}

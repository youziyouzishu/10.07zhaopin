<?php

namespace app\api\common;

use Braintree\Gateway;

class Pay
{
    protected $gateway;

    public function __construct()
    {
        $this->gateway = new Gateway([
            'environment' => 'sandbox', // 或 'production'
            'merchantId' => 'your_merchant_id',
            'publicKey' => 'your_public_key',
            'privateKey' => 'your_private_key'
        ]);
    }

    public function getClientToken()
    {
        return $this->gateway->clientToken()->generate();
    }

    public function processPayment($nonce, $amount)
    {
        // 发起销售交易
        $result = $this->gateway->transaction()->sale([
            'amount' => $amount,
            'paymentMethodNonce' => $nonce,
            'options' => [
                'submitForSettlement' => true
            ]
        ]);

        // 检查交易结果
        if ($result->success) {
            return ['status' => 'success', 'message' => 'Transaction successful'];
        } else {
            return ['status' => 'error', 'message' => 'Transaction failed'];
        }
    }


}
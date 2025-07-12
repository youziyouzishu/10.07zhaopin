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
            'merchantId' => '696p2rvbwjy5ktbc',
            'publicKey' => 'b2jkd88z2vmm798c',
            'privateKey' => 'cd7917dd077ed72bda6258f9d8944ba3'
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
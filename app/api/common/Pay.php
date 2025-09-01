<?php

namespace app\api\common;

use Braintree\Gateway;
use support\Log;

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

        // 交易成功
        if ($result->success) {
            $transaction = $result->transaction;
            return [
                'success' => true,
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
                'amount' => $transaction->amount
            ];
        }

        // 交易失败
        $errors = [];
        if (isset($result->transaction)) {
            // 交易被拒绝或失败，但有交易对象
            $errors[] = 'Transaction status: ' . $result->transaction->status;
        }

        // 遍历详细错误信息
        foreach ($result->errors->deepAll() as $error) {
            $errors[] = "Error {$error->code}: {$error->message}";
        }

        return [
            'success' => false,
            'errors' => $errors
        ];
    }


}
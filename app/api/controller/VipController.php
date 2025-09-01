<?php

namespace app\api\controller;

use app\admin\model\Vip;
use app\admin\model\VipOrders;
use app\api\basic\Base;
use app\api\common\Pay;
use Braintree\Gateway;
use Carbon\Carbon;
use plugin\admin\app\common\Util;
use support\Log;
use support\Request;

class VipController extends Base
{
    protected $noNeedLogin = ['pay'];
    #创建订单
    function createOrder(Request $request)
    {
        $vip_id = $request->post('vip_id');
        $vip = Vip::find($vip_id);
        if (!$vip){
            return $this->fail('会员不存在');
        }
        $ordersn = Util::generateOrdersn();
        VipOrders::create([
            'user_id'=>$request->user_id,
            'vip_id'=>$vip_id,
            'ordersn'=>$ordersn,
            'pay_amount'=>$vip->price,
        ]);
        return $this->success('成功',['ordersn'=>$ordersn]);
    }



    /**
     * 支付
     * @param Request $request
     * @return \support\Response
     */
    function pay(Request $request)
    {
        $ordersn = $request->post('ordersn');
        $nonce = $request->post('nonce');
        $order = VipOrders::where(['ordersn' => $ordersn])->first();
        if (!$order) return $this->fail('订单不存在');
        if ($order->status != 0) return $this->fail('订单状态异常');
        if (!$nonce) return $this->fail('支付凭证不能为空');

        $gateway = new Gateway([
            'environment' => 'sandbox', // 或 'production'
            'merchantId' => '696p2rvbwjy5ktbc',
            'publicKey' => 'b2jkd88z2vmm798c',
            'privateKey' => 'cd7917dd077ed72bda6258f9d8944ba3'
        ]);
        if (in_array($order->vip_id,[1,4])){
            // 单次购买
            $result = $gateway->transaction()->sale([
                'amount' => $order->pay_amount,
                'paymentMethodNonce' => $nonce,
                'options' => ['submitForSettlement' => true]
            ]);
            if ($result->success) {
                $order->status = 1;
                $order->transaction_id = $result->transaction->id;
                $order->save();
                Log::info('单次支付成功', ['ordersn' => $ordersn, 'transaction_id' => $result->transaction->id]);
                return $this->success('支付成功', ['transaction_id' => $result->transaction->id]);
            } else {
                $errors = [];
                foreach ($result->errors->deepAll() as $error) $errors[] = "{$error->code}: {$error->message}";
                Log::error('单次支付失败', ['ordersn' => $ordersn, 'errors' => $errors]);
                return $this->fail('支付失败: ' . implode(', ', $errors));
            }
        }else{
            // 订阅支付
            $planId = $order->vip->plan_id;

            // 创建/获取客户
            $customerResult = $gateway->customer()->create([
                'paymentMethodNonce' => $nonce
            ]);
            if (!$customerResult->success) {
                $errors = [];
                foreach ($customerResult->errors->deepAll() as $error) $errors[] = "{$error->code}: {$error->message}";
                Log::error('创建客户失败', ['ordersn' => $ordersn, 'errors' => $errors]);
                return $this->fail('创建客户失败: ' . implode(', ', $errors));
            }

            $paymentMethodToken = $customerResult->customer->paymentMethods[0]->token;

            $subscriptionResult = $gateway->subscription()->create([
                'paymentMethodToken' => $paymentMethodToken,
                'planId' => $planId
            ]);

            if ($subscriptionResult->success) {
                $order->status = 1;
                $order->subscription_id = $subscriptionResult->subscription->id;
                $order->save();
                Log::info('订阅成功', ['ordersn' => $ordersn, 'subscription_id' => $subscriptionResult->subscription->id]);
                return $this->success('订阅成功', ['subscription_id' => $subscriptionResult->subscription->id]);
            } else {
                $errors = [];
                foreach ($subscriptionResult->errors->deepAll() as $error) $errors[] = "{$error->code}: {$error->message}";
                Log::error('订阅失败', ['ordersn' => $ordersn, 'errors' => $errors]);
                return $this->fail('订阅失败: ' . implode(', ', $errors));
            }
        }
    }




}

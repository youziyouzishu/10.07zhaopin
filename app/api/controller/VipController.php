<?php

namespace app\api\controller;

use app\admin\model\Vip;
use app\admin\model\VipOrders;
use app\api\basic\Base;
use app\api\common\Pay;
use plugin\admin\app\common\Util;
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
        $order = VipOrders::where(['ordersn'=>$ordersn])->first();
        if (!$order){
            return $this->fail('订单不存在');
        }
        if ($order->status != 0){
            return $this->fail('订单异常');
        }
        $nonce = $request->input('nonce');
        $amount = $request->input('amount');
        $pay = new Pay();
        $result = $pay->processPayment($nonce, $order->pay_amount);
        return $this->success('成功',$result);
    }




}

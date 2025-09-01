<?php

namespace app\api\controller;

use app\admin\model\User;
use app\admin\model\UsersHr;
use app\admin\model\VipOrders;
use app\api\basic\Base;
use Braintree\Gateway;
use support\Cache;
use support\exception\BusinessException;
use support\Log;
use support\Request;


class NotifyController extends Base
{
    protected $noNeedLogin = ['*'];

    function im(Request $request)
    {
        $params = $request->all();
        $CallbackCommand = $params['CallbackCommand'];
        if ($CallbackCommand == 'State.StateChange') {
            //在线状态
            if ($params['Info']['Action'] == 'Login') {
                User::where('id', $params['Info']['To_Account'])->update(['online' => 1]);
            }
            if ($params['Info']['Action'] == 'Disconnect' || $params['Info']['Action'] == 'Logout') {
                User::where('id', $params['Info']['To_Account'])->update(['online' => 0]);
            }
        }
    }

    function beHr(Request $request)
    {
        $invite = $request->get('invite');
        $invite = Cache::get('invite_'.$invite);
        if (empty($invite)){
            return $this->success('链接不存在或已过期');
        }
        $user_id = $invite['user_id'];
        $to_user_id = $invite['to_user_id'];

        $user = User::find($user_id);
        if ($user->hr->count() >= 10){
            return $this->fail('对方名额已满');
        }
        $toUser = User::find($to_user_id);
        if ($toUser->hr_type != 1){
            return  $this->fail('只有普通HR才能认证');
        }
        UsersHr::create([
            'user_id'=>$user_id,
            'to_user_id'=>$to_user_id,
        ]);
        $toUser->hr_type = 2;
        $toUser->save();
        return $this->success('认证成功');
    }

    function braintree(Request $request)
    {
        $gateway = new Gateway([
            'environment' => 'sandbox', // 或 'production'
            'merchantId' => '696p2rvbwjy5ktbc',
            'publicKey' => 'b2jkd88z2vmm798c',
            'privateKey' => 'cd7917dd077ed72bda6258f9d8944ba3'
        ]);

        $webhookNotification = $gateway->webhookNotification()->parse(
            $request->post('bt_signature'),
            $request->post('bt_payload')
        );

        $kind = $webhookNotification->kind;

        switch ($kind) {
            case 'transaction_disbursed':
                $transaction = $webhookNotification->transaction;
                Log::info("单次支付资金结算成功", [
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount
                ]);
                // 更新订单状态为“已结算”
                VipOrders::where('transaction_id', $transaction->id)->update(['status' => 2]);
                break;

            case 'transaction_settlement_declined':
            case 'transaction_failed':
                $transaction = $webhookNotification->transaction;
                Log::warning("单次支付失败或结算拒绝", [
                    'transaction_id' => $transaction->id,
                    'amount' => $transaction->amount
                ]);
                VipOrders::where('transaction_id', $transaction->id)->update(['status' => 3]);
                break;

            default:
                Log::info("其他 webhook 事件", ['kind'=>$kind]);
                break;
        }

        return response('OK');
    }

}

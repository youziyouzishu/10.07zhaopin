<?php

namespace app\api\controller;

use app\admin\model\User;
use app\admin\model\UsersHr;
use app\admin\model\VipOrders;
use app\api\basic\Base;
use Braintree\Gateway;
use Carbon\Carbon;
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
        $invite = Cache::get('invite_' . $invite);
        if (empty($invite)) {
            return $this->success('链接不存在或已过期');
        }
        $user_id = $invite['user_id'];
        $to_user_id = $invite['to_user_id'];

        $user = User::find($user_id);
        if ($user->hr->count() >= 10) {
            return $this->fail('对方名额已满');
        }
        $toUser = User::find($to_user_id);
        if ($toUser->hr_type != 1) {
            return $this->fail('只有普通HR才能认证');
        }
        UsersHr::create([
            'user_id' => $user_id,
            'to_user_id' => $to_user_id,
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

        try {
            $webhookNotification = $gateway->webhookNotification()->parse(
                $request->post('bt_signature'),
                $request->post('bt_payload')
            );

            $kind = $webhookNotification->kind;
            $transaction = $webhookNotification->transaction ?? null;
            $subscriptionId = $webhookNotification->subscription->id ?? null;

            // 查询订单
            $query = VipOrders::query();

            if (!empty($transaction->id)) {
                $query->where('transaction_id', $transaction->id);
            } elseif (!empty($subscriptionId)) {
                $query->where('subscription_id', $subscriptionId);
            } else {
                // 如果 transaction_id 和 subscription_id 都为空，直接返回
                Log::warning("Webhook无有效ID", ['kind' => $kind]);
                return response('OK');
            }

            $order = $query->first();

            if (!$order) {
                Log::warning("Webhook未找到订单", [
                    'transaction_id' => $transaction->id ?? null,
                    'subscription_id' => $subscriptionId,
                    'kind' => $kind
                ]);
                return response('OK');
            }

            switch ($kind) {
                case 'transaction_disbursed': // 单次支付到账
                case 'subscription_charged_successfully': // 订阅支付或续费成功
                    $order->status = 2; // 已支付/续费成功
                    // 订阅支付首次扣款，填充 transaction_id
                    if ($transaction && empty($order->transaction_id)) {
                        $order->transaction_id = $transaction->id;
                    }
                    $order->save();
                    // 延长会员有效期
                    $expireAt = $order->user->vip_expire_at ? Carbon::parse($order->user->vip_expire_at) : null;
                    if (in_array($order->vip_id,[1,4])){
                        $order->user->vip_expire_at = (!$expireAt || $expireAt->isPast())
                            ? Carbon::now()->addMonths(1)
                            : $expireAt->addMonths(1);
                    }else{
                        $order->user->vip_expire_at = (!$expireAt || $expireAt->isPast())
                            ? Carbon::now()->addYears(1)
                            : $expireAt->addYears(1);
                    }
                    $order->user->save();
                    Log::info("支付/续费成功", [
                        'ordersn' => $order->ordersn,
                        'transaction_id' => $order->transaction_id,
                        'subscription_id' => $order->subscription_id,
                        'kind' => $kind
                    ]);
                    break;
                case 'transaction_settlement_declined':
                case 'transaction_failed':
                case 'subscription_charged_unsuccessfully':
                    $order->status = 3; // 支付失败
                    $order->save();

                    Log::warning("支付失败", [
                        'ordersn' => $order->ordersn,
                        'transaction_id' => $transaction->id ?? null,
                        'subscription_id' => $subscriptionId,
                        'kind' => $kind
                    ]);
                    break;
                case 'check':
                    Log::info("Webhook测试事件", ['kind' => $kind]);
                    break;

                default:
                    Log::info("其他Webhook事件", ['kind' => $kind]);
                    break;
            }

        } catch (\Exception $e) {
            Log::error("Webhook处理异常", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return response('OK');
    }



}

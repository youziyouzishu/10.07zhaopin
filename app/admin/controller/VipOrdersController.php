<?php

namespace app\admin\controller;

use Braintree\Gateway;
use support\Request;
use support\Response;
use app\admin\model\VipOrders;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;
use support\Log;
use Throwable;

/**
 * VIP订单列表
 */
class VipOrdersController extends Crud
{

    /**
     * @var VipOrders
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new VipOrders;
    }

    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('vip-orders/index');
    }

    /**
     * 查询
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function select(Request $request): Response
    {
        $user_type = $request->get('user_type');
        $deleted_status = $request->get('deleted_status');
        [$where, $format, $limit, $field, $order] = $this->selectInput($request);
        $query = $this->doSelect($where, $field, $order)
            ->with(['user' => function ($query) {
                $query->withTrashed();
            },'vip'])
            ->when(!empty($user_type), function ($query) use ($user_type) {
                if ($user_type === '2') {
                    $query->whereHas('user', function ($query) {
                        $query->withTrashed()->where('user_type',1);
                    });
                }
                if ($user_type === '3') {
                    $query->whereHas('user', function ($query) {
                        $query->withTrashed()->where('user_type',0);
                    });
                }
            })
            ->when(!empty($deleted_status), function ($query) use ($deleted_status) {
                if ($deleted_status === '1') {
                    $query->whereHas('user', function ($query) {
                        $query->withTrashed();;
                    });
                }
                if ($deleted_status === '2') {
                    $query->whereHas('user', function ($query) {
                        $query->onlyTrashed();;
                    });
                }
                if ($deleted_status === '3') {
                    $query->whereHas('user', function ($query) {
                        $query->withoutTrashed();;
                    });
                }
            });

        return $this->doFormat($query, $format, $limit);
    }

    /**
     * 插入
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function insert(Request $request): Response
    {
        if ($request->method() === 'POST') {
            return parent::insert($request);
        }
        return view('vip-orders/insert');
    }

    /**
     * 更新
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function update(Request $request): Response
    {
        if ($request->method() === 'POST') {
            return parent::update($request);
        }
        return view('vip-orders/update');
    }

    /**
     * 退款
     * @param Request $request
     * @return Response
     */
    public function refund(Request $request): Response
    {
        $primaryKey = $this->model->getKeyName();
        $id = $request->post($primaryKey);
        if (!$id) {
            return $this->fail('缺少订单ID');
        }

        $order = VipOrders::find($id);
        if (!$order) {
            return $this->fail('订单不存在');
        }
        if ((int)$order->status === 0) {
            return $this->fail('订单未支付');
        }
        if ((int)$order->status === 4) {
            return $this->fail('订单已退款');
        }

        $gateway = new Gateway([
            'environment' => 'sandbox',
            'merchantId' => '696p2rvbwjy5ktbc',
            'publicKey' => 'b2jkd88z2vmm798c',
            'privateKey' => 'cd7917dd077ed72bda6258f9d8944ba3'
        ]);

        $actions = [];
        try {
            if (!empty($order->subscription_id)) {
                $subResult = $gateway->subscription()->cancel($order->subscription_id);
                if (!$subResult->success) {
                    $errors = [];
                    foreach ($subResult->errors->deepAll() as $error) {
                        $errors[] = "{$error->code}: {$error->message}";
                    }
                    Log::error('取消订阅失败', [
                        'order_id' => $order->id,
                        'ordersn' => $order->ordersn,
                        'subscription_id' => $order->subscription_id,
                        'errors' => $errors
                    ]);
                    return $this->fail('取消订阅失败: ' . implode(', ', $errors));
                }
                $actions[] = 'subscription_cancelled';
            }

            if (!empty($order->transaction_id)) {
                $refundResult = $gateway->transaction()->refund($order->transaction_id);
                if (!$refundResult->success) {
                    $errors = [];
                    if (isset($refundResult->transaction)) {
                        $errors[] = 'Transaction status: ' . $refundResult->transaction->status;
                    }
                    if ($refundResult->errors) {
                        foreach ($refundResult->errors->deepAll() as $error) {
                            $errors[] = "{$error->code}: {$error->message}";
                        }
                    }
                    Log::error('退款失败', [
                        'order_id' => $order->id,
                        'ordersn' => $order->ordersn,
                        'transaction_id' => $order->transaction_id,
                        'errors' => $errors
                    ]);
                    return $this->fail('退款失败: ' . implode(', ', $errors));
                }
                $actions[] = 'transaction_refunded';
            }
        } catch (Throwable $e) {
            Log::error('退款异常', [
                'order_id' => $order->id,
                'ordersn' => $order->ordersn,
                'error' => $e->getMessage()
            ]);
            return $this->fail('退款异常: ' . $e->getMessage());
        }

        if (empty($actions)) {
            return $this->fail('订单缺少交易信息');
        }

        $order->status = 4;
        $order->save();
        Log::info('退款成功', [
            'order_id' => $order->id,
            'ordersn' => $order->ordersn,
            'actions' => $actions
        ]);

        return $this->success('退款成功');
    }

}

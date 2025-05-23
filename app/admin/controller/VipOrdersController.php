<?php

namespace app\admin\controller;

use support\Request;
use support\Response;
use app\admin\model\VipOrders;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

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

}

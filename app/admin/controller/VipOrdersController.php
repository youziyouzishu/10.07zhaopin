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

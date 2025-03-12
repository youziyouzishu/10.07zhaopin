<?php

namespace plugin\admin\app\controller;

use plugin\admin\app\model\User;
use support\exception\BusinessException;
use support\Request;
use support\Response;
use Throwable;
use Webman\RedisQueue\Client;

/**
 * 用户管理 
 */
class UserController extends Crud
{
    
    /**
     * @var User
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new User;
    }

    /**
     * 浏览
     * @return Response
     * @throws Throwable
     */
    public function index(): Response
    {
        return raw_view('user/index');
    }

    /**
     * 插入
     * @param Request $request
     * @return Response
     * @throws BusinessException|Throwable
     */
    public function insert(Request $request): Response
    {
        if ($request->method() === 'POST') {
            return parent::insert($request);
        }
        return raw_view('user/insert');
    }

    /**
     * 更新
     * @param Request $request
     * @return Response
     * @throws BusinessException|Throwable
     */
    public function update(Request $request): Response
    {
        if ($request->method() === 'POST') {
            $id = $request->post('id');
            $vip_expire_at = $request->post('vip_expire_at');
            $user = $this->model->find($id);

            if (!empty($vip_expire_at) && $vip_expire_at != $user->vip_expire_at ){
                Client::send('job', ['event' => 'vip_expire', 'user_id' => $user->id], $user->vip_expire_at->timestamp - time());
            }
            return parent::update($request);
        }
        return raw_view('user/update');
    }

}

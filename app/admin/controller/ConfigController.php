<?php

namespace app\admin\controller;

use plugin\admin\app\common\Util;
use plugin\admin\app\model\Option;
use support\Request;
use support\Response;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 系统配置
 */
class ConfigController extends Crud
{

    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('config/index');
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
        return view('config/insert');
    }

    /**
     * 更改
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function update(Request $request): Response
    {
        $post = $request->post();
        $data['user_agreement'] = $post['user_agreement'] ?? '';
        $data['privacy_policy'] = $post['privacy_policy'] ?? '';
        $name = 'admin_config';
        $option = new Option();
        $row = $option->where('name', $name)->first();
        if ($row){
            $row->value = json_encode($data);
        }else{
            $option->name = $name;
            $option->value = json_encode($data);
            $option->save();
        }
        return $this->json(0);
    }

    /**
     * 获取配置
     * @return Response
     */
    public function get(): Response
    {
        $name = 'admin_config';
        $config = Option::where('name', $name)->value('value');
        $config = json_decode($config,true);
        return $this->success('成功', $config);
    }




}

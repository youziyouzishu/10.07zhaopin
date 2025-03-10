<?php

namespace app\admin\controller;

use support\Request;
use support\Response;
use app\admin\model\Country;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 国家管理 
 */
class CountryController extends Crud
{
    
    /**
     * @var Country
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new Country;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('country/index');
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
        return view('country/insert');
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
        return view('country/update');
    }

}

<?php

namespace app\admin\controller;

use support\Request;
use support\Response;
use app\admin\model\Company;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 公司管理 
 */
class CompanyController extends Crud
{
    
    /**
     * @var Company
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new Company;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('company/index');
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
        return view('company/insert');
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
        return view('company/update');
    }

}

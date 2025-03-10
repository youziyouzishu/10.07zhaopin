<?php

namespace app\admin\controller;

use support\Request;
use support\Response;
use app\admin\model\University;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 大关列表 
 */
class UniversityController extends Crud
{
    
    /**
     * @var University
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new University;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('university/index');
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
        return view('university/insert');
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
        return view('university/update');
    }

}

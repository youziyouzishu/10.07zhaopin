<?php

namespace app\admin\controller;

use support\Request;
use support\Response;
use app\admin\model\Report;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 举报管理 
 */
class ReportController extends Crud
{
    
    /**
     * @var Report
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new Report;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('report/index');
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
        return view('report/insert');
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
        return view('report/update');
    }

}

<?php

namespace app\admin\controller;

use support\Request;
use support\Response;
use app\admin\model\ProjectExperience;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 项目背景管理 
 */
class ProjectExperienceController extends Crud
{
    
    /**
     * @var ProjectExperience
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new ProjectExperience;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('project-experience/index');
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
        return view('project-experience/insert');
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
        return view('project-experience/update');
    }

}

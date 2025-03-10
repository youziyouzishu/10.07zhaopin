<?php

namespace app\admin\controller;

use support\Request;
use support\Response;
use app\admin\model\JobSkill;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 岗位技能管理 
 */
class JobSkillController extends Crud
{
    
    /**
     * @var JobSkill
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new JobSkill;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('job-skill/index');
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
        return view('job-skill/insert');
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
        return view('job-skill/update');
    }

}

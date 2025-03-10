<?php

namespace app\admin\controller;

use support\Request;
use support\Response;
use app\admin\model\ResumeSkill;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 简历技术栈 
 */
class ResumeSkillController extends Crud
{
    
    /**
     * @var ResumeSkill
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new ResumeSkill;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('resume-skill/index');
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
        return view('resume-skill/insert');
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
        return view('resume-skill/update');
    }

}

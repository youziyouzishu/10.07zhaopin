<?php

namespace app\admin\controller;

use support\Request;
use support\Response;
use app\admin\model\InternshipExperienceSkill;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 实习技能管理 
 */
class InternshipExperienceSkillController extends Crud
{
    
    /**
     * @var InternshipExperienceSkill
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new InternshipExperienceSkill;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('internship-experience-skill/index');
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
        return view('internship-experience-skill/insert');
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
        return view('internship-experience-skill/update');
    }

}

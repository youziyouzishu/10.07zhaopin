<?php

namespace app\admin\controller;

use support\Request;
use support\Response;
use app\admin\model\FullTimeExperienceSkill;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 全职技能管理 
 */
class FullTimeExperienceSkillController extends Crud
{
    
    /**
     * @var FullTimeExperienceSkill
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new FullTimeExperienceSkill;
    }
    
    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('full-time-experience-skill/index');
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
        return view('full-time-experience-skill/insert');
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
        return view('full-time-experience-skill/update');
    }

}

<?php

namespace app\api\controller;

use app\admin\model\EducationalBackground;
use app\admin\model\Job;
use app\admin\model\Resume;
use app\admin\model\User;
use app\api\basic\Base;
use support\Request;


class IndexController extends Base
{
    protected $noNeedLogin = ['*'];


    public function index(Request $request)
    {

    }


}

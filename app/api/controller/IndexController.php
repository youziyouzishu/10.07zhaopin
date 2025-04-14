<?php

namespace app\api\controller;

use app\admin\model\EducationalBackground;
use app\admin\model\Job;
use app\admin\model\JobMajor;
use app\admin\model\Resume;
use app\admin\model\User;
use app\api\basic\Base;
use Illuminate\Database\Eloquent\Builder;
use support\Db;
use support\Request;


class IndexController extends Base
{
    protected $noNeedLogin = ['*'];

    function index(Request $request)
    {

    }

    function resume(Request $request)
    {

    }

    public function hr(Request $request)
    {

    }

    function send(Request $request)
    {

    }


}

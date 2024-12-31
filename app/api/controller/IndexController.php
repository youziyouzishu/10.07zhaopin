<?php

namespace app\api\controller;

use app\admin\model\Country;
use app\admin\model\Resume;
use app\admin\model\University;
use app\api\basic\Base;
use GuzzleHttp\Client;
use support\Request;

class IndexController extends Base
{
    protected $noNeedLogin = ['*'];
    public function index(Request $request)
    {
        $rows = Resume::get();
        foreach ($rows as $row){
            $row->top_degree = $row->educationalBackground->max('degree_to_job');
            $row->save();
        }
    }

}

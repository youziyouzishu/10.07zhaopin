<?php

namespace app\api\controller;

use app\admin\model\Banner;
use app\admin\model\Company;
use app\admin\model\Country;
use app\admin\model\Major;
use app\admin\model\Province;
use app\admin\model\Skill;
use app\admin\model\University;
use app\api\basic\Base;
use Illuminate\Database\Eloquent\Builder;
use plugin\admin\app\model\Option;
use support\Request;
use Tencent\TLSSigAPIv2;

class CommonController extends Base
{
    protected $noNeedLogin = ['*'];

    function getProvinceList(Request $request)
    {
        $keyword = $request->post('keyword');
        $name = $request->post('name');
        if ($name == 'United States'){
            $rows = Province::when(!empty($keyword), function (Builder $builder) use ($keyword) {
                $builder->whereRaw('LOWER(name) LIKE LOWER(?)', ['%' . $keyword . '%']);
            })->get();
        }else{
            $rows = [];
        }
        return $this->success('成功', $rows);
    }

    function getCountryList(Request $request)
    {
        $keyword = $request->post('keyword');
        $rows = Country::when(!empty($keyword), function (Builder $builder) use ($keyword) {
            $builder->whereRaw('LOWER(name) LIKE LOWER(?)', ['%' . $keyword . '%']);
        })->limit(10)->get();
        return $this->success('成功', $rows);
    }

    function getMajorList(Request $request)
    {
        $keyword = $request->post('keyword');
        $rows = Major::when(!empty($keyword), function (Builder $builder) use ($keyword) {
            $builder->whereRaw('LOWER(name) LIKE LOWER(?)', ['%' . $keyword . '%']);
        })->limit(10)->get();
        return $this->success('成功', $rows);
    }


    function getCompanyList(Request $request)
    {
        $keyword = $request->post('keyword');
        $rows = Company::when(!empty($keyword), function (Builder $builder) use ($keyword) {
            $builder->whereRaw('LOWER(name) LIKE LOWER(?)', ['%' . $keyword . '%']);
        })->limit(10)->get();
        return $this->success('成功', $rows);
    }

    function getUniversityList(Request $request)
    {
        $keyword = $request->post('keyword');
        $rows = University::when(!empty($keyword), function (Builder $builder) use ($keyword) {
            $builder->whereRaw('LOWER(name) LIKE LOWER(?)', ['%' . $keyword . '%']);
        })->limit(10)->get();
        return $this->success('成功', $rows);
    }

    function getSkillList(Request $request)
    {
        $keyword = $request->post('keyword');
        $rows = Skill::when(!empty($keyword), function (Builder $builder) use ($keyword) {
            $builder->whereRaw('LOWER(name) LIKE LOWER(?)', ['%' . $keyword . '%']);
        })->limit(10)->get();
        return $this->success('成功', $rows);
    }

    function getConfig()
    {
        $name = 'admin_config';
        $config = Option::where('name', $name)->value('value');
        $config = json_decode($config);
        return $this->success('成功', $config);
    }

    #获取轮播图
    function getBannerList()
    {
        $rows = Banner::orderByDesc('weigh')->get();
        return $this->success('成功', $rows);
    }







}

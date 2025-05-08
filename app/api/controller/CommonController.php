<?php

namespace app\api\controller;

use app\admin\model\Banner;
use app\admin\model\Company;
use app\admin\model\Country;
use app\admin\model\Major;
use app\admin\model\Province;
use app\admin\model\SendLog;
use app\admin\model\Skill;
use app\admin\model\SystemNotice;
use app\admin\model\University;
use app\admin\model\Vip;
use app\api\basic\Base;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use plugin\admin\app\model\Option;
use support\Db;
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
            $rows = Province::orderBy('name')->when(!empty($keyword), function (Builder $builder) use ($keyword) {
                $builder->whereRaw('LOWER(name) LIKE LOWER(?)', [$keyword . '%']);
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
            $builder->whereRaw('LOWER(name) LIKE LOWER(?)', [$keyword . '%']);
        })->limit(10)->get();
        return $this->success('成功', $rows);
    }

    function getMajorList(Request $request)
    {
        $keyword = $request->post('keyword');
        $rows = Major::orderBy('name')->when(!empty($keyword), function (Builder $builder) use ($keyword) {
            $builder->whereRaw('LOWER(name) LIKE LOWER(?)', [$keyword . '%']);
        })->limit(10)->get();
        return $this->success('成功', $rows);
    }


    function getCompanyList(Request $request)
    {
        $keyword = $request->post('keyword');
        $rows = Company::when(!empty($keyword), function (Builder $builder) use ($keyword) {
            $builder->whereRaw('LOWER(name) LIKE LOWER(?)', [$keyword . '%']);
        })->limit(10)->get();
        return $this->success('success', $rows);
    }

    function getUniversityList(Request $request)
    {
        $keyword = $request->post('keyword');
        $rows = University::when(!empty($keyword), function (Builder $builder) use ($keyword) {
            $builder->whereRaw('LOWER(name) LIKE LOWER(?)', [$keyword . '%']);
        })->limit(10)->get();
        return $this->success('成功', $rows);
    }

    function getSkillList(Request $request)
    {
        $keyword = $request->post('keyword');
        $ids = $request->post('ids');#数组
        if (!empty($ids)){
            $rows = Skill::whereIn('id',$ids)->get()->map(function (Skill $item) {
                return [
                    'name' => $item->name,
                ];
            });
        }else{
            $rows = Skill::orderBy('name')->when(!empty($keyword), function (Builder $builder) use ($keyword) {
                $builder->whereRaw('LOWER(name) LIKE LOWER(?)', [$keyword . '%']);
            })->limit(10)->get();
        }
        return $this->success('成功', $rows);
    }

    function getConfig()
    {
        $name = 'admin_config';
        $config = Option::where('name', $name)->value('value');
        $config = json_decode($config);
        return $this->success('成功', $config);
    }

    #获取轮播图 user_type 用户类型:0=seeker=求职者,1=hr=招聘者
    function getBannerList(Request $request)
    {
        if ($request->user_type == 0){
            $type = 2;
        }else{
            $type = 1;
        }
        $rows = Banner::where('type',$type)->orderByDesc('weigh')->get();
        return $this->success('成功', $rows);
    }

    #获取会员价格列表
    function getVipList(Request $request)
    {
        $rows = Vip::where('type',$request->user_type)->get();
        return $this->success('成功', $rows);
    }

    function getNoticeList(Request $request)
    {
        $type = $request->post('type');
        $rows = SystemNotice::where('type',$type)->orderByDesc('id')->paginate()->items();
        return $this->success('成功', $rows);
    }


    function getNoticeDetail(Request $request)
    {
        $id = $request->post('id');
        $row = SystemNotice::find($id);
        return $this->success('成功', $row);
    }









}

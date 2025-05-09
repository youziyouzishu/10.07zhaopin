<?php

namespace app\admin\controller;

use Carbon\Carbon;
use plugin\admin\app\model\Option;
use support\Request;
use support\Response;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;
use Webman\RedisQueue\Client;

/**
 * 系统配置
 */
class ConfigController extends Crud
{

    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('config/index');
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
        return view('config/insert');
    }

    /**
     * 更改
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function update(Request $request): Response
    {
        $post = $request->post();

        $data['user_agreement'] = $post['user_agreement'] ?? '';
        $data['privacy_policy'] = $post['privacy_policy'] ?? '';

        $data['resume_activity'] = $post['resume_activity'] ?? '';
        $data['resume_activity_day'] = $post['resume_activity_day'] ?? '';

        $data['hr_activity'] = $post['hr_activity'] ?? '';
        $data['hr_activity_day'] = $post['hr_activity_day'] ?? '';

        $data['resume_compensation'] = $post['resume_compensation'] ?? '';
        $data['resume_compensation_day'] = $post['resume_compensation_day'] ?? '';
        $data['hr_compensation'] = $post['hr_compensation'] ?? '';
        $data['hr_compensation_day'] = $post['hr_compensation_day'] ?? '';
        if (empty($data['resume_activity']) || empty($data['resume_activity_day'])){
            return $this->fail('候选人端引流活动不能为空');
        }

        if (empty($data['hr_activity']) || empty($data['hr_activity_day'])){
            return $this->fail('HR端引流活动不能为空');
        }

        if (empty($data['resume_compensation']) || empty($data['resume_compensation_day'])){
            return $this->fail('候选人端补偿方式不能为空');
        }

        if (empty($data['hr_compensation']) || empty($data['hr_compensation_day'])){
            return $this->fail('HR端补偿方式不能为空');
        }
        $parts = explode(' - ', $data['resume_activity']);
        if (count($parts) === 2) {
            list($resume_activity_start, $resume_activity_end) = $parts;
            $resume_activity_start = Carbon::parse($resume_activity_start);
            $resume_activity_end = Carbon::parse($resume_activity_end);
            if (!$resume_activity_start->isValid() || !$resume_activity_end->isValid()) {
                return $this->fail('候选人端引流活动时间格式错误');
            }
            if ($resume_activity_end->isBefore($resume_activity_start)) {
                return $this->fail('候选人端引流活动开始时间不能大于结束时间');
            }
        } else {
            return $this->fail('候选人端引流活动时间格式错误');
        }

        $parts = explode(' - ', $data['hr_activity']);
        if (count($parts) === 2) {
            list($hr_activity_start, $hr_activity_end) = $parts;
            $hr_activity_start = Carbon::parse($hr_activity_start);
            $hr_activity_end = Carbon::parse($hr_activity_end);
            if (!$hr_activity_start->isValid() || !$hr_activity_end->isValid()) {
                return $this->fail('HR端引流活动时间格式错误');
            }
            if ($hr_activity_end->isBefore($hr_activity_start)) {
                return $this->fail('HR端引流活动开始时间不能大于结束时间');
            }
        } else {
            return $this->fail('HR端引流活动时间格式错误');
        }
        if ($data['resume_activity_day'] < 1){
            return $this->fail('候选人端引流活动时间不能小于1天');
        }
        if ($data['hr_activity_day'] < 1){
            return $this->fail('HR端引流活动时间不能小于1天');
        }

        $resume_compensation = Carbon::parse($data['resume_compensation']);
        if (!$resume_compensation->isValid()) {
            return $this->fail('候选人端补偿方式时间格式错误');
        }
        $hr_compensation = Carbon::parse($data['hr_compensation']);
        if (!$hr_compensation->isValid()) {
            return $this->fail('HR端补偿方式时间格式错误');
        }
        if ($data['resume_compensation_day'] < 1){
            return $this->fail('候选人端补偿方式时间不能小于1天');
        }
        if ($data['hr_compensation_day'] < 1){
            return $this->fail('HR端补偿方式时间不能小于1天');
        }


        $name = 'admin_config';
        $option = new Option();
        $row = $option->where('name', $name)->first();
        $value = json_decode($row->value);
        if ($value->resume_compensation != $data['resume_compensation']){
            //候选人队列补偿
            Client::send('job', ['event' => 'resume_compensation','days' => $data['resume_compensation_day']], $resume_compensation->diffInSeconds(Carbon::now()));
        }

        if ($value->hr_compensation != $data['hr_compensation']){
            //HR队列补偿
            Client::send('job', ['event' => 'resume_compensation','days' => $data['hr_compensation_day']], $hr_compensation->diffInSeconds(Carbon::now()));
        }

        if ($row){
            $row->value = json_encode($data);
            $row->save();
        }else{
            $option->name = $name;
            $option->value = json_encode($data);
            $option->save();
        }



        return $this->json(0);
    }

    /**
     * 获取配置
     * @return Response
     */
    public function get(): Response
    {
        $name = 'admin_config';
        $config = Option::where('name', $name)->value('value');
        $config = json_decode($config,true);
        return $this->success('成功', $config);
    }




}

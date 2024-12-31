<?php

namespace app\api\controller;

use app\admin\model\Ems;
use app\api\basic\Base;
use Carbon\Carbon;
use plugin\admin\app\model\User;
use support\Request;

class EmsController extends Base
{

    protected $noNeedLogin = ['send'];

    /**
     * 发送验证码
     *
     * @ApiMethod (POST)
     * @ApiParams (name="email", type="string", required=true, description="邮箱")
     * @ApiParams (name="event", type="string", required=true, description="事件名称")
     */
    public function send(Request $request)
    {
        $email = $request->post("email");
        $event = $request->post("event");
        $event = $event ? $event : 'register';

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
           return $this->fail('邮箱格式错误');
        }
        if (!preg_match("/^[a-z0-9_\-]{3,30}\$/i", $event)) {
            return $this->fail('事件名称错误');
        }

        $last = Ems::getLast($email, $event);
        if ($last && time() - $last->created_at->timestamp < 60) {
            return $this->fail('发送频繁');
        }

        // 获取当前小时的开始和结束时间
        $startTime = Carbon::now()->startOfHour();
        $endTime = Carbon::now()->endOfHour();
        $ipSendTotal = Ems::where(['ip' => $request->getRealIp()])->whereBetween('created_at', [$startTime, $endTime])->count();
        if ($ipSendTotal >= 5) {
            return $this->fail('发送频繁');
        }

        if ($event) {
            $userinfo = User::where(['email'=>$email])->first();
            if ($event == 'register' && $userinfo) {
                //已被注册
                return $this->fail('已被注册');
            } elseif (in_array($event, ['changeemail']) && $userinfo) {
                //被占用
                return $this->fail('已被占用');
            } elseif (in_array($event, ['changepwd', 'resetpwd']) && !$userinfo) {
                //未注册
                return $this->fail('未注册');
            }
        }
        $ret = Ems::send($email, null, $event);
        if ($ret) {
            return $this->success('发送成功');
        } else {
            return $this->fail('发送失败');
        }
    }

    /**
     * 检测验证码
     *
     * @ApiMethod (POST)
     * @ApiParams (name="email", type="string", required=true, description="邮箱")
     * @ApiParams (name="event", type="string", required=true, description="事件名称")
     * @ApiParams (name="captcha", type="string", required=true, description="验证码")
     */
    public function check(Request $request)
    {
        $email = $request->post("email");
        $event = $request->post("event");
        $event = $event ? $event : 'register';
        $captcha = $request->post("captcha");

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('邮箱格式错误');
        }
        if (!preg_match("/^[a-z0-9_\-]{3,30}\$/i", $event)) {
            return $this->fail('事件名称错误');
        }

        if (!preg_match("/^[a-z0-9]{4,6}\$/i", $captcha)) {
            return $this->fail('验证码格式错误');
        }

        if ($event) {
            $userinfo = User::where(['email'=>$email,'type'=>$request->user_type])->first();
            if ($event == 'register' && $userinfo) {
                //已被注册
                return $this->fail('已被注册');
            } elseif (in_array($event, ['changeemail']) && $userinfo) {
                //被占用
                return $this->fail('已被占用');
            } elseif (in_array($event, ['changepwd', 'resetpwd']) && !$userinfo) {
                //未注册
                return $this->fail('未注册');
            }
        }
        $ret = Ems::check($email, $captcha, $event);
        if ($ret) {
            return $this->success('成功');
        } else {
            return $this->fail('验证码不正确');
        }
    }
}

<?php

namespace app\api\controller;

use app\admin\model\Sms;
use app\admin\model\User;
use app\api\basic\Base;
use Carbon\Carbon;
use support\Request;
use support\Response;
use Tinywan\Validate\Facade\Validate;

class SmsController extends Base
{

    protected $noNeedLogin = ['send','check'];

    /**
     * 发送验证码
     *
     * @ApiMethod (POST)
     * @param string $mobile 手机号
     * @param string $event 事件名称
     */
    public function send(Request $request):Response
    {
        $mobile = $request->post("mobile");
        $event = $request->post("event");
        $event = $event ?: 'register';

        if (!$mobile || !Validate::checkRule($mobile, 'mobile')) {
            return $this->fail('手机号不正确');
        }
        $last = Sms::getLast($mobile, $event);

        if ($last && time() - $last->created_at->timestamp < 60) {
            return $this->fail('发送频繁');
        }
        // 获取当前小时的开始和结束时间
        $startTime = Carbon::now()->startOfHour();
        $endTime = Carbon::now()->endOfHour();
        $ipSendTotal = Sms::where(['ip' => $request->getRealIp()])->whereBetween('created_at', [$startTime, $endTime])->count();
        if ($ipSendTotal >= 5) {
            return $this->fail('发送频繁');
        }
        if ($event) {
            $userinfo = User::where(['mobile'=>$mobile,'type'=>$request->user_type])->first();
            if ($event == 'register' && $userinfo) {
                //已被注册
                return $this->fail('已被注册');
            } elseif ($event == 'changemobile' && $userinfo) {
                //被占用
                return $this->fail('已被占用');
            } elseif (in_array($event, ['changepwd', 'resetpwd']) && !$userinfo) {
                //未注册
                return $this->fail('未注册');
            }
        }

        $ret = Sms::send($mobile, null, $event);
        if ($ret) {
            return $this->success('发送成功');
        } else {
            return $this->fail('发送失败，请检查短信配置是否正确');
        }
    }

    /**
     * 检测验证码
     *
     * @ApiMethod (POST)
     * @param string $mobile 手机号
     * @param string $event 事件名称
     * @param string $captcha 验证码
     */
    public function check(Request $request)
    {
        $mobile = $request->post("mobile");
        $event = $request->post("event");
        $event = $event ? $event : 'register';
        $captcha = $request->post("captcha");

        if (!$mobile || !Validate::checkRule($mobile, 'mobile')) {
            return $this->fail('手机号不正确');
        }
        if ($event) {
            $userinfo = User::where(['mobile'=>$mobile])->first();
            if ($event == 'register' && $userinfo) {
                //已被注册
                return $this->fail('已被注册');
            } elseif ($event == 'changemobile' && $userinfo) {
                //被占用
                return $this->fail('已被占用');
            } elseif (in_array($event, ['changepwd', 'resetpwd']) && !$userinfo) {
                //未注册
                return $this->fail('未注册');
            }
        }
        $ret = Sms::check($mobile, $captcha, $event);
        if ($ret) {
            return $this->success();
        } else {
            return $this->fail('验证码不正确');
        }
    }
}

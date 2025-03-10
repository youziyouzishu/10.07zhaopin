<?php

namespace app\api\controller;

use app\admin\model\Ems;
use app\admin\model\Report;
use app\admin\model\Sms;
use app\admin\model\User;
use app\admin\model\UsersHr;
use app\admin\model\UsersProfile;
use app\api\basic\Base;
use plugin\admin\app\common\Util;
use support\Request;
use Tencent\TLSSigAPIv2;
use Tinywan\Jwt\JwtToken;
use Tinywan\Validate\Facade\Validate;
use Webman\RedisQueue\Client;
use Webman\RedisQueue\Redis;

class UserController extends Base
{
    protected $noNeedLogin = ['login', 'register'];

    function register(Request $request)
    {
        $name = $request->post('name');
        $middle_name = $request->post('middle_name');
        $last_name = $request->post('last_name');
        $email = $request->post('email');
        $email_code = $request->post('email_code');
        $mobile = $request->post('mobile');
        $mobile_code = $request->post('mobile_code');
        $password = $request->post('password');
        $password_confirm = $request->post('password_confirm');

        if ($password != $password_confirm) {
            return $this->fail('两次密码不一致');
        }
        $emsResult = Ems::check($email, $email_code, 'register');
        if (!$emsResult) {
            return $this->fail('邮箱验证码不正确');
        }
        $smsResult = Sms::check($mobile, $mobile_code, 'register');
        if (!$smsResult) {
            return $this->fail('手机验证码不正确');
        }
        $email = User::where(['email' => $email,'type' => $request->user_type])->first();
        if ($email) {
            return $this->fail('邮箱已存在');
        }
        $mobile = User::where(['mobile' => $mobile,'type' => $request->user_type])->first();
        if ($mobile) {
            return $this->fail('手机号已存在');
        }
        $has = User::where(['email' => $email, 'mobile' => $mobile, 'type' => $request->user_type == 0 ? 1 : 0])->first();
        if ($has) {
            //如果有对应端用户，则删除
            $has->delete();
        }
        $user = User::create([
            'nickname' => $name .' '. $middle_name .' '. $last_name,
            'middle_name' => $middle_name,
            'avatar' => '/avatar.png',
            'email' => $email,
            'mobile' => $mobile,
            'last_time' => date('Y-m-d H:i:s'),
            'last_ip' => $request->getRealIp(),
            'join_time' => date('Y-m-d H:i:s'),
            'join_ip' => $request->getRealIp(),
            'name' => $name,
            'last_name' => $last_name,
            'password' => Util::passwordHash($password),
            'type' => $request->user_type,
            'hr_type' => $request->user_type == 0 ? 0 : 1,
            'salutation'=> $request->user_type == 0 ? '' : 'I am very interested in your background. Could you share your resume with me?'
        ]);
        $token = JwtToken::generateToken([
            'id' => $user->id,
            'client' => JwtToken::TOKEN_CLIENT_MOBILE
        ]);
        return $this->success('注册成功', ['user' => $user, 'token' => $token]);
    }

    function login(Request $request)
    {

        $login_type = $request->post('login_type');#登陆方式 0=账号登录 1=验证码登录
        $account = $request->post('account');
        $password = $request->post('password');
        $captcha = $request->post('captcha');
        if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
            $field = 'email';
        } elseif (preg_match('/^[0-9]{10}$/', $account)) {
            $field = 'mobile';
        } else {
            return $this->fail('账号格式不正确');
        }
        if ($login_type == 0) {
            $user = User::where([$field => $account, 'type' => $request->user_type])->first();
            if (!$user) {
                return $this->fail('账户不存在');
            }
            if (!Util::passwordVerify($password, $user->password)) {
                return $this->fail('密码错误');
            }
        } else {

            if ($field == 'mobile') {
                $ret = Sms::check($account, $captcha, 'login');
            } else {
                $ret = Ems::check($account, $captcha, 'login');
            }
            if (!$ret) {
                return $this->fail('验证码不正确');
            }
            $user = User::where([$field => $account])->first();
            if (!$user) {
                return $this->fail('账户不存在');
            }
        }
        $token = JwtToken::generateToken([
            'id' => $user->id,
            'client' => JwtToken::TOKEN_CLIENT_MOBILE
        ]);
        return $this->success('登陆成功', ['user' => $user, 'token' => $token]);
    }

    function getUserInfo(Request $request)
    {
        $user_id = $request->post('user_id');
        if (!empty($user_id)) {
            $request->user_id = $user_id;
        }
        $row = User::with(['profile'])->find($request->user_id);
        return $this->success('成功', $row);
    }

    function changeMobile(Request $request)
    {
        $mobile = $request->post('mobile');
        $captcha = $request->post('captcha');
        if (!$mobile) {
            return $this->fail('手机号不正确');
        }
        $smsResult = Sms::check($mobile, $captcha, 'changemobile');
        if (!$smsResult) {
            return $this->fail('验证码不正确');
        }
        $user = User::find($request->user_id);
        $user->mobile = $mobile;
        $user->save();
        return $this->success();
    }

    function changeEmail(Request $request)
    {
        $email = $request->post('email');
        $captcha = $request->post('captcha');
        if (!$email || !Validate::checkRule($email, 'email')) {
            return $this->fail('邮箱不正确');
        }
        $emsResult = Ems::check($email, $captcha, 'changeemail');
        if (!$emsResult) {
            return $this->fail('验证码不正确');
        }
        $user = User::find($request->user_id);
        $user->email = $email;
        $user->save();
        return $this->success();
    }

    #更改密码
    function changePassword(Request $request)
    {
        $newpassword = $request->post('newpassword');
        $password_confirm = $request->post('password_confirm');
        if ($newpassword !== $password_confirm) {
            return $this->fail('两次密码不一致');
        }
        $user = User::find($request->user_id);
        $user->password = Util::passwordHash($newpassword);
        $user->save();
        return $this->success();
    }

    function editUserInfo(Request $request)
    {

        $data = $request->post();

        $row = User::find($request->user_id);
        if (!$row) {
            return $this->fail('用户不存在');
        }
        if (!empty($data['company_name']) && $data['company_name'] != $row->company_name && $row->hr_type >= 2){
            //如果认证hr修改了公司名称，则取消认证
            //删除下面的HR
            if ($row->hr_type == 2){
                //认证HR
                $hrs = UsersHr::where(['to_user_id' => $request->user_id])->get();
                foreach ($hrs as $hr) {
                    $hr->delete();
                    //发信给自己
                    Client::send('job', ['event' => 'email_cancel_hr_1', 'email' => $hr->toUser->email]);
                    //发信给超级HR
                    Client::send('job', ['event' => 'email_cancel_hr_2', 'email' => $hr->user->email]);
                }

            }
            if ($row->hr_type == 3){
                //超级HR
                $hrs = UsersHr::where(['user_id' => $request->user_id])->get();
                //发信给自己
                Client::send('job', ['event' => 'email_cancel_hr_3', 'email' => $row->email]);
                foreach ($hrs as $hr) {
                    $hr->delete();
                    //发信给被管理认证的HR
                    Client::send('job', ['event' => 'email_cancel_hr_4', 'email' => $hr->toUser->email]);
                }
            }
            $row->hr_type = 1;
        }
        if (in_array($data,['middle_name','name','last_name'])){
            $row->nickname = $data['name'].' '.$data['middle_name'].' '.$data['last_name'];
        }
        $userAttributes = $row->getAttributes();
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $userAttributes) && (!empty($value) || $value === 0)) {
                $row->setAttribute($key, $value);
            }
        }
        $row->save();
        return $this->success('成功');
    }

    function saveProfile(Request $request)
    {
        $last_name = $request->post('last_name');
        $middle_name = $request->post('middle_name');
        $name = $request->post('name');
        $adult = $request->post('adult');
        $top_secret = $request->post('top_secret');
        $united_states_authorization = $request->post('united_states_authorization');
        $sponsorship = $request->post('sponsorship');
        $from_limitation = $request->post('from_limitation');
        $gender = $request->post('gender');
        $sexual_orientation = $request->post('sexual_orientation');
        $race = $request->post('race');
        $disability = $request->post('disability');
        $veteran = $request->post('veteran');
        $address = $request->post('address');
        $city = $request->post('city');
        $country = $request->post('country');
        $postal_code = $request->post('postal_code');
        $us_citizen = $request->post('us_citizen');
        $salutation = $request->post('salutation');
        $profile = UsersProfile::where('user_id', $request->user_id)->first();
        if (!$profile) {
            $profile = new UsersProfile();
            $profile->user_id = $request->user_id;
        }
        $profile->last_name = $last_name;
        $profile->name = $name;
        $profile->adult = $adult;
        $profile->top_secret = $top_secret;
        $profile->united_states_authorization = $united_states_authorization;
        $profile->sponsorship = $sponsorship;
        $profile->from_limitation = $from_limitation;
        $profile->gender = $gender;
        $profile->sexual_orientation = $sexual_orientation;
        $profile->race = $race;
        $profile->disability = $disability;
        $profile->veteran = $veteran;
        $profile->address = $address;
        $profile->city = $city;
        $profile->country = $country;
        $profile->postal_code = $postal_code;
        $profile->us_citizen = $us_citizen;
        $profile->middle_name = $middle_name;
        $profile->salutation = empty($salutation)? '' : $salutation;
        $profile->save();
        return $this->success('成功');
    }

    function report(Request $request)
    {
        $to_user_id = $request->post('to_user_id');
        $reason = $request->post('reason');
        $explain = $request->post('explain');
        $images = $request->post('images');

        Report::create([
            'user_id' => $request->user_id,
            'to_user_id' => $to_user_id,
            'reason' => $reason,
            'explain' => $explain,
            'images' => $images,
        ]);
        return $this->success('成功');
    }

    function getTLSSig(Request $request)
    {
        $api = new TLSSigAPIv2(1600067517, '8f00cb63054ab6d5516bd15bc7c770db18db105c3fe7bbbe4e78cd3fbfb129e7'); // 替换为实际AppID和密钥
        $sign = $api->genUserSig($request->user_id);
        return $this->success('成功', ['sign' => $sign]);
    }


}

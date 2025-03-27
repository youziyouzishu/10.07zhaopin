<?php

namespace plugin\admin\app\controller;

use app\admin\model\Ems;
use app\admin\model\Sms;
use Carbon\Carbon;
use plugin\admin\app\common\Auth;
use plugin\admin\app\common\Util;
use plugin\admin\app\model\Admin;
use support\exception\BusinessException;
use support\Request;
use support\Response;
use Throwable;
use Webman\Captcha\CaptchaBuilder;
use Webman\Captcha\PhraseBuilder;

/**
 * 管理员账户
 */
class AccountController extends Crud
{
    /**
     * 不需要登录的方法
     * @var string[]
     */
    protected $noNeedLogin = ['login', 'logout', 'captcha', 'forgetPassword', 'send', 'check', 'resetpwd'];

    /**
     * 不需要鉴权的方法
     * @var string[]
     */
    protected $noNeedAuth = ['info'];

    /**
     * @var Admin
     */
    protected $model = null;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->model = new Admin;
    }

    /**
     * 账户设置
     * @return Response
     * @throws Throwable
     */
    public function index()
    {
        return raw_view('account/index');
    }

    /**
     * 登录
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function login(Request $request): Response
    {
        $this->checkDatabaseAvailable();
        $captcha = $request->post('captcha', '');
        if (strtolower($captcha) !== session('captcha-login')) {
            return $this->json(1, '验证码错误');
        }
        $request->session()->forget('captcha-login');
        $username = $request->post('username', '');
        $password = $request->post('password', '');
        if (!$username) {
            return $this->json(1, '用户名不能为空');
        }
        $this->checkLoginLimit($username);
        $admin = Admin::where('username', $username)->first();
        if (!$admin || !Util::passwordVerify($password, $admin->password)) {
            return $this->json(1, '账户不存在或密码错误');
        }
        if ($admin->status != 0) {
            return $this->json(1, '当前账户暂时无法登录');
        }
        $admin->login_at = date('Y-m-d H:i:s');
        $admin->save();
        $this->removeLoginLimit($username);
        $admin = $admin->toArray();
        $session = $request->session();
        $admin['password'] = md5($admin['password']);
        $session->set('admin', $admin);
        return $this->json(0, '登录成功', [
            'nickname' => $admin['nickname'],
            'token' => $request->sessionId(),
        ]);
    }

    /**
     * 退出
     * @param Request $request
     * @return Response
     */
    public function logout(Request $request): Response
    {
        $request->session()->delete('admin');
        return $this->json(0);
    }

    /**
     * 获取登录信息
     * @param Request $request
     * @return Response
     */
    public function info(Request $request): Response
    {
        $admin = admin();
        if (!$admin) {
            return $this->json(1);
        }
        $info = [
            'id' => $admin['id'],
            'username' => $admin['username'],
            'nickname' => $admin['nickname'],
            'avatar' => $admin['avatar'],
            'email' => $admin['email'],
            'mobile' => $admin['mobile'],
            'isSuperAdmin' => Auth::isSuperAdmin(),
            'token' => $request->sessionId(),
        ];
        return $this->json(0, 'ok', $info);
    }

    /**
     * 更新
     * @param Request $request
     * @return Response
     */
    public function update(Request $request): Response
    {
        $allow_column = [
            'nickname' => 'nickname',
            'avatar' => 'avatar',
            'email' => 'email',
            'mobile' => 'mobile',
        ];

        $data = $request->post();
        $update_data = [];
        foreach ($allow_column as $key => $column) {
            if (isset($data[$key])) {
                $update_data[$column] = $data[$key];
            }
        }
        if (isset($update_data['password'])) {
            $update_data['password'] = Util::passwordHash($update_data['password']);
        }
        Admin::where('id', admin_id())->update($update_data);
        $admin = admin();
        unset($update_data['password']);
        foreach ($update_data as $key => $value) {
            $admin[$key] = $value;
        }
        $request->session()->set('admin', $admin);
        return $this->json(0);
    }

    /**
     * 修改密码
     * @param Request $request
     * @return Response
     */
    public function password(Request $request): Response
    {
        $hash = Admin::find(admin_id())['password'];
        $password = $request->post('password');
        if (!$password) {
            return $this->json(2, '密码不能为空');
        }
        if ($request->post('password_confirm') !== $password) {
            return $this->json(3, '两次密码输入不一致');
        }
        if (!Util::passwordVerify($request->post('old_password'), $hash)) {
            return $this->json(1, '原始密码不正确');
        }
        $update_data = [
            'password' => Util::passwordHash($password)
        ];
        Admin::where('id', admin_id())->update($update_data);
        return $this->json(0);
    }

    function resetpwd(Request $request)
    {
        $account = $request->post('account');
        $password = $request->post('password');
        if (!$password) {
            return $this->fail('密码不能为空');
        }

        if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
            $field = 'email';
        } elseif (preg_match('/^[0-9]{10}$/', $account)) {
            $field = 'mobile';
        } else {
            return $this->fail('账号格式不正确');
        }
        $admin = Admin::where([$field => $account])->first();
        if (empty($admin)){
            return $this->fail('账号不存在');
        }
        $admin->password = Util::passwordHash($password);
        $admin->save();
        return $this->json(0);
    }

    /**
     * 验证码
     * @param Request $request
     * @param string $type
     * @return Response
     */
    public function captcha(Request $request, string $type = 'login'): Response
    {
        $builder = new PhraseBuilder(4, 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ');
        $captcha = new CaptchaBuilder(null, $builder);
        $captcha->build(120);
        $request->session()->set("captcha-$type", strtolower($captcha->getPhrase()));
        $img_content = $captcha->get();
        return response($img_content, 200, ['Content-Type' => 'image/jpeg']);
    }

    /**
     * 检查登录频率限制
     * @param $username
     * @return void
     * @throws BusinessException
     */
    protected function checkLoginLimit($username)
    {
        $limit_log_path = runtime_path() . '/login';
        if (!is_dir($limit_log_path)) {
            mkdir($limit_log_path, 0777, true);
        }
        $limit_file = $limit_log_path . '/' . md5($username) . '.limit';
        $time = date('YmdH') . ceil(date('i') / 5);
        $limit_info = [];
        if (is_file($limit_file)) {
            $json_str = file_get_contents($limit_file);
            $limit_info = json_decode($json_str, true);
        }

        if (!$limit_info || $limit_info['time'] != $time) {
            $limit_info = [
                'username' => $username,
                'count' => 0,
                'time' => $time
            ];
        }
        $limit_info['count']++;
        file_put_contents($limit_file, json_encode($limit_info));
        if ($limit_info['count'] >= 5) {
            throw new BusinessException('登录失败次数过多，请5分钟后再试');
        }
    }

    /**
     * 解除登录频率限制
     * @param $username
     * @return void
     */
    protected function removeLoginLimit($username)
    {
        $limit_log_path = runtime_path() . '/login';
        $limit_file = $limit_log_path . '/' . md5($username) . '.limit';
        if (is_file($limit_file)) {
            unlink($limit_file);
        }
    }

    protected function checkDatabaseAvailable()
    {
        if (!config('plugin.admin.database')) {
            throw new BusinessException('请重启webman');
        }
    }

    /**
     * 忘记密码
     * @param Request $request
     */
    function forgetPassword(Request $request)
    {
        return raw_view('account/forgetpassword');
    }

    function send(Request $request)
    {
        $account = $request->post('account');
        $event = $request->post('event');
        $event = $event ?: 'register';

        if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
            $field = 'email';
        } elseif (preg_match('/^[0-9]{10}$/', $account)) {
            $field = 'mobile';
        } else {
            return $this->fail('账号格式不正确');
        }
        $admin = Admin::where([$field => $account])->first();

        if ($field == 'mobile') {
            $last = Sms::getLast($account, $event);

            if ($last && time() - $last->created_at->timestamp < 60) {
                return $this->fail('短信发送频繁');
            }
            // 获取当前小时的开始和结束时间
            $startTime = Carbon::now()->startOfHour();
            $endTime = Carbon::now()->endOfHour();
            $ipSendTotal = Sms::where(['ip' => $request->getRealIp()])->whereBetween('created_at', [$startTime, $endTime])->count();
            if ($ipSendTotal >= 5) {
                return $this->fail('短信发送频繁');
            }
            if ($event) {
                if ($event == 'register' && $admin) {
                    //已被注册
                    return $this->fail('手机号已被注册');
                } elseif ($event == 'changemobile' && $admin) {
                    //被占用
                    return $this->fail('手机号已被占用');
                } elseif (in_array($event, ['login', 'changepwd', 'resetpwd']) && !$admin) {
                    //未注册
                    return $this->fail('该手机号未注册');
                }
            }

            $ret = Sms::send($account, null, $event);
            if ($ret) {
                return $this->success('发送成功');
            } else {
                return $this->fail('发送失败，请检查短信配置是否正确');
            }
        } else {
            $last = Ems::getLast($account, $event);
            if ($last && time() - $last->created_at->timestamp < 60) {
                return $this->fail('邮件发送频繁');
            }

            // 获取当前小时的开始和结束时间
            $startTime = Carbon::now()->startOfHour();
            $endTime = Carbon::now()->endOfHour();
            $ipSendTotal = Ems::where(['ip' => $request->getRealIp()])->whereBetween('created_at', [$startTime, $endTime])->count();
            if ($ipSendTotal >= 5) {
                return $this->fail('邮件发送频繁');
            }

            if ($event) {
                if ($event == 'register' && $admin) {
                    //已被注册
                    return $this->fail('邮箱已被注册');
                } elseif (in_array($event, ['changeemail']) && $admin) {
                    //被占用
                    return $this->fail('邮箱已被占用');
                } elseif (in_array($event, ['login', 'changepwd', 'resetpwd']) && !$admin) {
                    //未注册
                    return $this->fail('邮箱未注册');
                }
            }
            $ret = Ems::send($account, null, $event);
            if ($ret) {
                return $this->success('发送成功');
            } else {
                return $this->fail('发送失败');
            }
        }
    }


    function check(Request $request)
    {
        $account = $request->post('account');
        $event = $request->post('event');
        $event = $event ? $event : 'register';
        $captcha = $request->post('captcha');
        if (!preg_match("/^[a-z0-9]{4,6}\$/i", $captcha)) {
            return $this->fail('验证码格式错误');
        }

        if (filter_var($account, FILTER_VALIDATE_EMAIL)) {
            $field = 'email';
        } elseif (preg_match('/^[0-9]{10}$/', $account)) {
            $field = 'mobile';
        } else {
            return $this->fail('账号格式不正确');
        }
        $admin = Admin::where([$field => $account])->first();
        if ($field == 'email') {
            if ($event) {
                if ($event == 'register' && $admin) {
                    //已被注册
                    return $this->fail('邮箱已被注册');
                } elseif (in_array($event, ['changeemail']) && $admin) {
                    //被占用
                    return $this->fail('邮箱已被占用');
                } elseif (in_array($event, ['changepwd', 'resetpwd']) && !$admin) {
                    //未注册
                    return $this->fail('邮箱未注册');
                }
            }
            $ret = Ems::check($account, $captcha, $event);
            if ($ret) {
                return $this->success('成功');
            } else {
                return $this->fail('验证码不正确');
            }
        } else {
            if ($event) {
                if ($event == 'register' && $admin) {
                    //已被注册
                    return $this->fail('手机号已被注册');
                } elseif ($event == 'changemobile' && $admin) {
                    //被占用
                    return $this->fail('手机号已被占用');
                } elseif (in_array($event, ['changepwd', 'resetpwd']) && !$admin) {
                    //未注册
                    return $this->fail('该手机号未注册');
                }
            }
            $ret = Sms::check($account, $captcha, $event);
            if ($ret) {
                return $this->success();
            } else {
                return $this->fail('验证码不正确');
            }
        }


    }

}

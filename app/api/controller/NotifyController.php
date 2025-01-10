<?php

namespace app\api\controller;

use app\admin\model\User;
use app\admin\model\UsersHr;
use app\api\basic\Base;
use support\Request;

class NotifyController extends Base
{
    protected $noNeedLogin = ['*'];

    function im(Request $request)
    {
        $params = $request->all();
        $CallbackCommand = $params['CallbackCommand'];
        if ($CallbackCommand == 'State.StateChange') {
            //在线状态
            if ($params['Info']['Action'] == 'Login') {
                User::where('id', $params['Info']['To_Account'])->update(['online' => 1]);
            }
            if ($params['Info']['Action'] == 'Disconnect' || $params['Info']['Action'] == 'Logout') {
                User::where('id', $params['Info']['To_Account'])->update(['online' => 0]);
            }
        }
    }

    function beHr(Request $request)
    {
        $invite = $request->get('invite');
        $invite = unserialize($invite);
        $user_id = $invite['user_id'];
        $to_user_id = $invite['to_user_id'];
        $time = $invite['time'];
        if (time() - $time > 60 * 60 * 24) {
            return $this->fail('已过有效期');
        }
        $user = User::find($user_id);
        if ($user->hr->count() >= 10){
            return $this->fail('对方名额已满');
        }
        $toUser = User::find($to_user_id);
        if ($toUser->hr_type != 1){
            return  $this->fail('只有普通HR才能认证');
        }
        UsersHr::create([
            'user_id'=>$user_id,
            'to_user_id'=>$to_user_id,
        ]);
        $toUser->hr_type = 2;
        $toUser->save();
        return $this->success('认证成功');
    }
}

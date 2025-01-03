<?php

namespace app\api\controller;

use app\admin\model\User;
use app\api\basic\Base;
use support\Request;

class NotifyController extends Base
{
    protected $noNeedLogin = ['*'];

    function im(Request $request)
    {
        $params = $request->all();
        $CallbackCommand = $params['CallbackCommand'];
        if ($CallbackCommand == 'State.StateChange'){
            //在线状态
            if ($params['Info']['Action'] == 'Login') {
                User::where('id', $params['Info']['To_Account'])->update(['online' => 1]);
            }
            if ($params['Info']['Action'] == 'Disconnect' || $params['Info']['Action'] == 'Logout') {
                User::where('id', $params['Info']['To_Account'])->update(['online' => 0]);
            }
        }

    }
}

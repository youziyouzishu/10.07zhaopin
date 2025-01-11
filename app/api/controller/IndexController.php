<?php

namespace app\api\controller;

use app\api\basic\Base;
use support\Request;
use Webman\RateLimiter\Annotation\RateLimiter;
use Webman\RateLimiter\Limiter;
use Webman\RateLimiter\RateLimitException;


class IndexController extends Base
{
    protected $noNeedLogin = ['*'];


    public function index(Request $request)
    {
        try {
            #限流器 每个用户1秒内只能请求1次
            Limiter::check('user_' . $request->user_id, 1, 1);
        } catch (RateLimitException $e) {
            return $this->fail(trans('Too Many Requests'));
        }

        return $this->success('ok');
    }


}

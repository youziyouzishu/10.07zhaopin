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

    }


}

<?php

namespace app\api\basic;

use support\Response;

class Base
{
    /**
     * 无需登录及鉴权的方法
     * @var array
     */
    protected $noNeedLogin = [];


    /**
     * 返回格式化json数据
     *
     * @param int $code
     * @param string $msg
     * @param array $data
     * @return Response
     */
    protected function json(int $code, string $msg = 'ok',  $data = []): Response
    {
        return json(['code' => $code, 'data' => $data, 'msg' => $msg]);
    }

    protected function success(string $msg = '成功',  $data = []): Response
    {
        return $this->json(0, $msg, $data);
    }

    protected function fail(string $msg = '失败',  $data = []): Response
    {
        return $this->json(1, $msg, $data);
    }
}
<?php

namespace app\middleware;

use ReflectionClass;
use support\Request;
use Tinywan\Jwt\JwtToken;
use Webman\MiddlewareInterface;
use Webman\Http\Response;


class ApiAuth implements MiddlewareInterface
{
    public function process(Request|\Webman\Http\Request $request, callable $handler): Response
    {

        $user_type = $request->header('user-type');

        if (!empty($user_type) && !in_array($user_type, [0, 1])) {
            return json(['code' => 0, 'msg' => '非法访问', 'data' => []]);
        }

        $request->user_type = $user_type;

        $request->user_id = 0;

        // 通过反射获取控制器哪些方法不需要登录
        if (!empty($request->controller)) {  #路由中return无实际controller
            $controller = new ReflectionClass($request->controller);
            $noNeedLogin = $controller->getDefaultProperties()['noNeedLogin'] ?? [];
            $arr = array_map('strtolower', $noNeedLogin);
            // 是否存在
            if (!in_array(strtolower($request->action), $arr) && !in_array('*', $arr)) {
                // 访问的方法需要登录
                $request->user_id = JwtToken::getCurrentId();
            }

        }
        // 如果是options请求则返回一个空响应，否则继续向洋葱芯穿越，并得到一个响应
        $response = $request->method() == 'OPTIONS' ? response('') : $handler($request);

        // 给响应添加跨域相关的http头
        return $response->withHeaders([
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Origin' => $request->header('origin', '*'),
            'Access-Control-Allow-Methods' => $request->header('access-control-request-method', '*'),
            'Access-Control-Allow-Headers' => $request->header('access-control-request-headers', '*'),
        ]);
    }

}

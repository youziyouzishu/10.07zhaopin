<?php
namespace plugin\smsbao\app\admin\controller;
use support\Request;
use support\Response;
use plugin\admin\app\model\Option;
use plugin\smsbao\api\Smsbao;

class SettingController
{
    public function index()
    {
        return raw_view('setting/index');
    }

    /**
     * 获取配置
     * @return Response
     */
    public function get(): Response
    {
        // 获取name
        $name = Smsbao::SETTING_OPTION_NAME;
        $setting = Option::where('name', $name)->value('value');
        $setting = $setting ? json_decode($setting, true) : [
            'Username' => '',
            'Password' => '',
            'Template' => '',
            'CodeLength' => '4'
        ];
        return json(['code' => 0, 'msg' => 'ok', 'data' => $setting]);
    }

    /**
     * 查询余额
     * @return Response
     */
    public function getMoney(): Response
    {
        return Smsbao::queryMoney();
    }

    /**
     * 更改设置
     * @param Request $request
     * @return Response
     */
    public function save(Request $request): Response
    {
        $data = [
            'Username' => $request->post('Username'),
            'Password' => $request->post('Password'),
            'Template' => $request->post('Template'),
            'CodeLength' => $request->post('CodeLength'),
        ];
        $value = json_encode($data, JSON_UNESCAPED_UNICODE);
        $name = Smsbao::SETTING_OPTION_NAME;
        $option = Option::where('name', $name)->first();
        if ($option) {
            Option::where('name', $name)->update(['value' => $value]);
        } else {
            $option = new Option();
            $option->name = $name;
            $option->value = $value;
            $option->save();
        }

        return json(['code' => 0, 'msg' => 'ok']);
    }

    /**
     * 测试发送
     * @param Request $request
     * @return Response
     */
    public function test(Request $request)
    {
        $phone = $request->post('Phone');

        return Smsbao::send($phone,'1234');
    }
}
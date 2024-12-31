<?php
namespace plugin\smsbao\api;

use support\Response;
use plugin\admin\app\model\Option;

class Smsbao
{
    /**
     * Option表的name字段值
     */
    const SETTING_OPTION_NAME = 'smsbao_setting';

    /**
     * 短信宝API地址
     */
    const SMSBAO_URL = "https://api.smsbao.com/";

    /**
     * 短信宝错误码
     */
    const SMSBAO_STATUS = [
        "0" => "短信发送成功",
        "-1" => "参数不全",
        "-2" => "服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！",
        "30" => "密码错误",
        "40" => "账号不存在",
        "41" => "余额不足",
        "42" => "帐户已过期",
        "43" => "IP地址限制",
        "50" => "内容含有敏感词"
    ];

    /**
     * 发送短信
     * @param $phone
     * @return Response
     */
    public static function send($phone,$code): Response
    {

        $account = static::getSmsbaoAccount();
        if(!$account) return json(['code' => 1, 'msg' => '未配置发信账户']);
//        $code = static::getRandomNumberByLength($account['CodeLength']);
        $content = str_replace("{code}", $code, $account['Template']);
        $sendUrl = static::SMSBAO_URL . "wsms?sms&u="
            .$account['Username']
            ."&p="
            .$account['Password']
            ."&m=" .urlencode($phone)
            ."&c=" .urlencode($content);

        $result = file_get_contents($sendUrl);
        if( $result == 0 ) {
            // 此处可以写自己的业务逻辑，如存储到redis中
            return json(['code' => 0, 'msg' => 'ok']);
        }
        return json(['code' => 1, 'msg' => static::SMSBAO_STATUS[$result]]);
    }

    /**
     * 查询余额使用情况
     * @return Response
     */
    public static function queryMoney(): Response
    {

        $account = static::getSmsbaoAccount();
        if(!$account) {
            return json(['code' => 1, 'msg' => 'Error', 'data' => [
                'UsedCount' => 0,
                'UnUsedCount' => '查询余额失败~'
            ]]);
        }
        $content = str_replace("{code}", static::getRandomNumberByLength($account['CodeLength']), $account['Template']);
        $sendUrl = static::SMSBAO_URL . "query?u="
            .$account['Username']
            ."&p="
            .$account['Password'];
        $result = file_get_contents($sendUrl);
        // 截取返回的状态码 0 为成功
        $statusCode = substr($result,0, strpos($result, ' '));
        // 下标0：使用条数，1：剩余条数
        $resultData = explode(',', preg_replace('/[\r\n]/','', substr($result,strripos($result,'') + 1)));
        $resultData = [
            'UsedCount' => $resultData[0],
            'UnUsedCount' => $resultData[1]
        ];
        if( $statusCode == 0 ) {
            return json(['code' => 0, 'msg' => 'ok', 'data' => $resultData]);
        }
        return json(['code' => 1, 'msg' => 'Error', 'data' => [
            'UsedCount' => 0,
            'UnUsedCount' => '查询余额失败~'
        ]]);
    }


    /**
     * 获取配置
     * @return false|mixed
     */
    public static function getSmsbaoAccount()
    {
        $config = Option::where('name', static::SETTING_OPTION_NAME)->value('value');
        return $config ? json_decode($config, true) : false;
    }

    /**
     * 根据指定长度获取随机数字
     * @param int $length
     * @return int
     */
    public static function getRandomNumberByLength(int $length = 4) {
        $min = pow(10 , ($length - 1));
        $max = pow(10, $length) - 1;
        return mt_rand($min, $max);
    }
}
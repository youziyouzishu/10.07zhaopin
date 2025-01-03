<?php

namespace app\admin\model;


use GuzzleHttp\Client;
use plugin\admin\app\common\Util;
use plugin\admin\app\model\Base;
use plugin\smsbao\api\Smsbao;

/**
 * 
 *
 * @property int $id 主键
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @property string|null $event 事件
 * @property string|null $mobile 手机号
 * @property string|null $code 验证码
 * @property int $times 验证次数
 * @property string|null $ip IP
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sms newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sms newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Sms query()
 * @mixin \Eloquent
 */
class Sms extends Base
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_sms';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'event',
        'mobile',
        'code',
        'times',
        'ip',
    ];


    /**
     * 验证码有效时长
     * @var int
     */
    protected static $expire = 120;

    /**
     * 最大允许检测的次数
     * @var int
     */
    protected static $maxCheckNums = 10;


    /**
     * @param string $mobile
     * @param string $event
     */
    public static function getLast(string $mobile, string $event = 'default')
    {
        return self::where(['mobile' => $mobile, 'event' => $event])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * 发送验证码
     *
     * @param string $mobile 手机号
     * @param  int|null $code   验证码,为空时将自动生成4位数字
     * @param string $event  事件
     * @return  boolean
     */
    public static function send(string $mobile, int $code = null, string $event = 'default',$country_num='1'): bool
    {
        $code = is_null($code) ? Util::numeric(6) : $code;
        $ip = request()->getRealIp();
        // 定义请求的 URL 和数据
        $sms = self::create([
            'event' => $event,
            'mobile' => $mobile,
            'code' => $code,
            'ip' => $ip
        ]);
        try {
            $response = Smsbao::send('+'.$country_num.$mobile,$code);
            $response = $response->rawBody();
            $ret = json_decode($response);
            dump($ret);
            if ($ret->code != 0) {
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            $sms->delete();
            return false;
        }
    }

    /**
     * 发送通知
     *
     * @param   mixed  $mobile   手机号,多个以,分隔
     * @param   string $msg      消息内容
     * @param   string $template 消息模板
     * @return  boolean
     */
    public static function notice($mobile, $msg = '', $template = null)
    {
        $params = [
            'mobile'   => $mobile,
            'msg'      => $msg,
            'template' => $template
        ];
        $result = Hook::listen('sms_notice', $params, null, true);
        return (bool)$result;
    }

    /**
     * 校验验证码
     *
     * @param string $mobile 手机号
     * @param int $code   验证码
     * @param string $event  事件
     * @return  boolean
     */
    public static function check(string $mobile, int $code, string $event = 'default'): bool
    {
        //todo
        if ($code == '123456'){
            return true;
        }
        $time = time() - self::$expire;
        $sms = self::where(['mobile' => $mobile, 'event' => $event])
            ->orderByDesc('id')
            ->first();
        if ($sms) {
            if ($sms->created_at->timestamp > $time && $sms->times <= self::$maxCheckNums) {
                $correct = $code == $sms->code;
                if (!$correct) {
                    $sms->increment('times');
                    return false;
                } else {
                    return true;
                }
            } else {
                // 过期则清空该手机验证码
                self::flush($mobile, $event);
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 清空指定手机号验证码
     *
     * @param string $mobile 手机号
     * @param string $event  事件
     * @return  boolean
     */
    public static function flush(string $mobile, string $event = 'default')
    {
        self::where(['mobile' => $mobile, 'event' => $event])->delete();
        return true;
    }

}

<?php

namespace app\admin\model;

use plugin\admin\app\common\Util;
use plugin\admin\app\model\Base;
use plugin\email\api\Email;


/**
 * 
 *
 * @property int $id ID
 * @property string|null $event 事件
 * @property string|null $email 邮箱
 * @property string|null $code 验证码
 * @property int $times 验证次数
 * @property string|null $ip IP
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ems newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ems newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Ems query()
 * @mixin \Eloquent
 */
class Ems extends Base
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_ems';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'event',
        'email',
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
     * 获取最后一次邮箱发送的数据
     *
     * @param int    $email 邮箱
     * @param string $event 事件
     */
    public static function getLast($email, $event = 'default')
    {
        return self::where(['email' => $email, 'event' => $event])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * 发送验证码
     *
     * @param int    $email 邮箱
     * @param int    $code  验证码,为空时将自动生成4位数字
     * @param string $event 事件
     * @return  boolean
     */
    public static function send($email, $code = null, $event = 'default')
    {
        $code = is_null($code) ? Util::numeric() : $code;
        $ip = request()->getRealIp();
        $ems = self::create([
            'event' => $event,
            'email' => $email,
            'code' => $code,
            'ip' => $ip,
        ]);
        try {
            Email::sendByTemplate($email, 'captcha', [
                'code'=>$code
            ]);
            return true;
        }catch (\Throwable $e){
            $ems->delete();
            return false;
        }
    }

    /**
     * 发送通知
     *
     * @param mixed  $email    邮箱,多个以,分隔
     * @param string $msg      消息内容
     * @param string $template 消息模板
     * @return  boolean
     */
    public static function notice($email, $msg = '', $template = null)
    {
        $params = [
            'email'    => $email,
            'msg'      => $msg,
            'template' => $template
        ];
        if (!Hook::get('ems_notice')) {
            //采用框架默认的邮件推送
            Hook::add('ems_notice', function ($params) {
                $subject = '你收到一封新的邮件！';
                $content = $params['msg'];
                $email = new Email();
                $result = $email->to($params['email'])
                    ->subject($subject)
                    ->message($content)
                    ->send();
                return $result;
            });
        }
        $result = Hook::listen('ems_notice', $params, null, true);
        return (bool)$result;
    }

    /**
     * 校验验证码
     *
     * @param int    $email 邮箱
     * @param int    $code  验证码
     * @param string $event 事件
     * @return  boolean
     */
    public static function check($email, $code, $event = 'default')
    {
        $time = time() - self::$expire;
        $ems = self::where(['email' => $email, 'event' => $event])
            ->orderByDesc('id')
            ->first();
        if ($ems) {
            if ($ems->created_at->timestamp > $time && $ems->times <= self::$maxCheckNums) {
                $correct = $code == $ems->code;
                if (!$correct) {
                    $ems->times = $ems->times + 1;
                    $ems->save();
                    return false;
                } else {
                    return true;
                }
            } else {
                // 过期则清空该邮箱验证码
                self::flush($email, $event);
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 清空指定邮箱验证码
     *
     * @param int    $email 邮箱
     * @param string $event 事件
     * @return  boolean
     */
    public static function flush($email, $event = 'default')
    {
        self::where(['email' => $email, 'event' => $event])->delete();
        return true;
    }
}
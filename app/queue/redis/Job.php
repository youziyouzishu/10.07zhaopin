<?php

namespace app\queue\redis;

use app\admin\model\Resume;
use app\admin\model\User;
use Carbon\Carbon;
use plugin\admin\app\model\Option;
use plugin\email\api\Email;
use plugin\smsbao\api\Smsbao;
use Webman\RedisQueue\Client;
use Webman\RedisQueue\Consumer;

class Job implements Consumer
{
    // 要消费的队列名
    public $queue = 'job';

    // 连接名，对应 plugin/webman/redis-queue/redis.php 里的连接`
    public $connection = 'default';

    // 消费
    public function consume($data)
    {
        $event = $data['event'];
        if ($event == 'job_expire') {
            $job = \app\admin\model\Job::find($data['job_id']);
            if ($job && $job->expire_time->isPast() && $job->status == 1) {
                $job->status = 0;
                $job->save();
            }
        }
        if ($event == 'email_add_hr') {
            $email = $data['email'];
            $company_name = $data['company_name'];
            $position = $data['position'];
            $last_name = $data['last_name'];
            $name = $data['name'];
            $url = $data['url'];
            $template = $data['template'];
            Email::sendByTemplate($email, $template, [
                'company_name' => $company_name,
                'position' => $position,
                'last_name' => $last_name,
                'name' => $name,
                'url' => $url,
            ]);
        }
        if ($event == 'sms_add_hr') {
            $url = $data['url'];
            file_get_contents($url);
        }
        if ($event == 'email_cancel_hr_1') {
            $email = $data['email'];
            Email::sendByTemplate($email, 'cancel_hr_1');
        }
        if ($event == 'email_cancel_hr_2') {
            $email = $data['email'];
            Email::sendByTemplate($email, 'cancel_hr_2');
        }
        if ($event == 'email_cancel_hr_3') {
            $email = $data['email'];
            Email::sendByTemplate($email, 'cancel_hr_3');
        }
        if ($event == 'email_cancel_hr_4') {
            $email = $data['email'];
            Email::sendByTemplate($email, 'cancel_hr_4');
        }
        if ($event == 'email_captcha') {
            $email = $data['email'];
            $code = $data['code'];
            Email::sendByTemplate($email, 'captcha', ['code' => $code]);
        }
        if ($event == 'sms_captcha') {
            $mobile = $data['mobile'];
            $code = $data['code'];
            Smsbao::send($mobile, $code);
        }
        if ($event == 'email_delete_hr_1') {
            $email = $data['email'];
            Email::sendByTemplate($email, 'delete_hr_1');
        }
        if ($event == 'email_delete_hr_2') {
            $email = $data['email'];
            Email::sendByTemplate($email, 'delete_hr_2');
        }

        if ($event == 'vip_expire') {
            $user_id = $data['user_id'];
            $user = User::find($user_id);
            if ($user && !$user->vip_status) {
                #进行vip过期处理
                $name = 'admin_config';
                $config = Option::where('name', $name)->value('value');
                $config = json_decode($config);
                $current_time = Carbon::now();
                if ($user->type == 0) {
                    $add_days = $config->resume_compensation_day;
                    $compensation = $config->resume_compensation;
                } else {
                    $add_days = $config->hr_compensation_day;
                    $compensation = $config->hr_compensation;
                }
                $compensation = Carbon::parse($compensation);
                if ($current_time->isBefore($compensation)) {
                    #进行活动补偿
                    $user->vip_expire_at = $current_time->addDays($add_days);
                    $user->save();
                    Client::send('job', ['event' => 'vip_expire', 'user_id' => $user->id], $user->vip_expire_at->timestamp - time());
                } else {
                    #这次是真过期了
                    if ($user->type == 0) {
                        #如果是求职者  简历只保留默认的  其余的删除
                        $user->resume()->where(['default' => 0])->delete();
                    } else {
                        #如果是HR  简历只保留最后修改的三个  其余的下架
                        $user->job()
                            ->where(['status' => 1])
                            ->orderBy('updated_at', 'desc')
                            ->offset(3)
                            ->update([
                                'status' => 0
                            ]);
                    }
                }
            }

        }

    }

}

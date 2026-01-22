<?php

namespace app\queue\redis;

use app\admin\model\Resume;
use app\admin\model\User;
use Carbon\Carbon;
use PHPMailer\PHPMailer\Exception;
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

    /**
     * @throws Exception
     * @throws \Throwable
     */
    public function consume($data)
    {
        $event = $data['event'];
        try {

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
            if ($event == 'report_submit') {
                $email = $data['email'];
                $id = $data['id'];
                $last_name = $data['last_name'];
                $name = $data['name'];
                $template = $data['template'];
                Email::sendByTemplate($email, $template, [
                    'last_name' => $last_name,
                    'name' => $name,
                    'id' => $id,
                ]);
            }
            if ($event == 'report_reply') {
                $email = $data['email'];
                $last_name = $data['last_name'];
                $name = $data['name'];
                $template = $data['template'];
                $result = $data['result'];
                Email::sendByTemplate($email, $template, [
                    'last_name' => $last_name,
                    'name' => $name,
                    'result' => $result,
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

            if ($event == 'subscribe_notice_ems') {
                $email = $data['email'];
                $companyName = $data['companyName'];
                $userName = $data['userName'];
                $jobTitle = $data['jobTitle'];
                $jobLink = $data['jobLink'];
                Email::sendByTemplate($email, 'subscribe_notice_ems', [
                    'companyName' => $companyName,
                    'userName' => $userName,
                    'jobTitle' => $jobTitle,
                    'jobLink' => $jobLink
                ]);
            }

            if ($event == 'subscribe_notice_sms') {
                $url = $data['url'];
                file_get_contents($url);
            }

            if ($event == 'hr_adjust') {
                $email = $data['email'];
                $name = $data['name'];
                $new_hr_type_text = $data['new_hr_type_text'];
                Email::sendByTemplate($email, 'hr_adjust', [
                    'name' => $name,
                    'new_hr_type_text' => $new_hr_type_text
                ]);
            }

            if ($event == 'hr_adjust_to_parent') {
                $email = $data['email'];
                $name = $data['name'];
                $children_name = $data['children_name'];
                $new_hr_type_text = $data['new_hr_type_text'];
                Email::sendByTemplate($email, 'hr_adjust_to_parent', [
                    'name' => $name,
                    'children_name' => $children_name,
                    'new_hr_type_text' => $new_hr_type_text
                ]);
            }

            if ($event == 'hr_adjust_to_child') {
                $email = $data['email'];
                $name = $data['name'];
                $parent_name = $data['parent_name'];
                Email::sendByTemplate($email, 'hr_adjust_to_child', [
                    'name' => $name,
                    'parent_name' => $parent_name
                ]);
            }
            if ($event == 'forbid_notice') {
                $email = $data['email'];
                $reason = $data['reason'];
                $created_at = $data['created_at'];
                $expired_at = $data['expired_at'];
                $day = $data['day'];
                Email::sendByTemplate($email, 'forbid_notice', [
                    'reason' => $reason,
                    'created_at' => $created_at,
                    'expired_at' => $expired_at,
                    'day' => $day
                ]);
            }

            if ($event == 'resume_compensation') {
                dump('候选人队列补偿');
                //候选人队列补偿
                $days = $data['days'];
                $users = User::where('type', 0)->where('vip_expire_at', '>', Carbon::now())->get();
                $users->each(function (User $user) use ($days) {
                    $user->vip_expire_at = $user->vip_expire_at->addDays((int)$days);
                    $user->save();
                });
            }

            if ($event == 'hr_compensation') {
                dump('HR队列补偿');
                //HR队列补偿
                $days = $data['days'];
                $users = User::where('type', 1)->where('vip_expire_at', '>', Carbon::now())->get();
                $users->each(function (User $user) use ($days) {
                    $user->vip_expire_at = $user->vip_expire_at->addDays((int)$days);
                    $user->save();
                });
            }

            if ($event == 'vip_expire') {
                $user_id = $data['user_id'];
                $user = User::find($user_id);
                if ($user && !$user->vip_status) {
                    #进行vip过期处理
                    if ($user->type == 0) {
                        #如果是求职者  简历只保留默认的  其余的删除
                        $user->resume()->where(['default' => 0])->delete();
                    } else {
                        #如果是HR  简历只保留最后修改的三个  其余的下架
                        $jobs = $user->job()
                            ->where(['status' => 1])
                            ->orderBy('updated_at', 'desc')
                            ->offset(3)
                            ->limit(PHP_INT_MAX)
                            ->get();
                        $jobs->each(function ($job) {
                            $job->status = 0;
                            $job->save();
                        });
                    }
                }

            }
        } catch (\Throwable $e) {
            dump('队列失败',$data);
            dump($e->getMessage());
            throw $e;
        }

    }

}

<?php

namespace app\queue\redis;

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
        if ($event == 'job_expire'){
            $job = \app\admin\model\Job::find($data['job_id']);
            if ($job){
                if ($job->expire_time < time() && $job->status == 1){
                    $job->status = 0;
                    $job->save();
                }
            }
        }
    }
            
}

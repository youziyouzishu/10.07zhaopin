<?php

namespace app\admin\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use plugin\admin\app\model\Base;



/**
 * 
 *
 * @property int $id 主键
 * @property int $resume_user_id 简历用户
 * @property int $resume_id 简历
 * @property int $job_id 岗位
 * @property int $job_user_id 岗位用户
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间
 * @property-read \app\admin\model\Job|null $job
 * @property-read \app\admin\model\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SendLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SendLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SendLog onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SendLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SendLog withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SendLog withoutTrashed()
 * @property-read \app\admin\model\User|null $jobUser
 * @property-read \app\admin\model\User|null $resumeUser
 * @mixin \Eloquent
 */
class SendLog extends Base
{
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_send_log';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'resume_id',
        'job_id',
        'job_user_id',
        'resume_user_id'
    ];

    function job()
    {
        return $this->belongsTo(Job::class, 'job_id', 'id');
    }

    function jobUser()
    {
        return $this->belongsTo(User::class, 'job_user_id', 'id');
    }

    function resumeUser()
    {
        return $this->belongsTo(User::class, 'resume_user_id', 'id');
    }

}
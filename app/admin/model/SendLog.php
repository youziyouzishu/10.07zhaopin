<?php

namespace app\admin\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use plugin\admin\app\model\Base;


/**
 * 
 *
 * @property int $id 主键
 * @property int $resume_id 简历
 * @property int $job_id 岗位
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SendLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SendLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SendLog query()
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SendLog onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SendLog withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SendLog withoutTrashed()
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
    ];

    function job()
    {
        return $this->belongsTo(Job::class, 'job_id', 'id');
    }

}
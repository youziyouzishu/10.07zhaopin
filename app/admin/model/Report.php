<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;

/**
 * 
 *
 * @property int $id 主键
 * @property int $user_id 举报人
 * @property int|null $to_user_id 被举报人
 * @property string $reason 举报理由
 * @property string $explain 举报说明
 * @property string $images 证明
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Report query()
 * @mixin \Eloquent
 */
class Report  extends Base
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_report';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'to_user_id',
        'reason',
        'explain',
        'images',
    ];

    function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id', 'id');
    }
}
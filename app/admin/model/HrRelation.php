<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;

/**
 * 
 *
 * @property int $id 主键
 * @property int $user_id 用户
 * @property int $to_user_id to用户
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @property-read \app\admin\model\User|null $toUser
 * @property-read \app\admin\model\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HrRelation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HrRelation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HrRelation query()
 * @mixin \Eloquent
 */
class HrRelation extends Base
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_hr_relation';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'to_user_id',
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
<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;

/**
 * 
 *
 * @property int $id 主键
 * @property int $user_id 邀请人
 * @property int $to_user_id 被邀请人
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UsersHr newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UsersHr newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UsersHr query()
 * @property-read \app\admin\model\User|null $toUser
 * @property-read \app\admin\model\User|null $user
 * @mixin \Eloquent
 */
class UsersHr extends Base
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_users_hr';

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
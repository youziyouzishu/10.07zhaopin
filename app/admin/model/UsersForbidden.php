<?php

namespace app\admin\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use plugin\admin\app\model\Base;


/**
 * 
 *
 * @property int $id 主键
 * @property int $user_id 用户
 * @property \Illuminate\Support\Carbon $expired_at 过期时间
 * @property string $reason 原因
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @property-read \app\admin\model\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UsersForbidden newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UsersForbidden newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UsersForbidden onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UsersForbidden query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UsersForbidden withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UsersForbidden withoutTrashed()
 * @mixin \Eloquent
 */
class UsersForbidden extends Base
{
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_users_forbidden';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $casts = [
        'expired_at' => 'datetime',
    ];

    protected $fillable = ['user_id', 'expired_at', 'reason'];

    function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    
    
    
}

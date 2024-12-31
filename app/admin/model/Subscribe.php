<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;



/**
 * 
 *
 * @property int $id 主键
 * @property int $user_id 用户
 * @property string $company_name 公司
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscribe newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscribe newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscribe query()
 * @mixin \Eloquent
 */
class Subscribe extends Base
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_subscribe';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'company_name',
    ];
}
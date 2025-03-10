<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;

/**
 *
 *
 * @property int $id 主键
 * @property string|null $name 名称
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Province newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Province newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Province query()
 * @mixin \Eloquent
 */
class Province extends Base
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_province';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
    ];


}
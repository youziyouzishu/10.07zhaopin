<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;

/**
 *
 *
 * @property int $id 主键
 * @property string $name 名称
 * @property int $qs_ranking QS排名
 * @property int $us_ranking US排名
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|University newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|University newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|University query()
 * @mixin \Eloquent
 */
class University extends Base
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_university';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'qs_ranking',
        'us_ranking',
    ];

}
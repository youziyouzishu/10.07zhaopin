<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;

/**
 * 
 *
 * @property int $id 主键
 * @property int $type 类型:1=HR,2=Seeker
 * @property string $title 标题
 * @property string $content 内容
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemNotice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemNotice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SystemNotice query()
 * @mixin \Eloquent
 */
class SystemNotice extends Base
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_system_notice';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';


}
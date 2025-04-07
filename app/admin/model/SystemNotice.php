<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;

/**
 * @property integer $id 主键(主键)
 * @property integer $type 类型:1=HR,2=Seeker
 * @property string $title 标题
 * @property string $content 内容
 * @property string $image 图片
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
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

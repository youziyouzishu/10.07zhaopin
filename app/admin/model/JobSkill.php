<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;

/**
 * 
 *
 * @property int $id 主键
 * @property int $job_id 所属岗位
 * @property string $name 技术栈
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobSkill newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobSkill newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobSkill query()
 * @mixin \Eloquent
 */
class JobSkill extends Base
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_job_skill';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'job_id',
        'name',
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper(trim($value));
    }




}
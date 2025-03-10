<?php

namespace app\admin\model;


use plugin\admin\app\model\Base;


/**
 *
 *
 * @property int $id 主键
 * @property int $resume_id 所属简历
 * @property string $name 名称
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResumeSkill newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResumeSkill newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResumeSkill query()
 * @mixin \Eloquent
 */
class ResumeSkill extends Base
{



    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_resume_skill';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'resume_id',
        'name'
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper(trim($value));
    }




}
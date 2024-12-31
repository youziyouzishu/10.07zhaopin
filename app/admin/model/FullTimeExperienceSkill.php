<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;



/**
 * 
 *
 * @property int $id 主键
 * @property int $full_id
 * @property string $name 技术栈
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FullTimeExperienceSkill newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FullTimeExperienceSkill newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FullTimeExperienceSkill query()
 * @property-read \app\admin\model\FullTimeExperience|null $fullTimeExperience
 * @mixin \Eloquent
 */
class FullTimeExperienceSkill extends Base
{


    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_full_time_experience_skill';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'full_id',
        'name',
    ];

    function fullTimeExperience()
    {
        return $this->belongsTo(FullTimeExperience::class,'full_id','id');
    }


    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper(trim($value));
    }



}
<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;



/**
 * 
 *
 * @property int $id 主键
 * @property int $project_id
 * @property string $name 技术栈
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectExperienceSkill newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectExperienceSkill newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectExperienceSkill query()
 * @property-read \app\admin\model\ProjectExperience|null $projectExperience
 * @mixin \Eloquent
 */
class ProjectExperienceSkill extends Base
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_project_experience_skill';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'project_id',
        'name',
    ];

    function projectExperience()
    {
        return $this->belongsTo(ProjectExperience::class,'project_id','id');
    }


    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper(trim($value));
    }


}
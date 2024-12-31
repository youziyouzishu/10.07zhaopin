<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;



/**
 * 
 *
 * @property int $id
 * @property int $resume_id 所属简历
 * @property string $project_name 项目名称
 * @property string|null $project_start_date 项目开始日期
 * @property string|null $project_end_date 项目结束日期
 * @property string $project_location 项目地点
 * @property string $project_description 项目简介
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @property-read \app\admin\model\Resume|null $resume
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectExperience newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectExperience newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectExperience query()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\ProjectExperienceSkill> $skill
 * @mixin \Eloquent
 */
class ProjectExperience extends Base
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_project_experience';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'resume_id',
        'project_name',
        'project_start_date',
        'project_end_date',
        'project_location',
        'project_description',
    ];

    function resume()
    {
        return $this->belongsTo(Resume::class,'resume_id','id');
    }

    function skill()
    {
        return $this->hasMany(ProjectExperienceSkill::class,'project_id','id');
    }


}
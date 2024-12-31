<?php

namespace app\admin\model;

use Carbon\Carbon;
use plugin\admin\app\model\Base;



/**
 * 
 *
 * @property int $id
 * @property int $resume_id 所属简历
 * @property string $company_name 公司名
 * @property string $position_name 岗位名
 * @property string|null $start_date 工作开始时间
 * @property string|null $end_date 工作结束时间
 * @property string|null $internship_description 工作描述
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @property-read \app\admin\model\Resume|null $resume
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FullTimeExperience newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FullTimeExperience newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FullTimeExperience query()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\FullTimeExperienceSkill> $skill
 * @mixin \Eloquent
 */
class FullTimeExperience extends Base
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_full_time_experience';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'resume_id',
        'company_name',
        'position_name',
        'start_date',
        'end_date',
        'internship_description',
    ];

    public function resume()
    {
        return $this->belongsTo(Resume::class,'resume_id','id');
    }

    function skill()
    {
        return $this->hasMany(FullTimeExperienceSkill::class,'full_id','id');
    }



}
<?php

namespace app\admin\model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\EnumeratesValues;
use plugin\admin\app\model\Base;


/**
 *
 *
 * @property int $id
 * @property int $user_id 所属用户
 * @property string $name 简历名称
 * @property string $tech_stack 技术栈
 * @property string $file 简历附件
 * @property int $default 默认:0=false=否,1=true=是
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resume newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resume newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resume query()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\EducationalBackground> $educationalBackground
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\FullTimeExperience> $fullTimeExperience
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\InternshipExperience> $internshipExperience
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\ProjectExperience> $projectExperience
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\FullTimeExperienceSkill> $fulltimeSkill
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\InternshipExperienceSkill> $internshipSkill
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\ProjectExperienceSkill> $projectSkill
 * @property-read mixed $major
 * @property-read mixed $educational_background_to_job
 * @property-read \app\admin\model\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\SendLog> $sendLog
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\ResumeSkill> $skill
 * @property string|null $end_graduation_date 毕业日期
 * @property-read mixed $top_q_s_ranking_between
 * @property-read mixed $top_u_s_ranking_between
 * @property float $total_full_time_experience_years 全职工作年限
 * @property int $total_internship_experience_number 实习段数
 * @property int $top_degree 最高学历:0=High School or Below=高中及以下,1=Associate Degree=副学士学位,2=Bachelor's Degree=本科学位,3=Master's Degree=硕士学位,4=Doctoral Degree (PhD)=博士学位
 * @property-read mixed $top_degree_text
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resume onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resume withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resume withoutTrashed()
 * @mixin \Eloquent
 */
class Resume extends Base
{

    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_resume';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'name',
        'tech_stack',
        'file',
        'default',
    ];

    protected $appends = ['top_degree_text'];

    function user()
    {
        return $this->belongsTo(User::class,'user_id','id');
    }

    function educationalBackground()
    {
        return $this->hasMany(EducationalBackground::class,'resume_id','id');
    }

    function fullTimeExperience()
    {
        return $this->hasMany(FullTimeExperience::class,'resume_id','id');
    }

    function internshipExperience()
    {
        return $this->hasMany(InternshipExperience::class,'resume_id','id');
    }

    function projectExperience()
    {
        return $this->hasMany(ProjectExperience::class,'resume_id','id');
    }

    #项目技术栈
    function projectSkill()
    {
        return $this->hasManyThrough(ProjectExperienceSkill::class, ProjectExperience::class, 'resume_id', 'project_id', 'id', 'id');
    }

    #实习技术栈
    function internshipSkill()
    {
        return $this->hasManyThrough(InternshipExperienceSkill::class, InternshipExperience::class, 'resume_id', 'internship_id', 'id', 'id');
    }

    #全职技术栈
    function fulltimeSkill()
    {
        return $this->hasManyThrough(FullTimeExperienceSkill::class, FullTimeExperience::class, 'resume_id', 'full_id', 'id', 'id');
    }

    /**
     * 获取关联的 EducationalBackground 模型的所有专业
     */
    function getMajorAttribute($value)
    {
        // 直接获取该 Resume 下最高学历的 EducationalBackground 记录
        return $this->educationalBackground()->pluck('major');
    }

    function sendLog()
    {
        return $this->hasMany(SendLog::class,'resume_id','id');
    }

    function skill()
    {
        return $this->hasMany(ResumeSkill::class,'resume_id','id');
    }

    function getTopDegreeTextAttribute($value)
    {
        $value = $value ?: ($this->top_degree ?? '');
        $list = $this->getTopDegreeList();
        return $list[$value] ?? '';
    }

    function getTopDegreeList()
    {
        return [
            0 => 'High School or Below',
            1 => 'Associate Degree',
            2 => 'Bachelor\'s Degree',
            3 => 'Master\'s Degree',
            4 => 'Doctoral Degree',
        ];
    }


}
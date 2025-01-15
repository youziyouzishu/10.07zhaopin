<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;


/**
 * 
 *
 * @property int $id
 * @property int $user_id 用户
 * @property string $position_name 岗位名称
 * @property string $position_description 岗位描述
 * @property string $minimum_salary 最低工资
 * @property string $maximum_salary 最高工资
 * @property string $position_type 工作类型
 * @property int $adult 是否只招聘成年人:0=false=否,1=true=是
 * @property int $work_mode 工作模式:0=In-Person=现场办公,1=Hybrid=混合办公,2=Remote=远程办公
 * @property int $sponsorship 签证支持:0=false=否,1=true=是
 * @property int $project_tech_stack_match 项目技术栈匹配:0=false=否,1=true=是
 * @property int $internship_tech_stack_match 实习技术栈匹配:0=false=否,1=true=是
 * @property int $full_time_tech_stack_match 全职技术栈匹配:0=false=否,1=true=是
 * @property int $degree_requirements 学历要求:0=High School or Below=高中及以下,1=Associate Degree=副学士学位,2=Bachelor's Degree=学士学位,3=Master's Degree=硕士学位,4=Doctoral Degree (PhD)=博士学位
 * @property int $degree_qs_ranking 学历QS排名:0=Null,1=Top 10=前10,2=Top 30=前30,3=Top 50=前50,4=Top 70=前70,5=Top 100=前100,6=Top 150=前150,7=Top 200=前200
 * @property int $degree_us_ranking 学历US排名:0=Null,1=Top 10=前10,2=Top 30=前30,3=Top 50=前50,4=Top 70=前70,5=Top 100=前100,6=Top 150=前150,7=Top 200=前200
 * @property int $overall_gpa_requirement 总绩点要求（满分4.0）（0-4）
 * @property int $major_gpa_requirement 专业绩点要求（满分4.0）（0-4）
 * @property int $minimum_full_time_internship_experience_years 全职工作最低年限要求（0-15）
 * @property int $minimum_internship_experience_number 实习工作最低段数要求（0-5）
 * @property string $top_secret 绝密:0=false=否,1=true=是
 * @property string|null $graduation_date 应届生毕业日期
 * @property string $position_location 工作地址
 * @property string $expected_number_of_candidates 招聘人数
 * @property int $status 状态:0=Removal=下架,1=Publish=上架
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @property int $from_limitation 是否接受受限国家:0=false=否,1=true=是
 * @property int $us_citizen 是否指招募美国公民:0=false=否,1=true=是
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\JobMajor> $major
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\JobNiceSkill> $niceSkill
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\JobSkill> $skill
 * @property-read \app\admin\model\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Job newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Job newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Job query()
 * @property int $default 默认:0=false=否,1=true=是
 * @property int $allow_duplicate_application 是否允许已申请用户重复申请:0=false,1=true
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\SendLog> $sendLog
 * @property \Illuminate\Support\Carbon|null $expire_time 过期时间
 * @mixin \Eloquent
 */
class Job extends Base
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_job';

    protected $casts = [
        'expire_time'=>'datetime:Y-m-d H:i:s',
    ];

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'position_name',
        'position_description',
        'minimum_salary',
        'maximum_salary',
        'position_type',
        'work_mode',
        'sponsorship',
        'tech_stack_requirements',
        'nice_to_have',
        'project_tech_stack_match',
        'internship_tech_stack_match',
        'full_time_tech_stack_match',
        'degree_requirements',
        'degree_qs_ranking',
        'degree_us_ranking',
        'overall_gpa_requirement',
        'major_requirement',
        'major_gpa_requirement',
        'top_secret',
        'graduation_date',
        'position_location',
        'expected_number_of_candidates',
        'status',
        'from_limitation',
        'us_citizen',
        'minimum_full_time_internship_experience_years',
        'minimum_internship_experience_number',
    ];

    function skill()
    {
        return $this->hasMany(JobSkill::class, 'job_id', 'id');
    }

    function niceSkill()
    {
        return $this->hasMany(JobNiceSkill::class, 'job_id', 'id');
    }


    function major()
    {
        return $this->hasMany(JobMajor::class, 'job_id', 'id');
    }
    

    function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    function sendLog()
    {
        return $this->hasMany(SendLog::class, 'job_id', 'id');
    }

}
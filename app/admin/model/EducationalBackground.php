<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;



/**
 * 
 *
 * @property int $id
 * @property int $resume_id 所属简历
 * @property int $university_id 所属学校
 * @property string $major 专业
 * @property int $degree 学历:0=High School or Below=高中及以下,1=Associate Degree=副学士学位,2=Bachelor of Arts (BA)=文科学士学位,3=Bachelor of Science (BS)=理科学士学位,4=Master of Arts (MA)=文科硕士学位,5=Master of Science (MS)=理科硕士学位,6=Doctor of Philosophy (PhD)=博士学位,7=Professional Degree (e.g., MD, JD, DDS)=职业学位（如医学博士、法学博士、牙医学博士）
 * @property int $degree_to_job 映射岗位学历要求:0=High School or Below=高中及以下,1=Associate Degree=副学士学位,2=Bachelor's Degree=本科学位,3=Master's Degree=硕士学位,4=Doctoral Degree (PhD)=博士学位
 * @property string $enrollment_date 入学时间
 * @property string $graduation_date 毕业时间
 * @property float $cumulative_gpa 总绩点（满分4.0）
 * @property float $major_gpa 专业绩点（满分4.0）
 * @property int $qs_ranking QS排名
 * @property int $us_ranking US排名
 * @property int $top_qs_ranking QS排名TOP:0=Null,1=Top 10=前10,2=Top 30=前30,3=Top 50=前50,4=Top 70=前70,5=Top 100=前100,6=Top 150=前150,7=Top 200=前200
 * @property int $top_us_ranking US排名TOP:0=Null,1=Top 10=前10,2=Top 30=前30,3=Top 50=前50,4=Top 70=前70,5=Top 100=前100,6=Top 150=前150,7=Top 200=前200
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @property-read \app\admin\model\Resume|null $resume
 * @property-read \app\admin\model\University|null $university
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EducationalBackground newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EducationalBackground newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EducationalBackground query()
 * @property-read mixed $degree_text
 * @mixin \Eloquent
 */
class EducationalBackground extends Base
{


    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_educational_background';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';
    protected $fillable = [
        'resume_id',
        'university_id',
        'major',
        'degree',
        'degree_to_job',
        'enrollment_date',
        'graduation_date',
        'cumulative_gpa',
        'major_gpa',
        'qs_ranking',
        'us_ranking',
        'top_qs_ranking',
        'top_us_ranking',
    ];

    protected $appends = ['degree_text'];

    public function resume()
    {
        return $this->belongsTo(Resume::class, 'resume_id', 'id');
    }

    function university()
    {
        return $this->belongsTo(University::class, 'university_id', 'id');
    }

    function getDegreeTextAttribute($value)
    {
        $value = $value ?: ($this->degree ?? '');
        $list = $this->getDegreeList();
        return $list[$value] ?? '';
    }


    function getDegreeList()
    {
        return [
            0 => 'High School or Below',
            1 => 'Associate Degree',
            2 => 'Bachelor of Arts',
            3 => 'Bachelor of Science',
            4 => 'Master of Arts',
            5 => 'Master of Science',
            6 => 'Doctor of Philosophy (PhD)',
            7 => 'Professional Degree (e.g., MD, JD, DDS)',
        ];
    }


}
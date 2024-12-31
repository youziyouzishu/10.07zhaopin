<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;



/**
 * 
 *
 * @property int $id 主键
 * @property int $internship_id
 * @property string $name 技术栈
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InternshipExperienceSkill newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InternshipExperienceSkill newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InternshipExperienceSkill query()
 * @property-read \app\admin\model\InternshipExperience|null $InternshipExperience
 * @mixin \Eloquent
 */
class InternshipExperienceSkill extends Base
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_internship_experience_skill';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'internship_id',
        'name',
    ];

    function InternshipExperience()
    {
        return $this->belongsTo(InternshipExperience::class,'internship_id','id');
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = strtoupper(trim($value));
    }





}
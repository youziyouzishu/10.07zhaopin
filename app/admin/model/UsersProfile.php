<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;

/**
 * 
 *
 * @property int $id 主键
 * @property int $user_id 所属用户
 * @property string $last_name 姓氏
 * @property string $name 名字
 * @property int $adult 年满18:0=false=否,1=true=是
 * @property int $top_secret 绝密:0=false=否,1=true=是
 * @property int $united_states_authorization 美国授权:0=false=否,1=true=是
 * @property int $sponsorship 签证担保:0=false=否,1=true=是
 * @property int $from_limitation 来自受限国家:0=false=否,1=true=是
 * @property int $gender 性别:0=Male,1=Female,2=Non-binary,3=Prefer not to say
 * @property int $sexual_orientation 性取向:0=Heterosexual,1=Homosexual,2=Bisexual,3=Other,4=Prefer not to say
 * @property int $race 种族:0=White,1=Black or African American,2=Asian,3=Native American or Alaska Native,4=Two or more races,5=Prefer not to say
 * @property int $disability 残疾:0=false=否,1=true=是
 * @property int $veteran 老兵:0=false=否,1=true=是
 * @property string $address 地址
 * @property string $city 城市
 * @property string $country 国家
 * @property string $postal_code 邮政编码
 * @property string $middle_name 中间名
 * @property int $us_citizen 美国公民:0=false=否,1=true=是
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UsersProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UsersProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UsersProfile query()
 * @property string $salutation 问候语
 * @mixin \Eloquent
 */
class UsersProfile extends Base
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_users_profile';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'last_name',
        'name',
        'adult',
        'top_secret',
        'united_states_authorization',
        'sponsorship',
        'from_limitation',
        'gender',
        'sexual_orientation',
        'race',
        'disability',
        'veteran',
        'address',
        'city',
        'country',
        'postal_code',
        'us_citizen',
    ];

}
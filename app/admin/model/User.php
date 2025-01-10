<?php

namespace app\admin\model;

use Illuminate\Database\Eloquent\SoftDeletes;
use plugin\admin\app\model\Base;



/**
 * 
 *
 * @property int $id 主键
 * @property string $nickname 昵称
 * @property string $password 密码
 * @property string $sex 性别
 * @property string|null $avatar 头像
 * @property string|null $email 邮箱
 * @property string|null $mobile 手机
 * @property int $level 等级
 * @property string|null $birthday 生日
 * @property string $money 余额(元)
 * @property int $score 积分
 * @property string|null $last_time 登录时间
 * @property string|null $last_ip 登录ip
 * @property string|null $join_time 注册时间
 * @property string|null $join_ip 注册ip
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @property int $role 角色
 * @property int $status 禁用
 * @property string|null $type 用户类型:seeker=求职者,hr=HR
 * @property string|null $name 名字
 * @property string|null $last_name 姓氏
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @property string|null $country_num 国家号码
 * @property int $online 在线状态:0=否,1=是
 * @property-read \app\admin\model\UsersProfile|null $profile
 * @property string $company_name 公司ID
 * @property string $position 岗位
 * @property string $company_explain 公司描述
 * @property string $salutation 问候语
 * @property string|null $vip_expire_at vip过期时间
 * @property int $notice_type 通知类型:0=邮箱通知,1=短信通知
 * @property int $vip_status 会员状态:0=未开通,1=已开通
 * @property int $show_status 展示简历状态:0=否=false,1=是=true
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间
 * @property string $middle_name 中间名
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \app\admin\model\UsersHr> $hr
 * @property int $hr_type HR类型:0=Null=无,1=Regular HR=普通HR,2=Verified HR=认证HR,3=Super HR=超级HR
 * @mixin \Eloquent
 */
class User extends Base
{
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_users';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'nickname',
        'password',
        'sex',
        'avatar',
        'email',
        'mobile',
        'level',
        'birthday',
        'money',
        'score',
        'last_time',
        'last_ip',
        'join_time',
        'join_ip',
        'role',
        'status',
        'type',
        'name',
        'last_name',
        'hr_type',
        'country_num',
        'online',
        'company_name',
        'position',
        'company_explain',
        'salutation',
        'vip_expire_at',
        'notice_type',
        'vip_status',
        'show_status',
        'middle_name',
    ];

    function profile()
    {
        return $this->hasOne(UsersProfile::class,'user_id','id');
    }

    function hr()
    {
        return $this->hasMany(UsersHr::class,'user_id','id');
    }





}
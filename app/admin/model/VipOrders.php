<?php

namespace app\admin\model;

use plugin\admin\app\model\Base;

/**
 * 
 *
 * @property int $id 主键
 * @property int $user_id 用户
 * @property int $vip_id VIP
 * @property string $pay_amount 支付金额
 * @property string $ordersn 订单编号
 * @property int $status 状态:0=待支付,1=已支付,2=已结算,3=结算失败
 * @property string $transaction_id 交易ID
 * @property string $subscription_id 订阅ID
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VipOrders newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VipOrders newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VipOrders query()
 * @property-read \app\admin\model\User|null $user
 * @property-read \app\admin\model\Vip|null $vip
 * @mixin \Eloquent
 */
class VipOrders extends Base
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wa_vip_orders';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'vip_id',
        'pay_amount',
        'ordersn',
        'status',
    ];

    function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    function vip()
    {
        return $this->belongsTo(Vip::class, 'vip_id', 'id');
    }


}
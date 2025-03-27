<?php

namespace app\admin\controller;

use app\admin\model\User;
use Carbon\Carbon;
use support\Request;
use support\Response;
use app\admin\model\UsersForbidden;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;
use Webman\RedisQueue\Client;

/**
 * 封禁列表
 */
class UsersForbiddenController extends Crud
{

    /**
     * @var UsersForbidden
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new UsersForbidden;
    }

    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('users-forbidden/index');
    }

    /**
     * 查询
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function select(Request $request): Response
    {
        $deleted_status = $request->get('deleted_status');
        [$where, $format, $limit, $field, $order] = $this->selectInput($request);
        $query = $this->doSelect($where, $field, $order)
            ->with(['user' => function ($query) {
                $query->withTrashed();
            }])
            ->when(!empty($deleted_status), function ($query) use ($deleted_status) {
                if ($deleted_status === '1') {
                    $query->whereHas('user', function ($query) {
                        $query->withTrashed();;
                    });
                }
                if ($deleted_status === '2') {
                    $query->whereHas('user', function ($query) {
                        $query->onlyTrashed();;
                    });
                }
                if ($deleted_status === '3') {
                    $query->whereHas('user', function ($query) {
                        $query->withoutTrashed();;
                    });
                }
            });
        return $this->doFormat($query, $format, $limit);
    }

    /**
     * 插入
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function insert(Request $request): Response
    {
        if ($request->method() === 'POST') {
            $expired_at = $request->post('expired_at');
            $expired_at = Carbon::parse($expired_at);
            if ($expired_at->isPast()) {
                return $this->fail('过期时间不能小于当前时间');
            }
            $data = $this->insertInput($request);
            $id = $this->doInsert($data);
            $row = UsersForbidden::find($id);
            $users_forbid = UsersForbidden::where('user_id', $row->user_id)->orderByDesc('expired_at')->first();
            if ($users_forbid->id == $row->id) {
                $day = (int)ceil($row->created_at->diffInDays($row->expired_at));
                Client::send('job', [
                    'event' => 'forbid_notice',
                    'email' => $row->user->email,
                    'template' => 'forbid_notice',
                    'reason'=>$row->reason,
                    'created_at'=>$row->created_at,
                    'expired_at'=>$row->expired_at,
                    'day'=>$day
                ]);
            }
            return $this->json(0, 'ok', ['id' => $id]);
        }
        return view('users-forbidden/insert');
    }

    /**
     * 更新
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function update(Request $request): Response
    {
        if ($request->method() === 'POST') {
            return parent::update($request);
        }
        return view('users-forbidden/update');
    }

}

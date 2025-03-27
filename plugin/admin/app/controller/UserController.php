<?php

namespace plugin\admin\app\controller;

use app\admin\model\UsersHr;
use plugin\admin\app\model\User;
use support\exception\BusinessException;
use support\Request;
use support\Response;
use Throwable;
use Webman\RedisQueue\Client;

/**
 * 用户管理
 */
class UserController extends Crud
{

    /**
     * @var User
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new User;
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
        $query = $this->doSelect($where, $field, $order)->when(!empty($deleted_status), function ($query) use ($deleted_status) {
            if ($deleted_status === '1') {
                $query->withTrashed();
            }
            if ($deleted_status === '2') {
                $query->onlyTrashed();
            }
        });
        return $this->doFormat($query, $format, $limit);
    }

    /**
     * 浏览
     * @return Response
     * @throws Throwable
     */
    public function index(): Response
    {
        return raw_view('user/index');
    }

    /**
     * 插入
     * @param Request $request
     * @return Response
     * @throws BusinessException|Throwable
     */
    public function insert(Request $request): Response
    {
        if ($request->method() === 'POST') {
            return parent::insert($request);
        }
        return raw_view('user/insert');
    }

    /**
     * 更新
     * @param Request $request
     * @return Response
     * @throws BusinessException|Throwable
     */
    public function update(Request $request): Response
    {
        if ($request->method() === 'POST') {
            $id = $request->post('id');
            $vip_expire_at = $request->post('vip_expire_at');
            $hr_type = $request->post('hr_type');
            $user = \app\admin\model\User::find($id);
            if (!empty($vip_expire_at) && $vip_expire_at != $user->vip_expire_at) {
                Client::send('job', ['event' => 'vip_expire', 'user_id' => $user->id], $user->vip_expire_at->timestamp - time());
            }
            if ($user->hr_type == 1 && in_array($hr_type, [2, 3])) {
                //普通HR->认证超级HR
                Client::send('job', ['event' => 'hr_adjust', 'email' => $user->email, 'template' => 'hr_adjust', 'name' => $user->name, 'new_hr_type_text' => (new \app\admin\model\User())->getHrTypeList()[$hr_type]]);
            }
            if ($user->hr_type == 2 && in_array($hr_type,[1,3])) {
                //认证HR->超级HR || 普通HR
                Client::send('job', ['event' => 'hr_adjust', 'email' => $user->email, 'template' => 'hr_adjust', 'name' => $user->name, 'new_hr_type_text' => (new \app\admin\model\User())->getHrTypeList()[$hr_type]]);
                //如果该用户有上级HR 则给上级HR也发一份
                $parent = UsersHr::where(['to_user_id' => $user->id])->first();
                if ($parent) {
                    Client::send('job', ['event' => 'hr_adjust_to_parent', 'email' => $parent->user->email, 'template' => 'hr_adjust_to_parent', 'name' => $parent->user->name, 'children_name' => $parent->toUser->name,'new_hr_type_text' => (new \app\admin\model\User())->getHrTypeList()[$hr_type]]);
                    $parent->delete();
                }
            }
            if ($user->hr_type == 3 && in_array($hr_type, [1, 2])) {
                //超级HR-》认证HR 普通HR
                Client::send('job', ['event' => 'hr_adjust', 'email' => $user->email, 'template' => 'hr_adjust', 'name' => $user->name, 'hr_type_text' => (new \app\admin\model\User())->getHrTypeList()[$hr_type]]);
                $childs = UsersHr::where(['user_id' => $user->id])->get();
                if ($childs->isNotEmpty()) {
                    $childs->each(function (UsersHr $child) {
                        //只有认证HR才发送
                        if ($child->toUser->hr_type == 2) {
                            $child->toUser->hr_type = 1;
                            $child->toUser->save();
                            Client::send('job', ['event' => 'hr_adjust_to_child', 'email' => $child->toUser->email, 'template' => 'hr_adjust_to_child', 'name' => $child->toUser->name, 'parent_name' => $child->user->name]);
                            $child->delete();
                        }
                    });
                }
            }


            return parent::update($request);
        }
        return raw_view('user/update');
    }

}

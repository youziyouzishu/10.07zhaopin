<?php

namespace app\admin\controller;

use support\Request;
use support\Response;
use app\admin\model\Job;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;

/**
 * 岗位管理
 */
class JobController extends Crud
{

    /**
     * @var Job
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new Job;
    }

    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('job/index');
    }

    /**
     * 查询
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function select(Request $request): Response
    {
        $compony_name = $request->get('compony_name');
        $deleted_status = $request->get('deleted_status');
        [$where, $format, $limit, $field, $order] = $this->selectInput($request);
        $query = $this->doSelect($where, $field, $order)
            ->with(['user'=>function ($query) {
                $query->withTrashed();
            }])
            ->when(!empty($compony_name), function ($query) use ($compony_name) {
                $query->whereHas('user', function ($query) use ($compony_name) {
                    $query->where('compony_name', $compony_name);
                });
            })
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
            return parent::insert($request);
        }
        return view('job/insert');
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
        return view('job/update');
    }

}

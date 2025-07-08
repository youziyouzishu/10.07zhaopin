<?php

namespace app\admin\controller;

use app\admin\model\User;
use app\api\common\TencentIM;
use Illuminate\Support\Facades\Date;
use support\Request;
use support\Response;
use app\admin\model\Report;
use plugin\admin\app\controller\Crud;
use support\exception\BusinessException;
use Webman\RedisQueue\Client;

/**
 * 举报管理 
 */
class ReportController extends Crud
{
    
    /**
     * @var Report
     */
    protected $model = null;

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        $this->model = new Report;
    }


    /**
     * 查询
     * @param Request $request
     * @return Response
     * @throws BusinessException
     */
    public function select(Request $request): Response
    {
        [$where, $format, $limit, $field, $order] = $this->selectInput($request);
        $query = $this->doSelect($where, $field, $order)->with(['user','toUser']);
        return $this->doFormat($query, $format, $limit);
    }


    /**
     * 浏览
     * @return Response
     */
    public function index(): Response
    {
        return view('report/index');
    }

    /**
     * 聊天记录
     * @return Response
     */
    public function chatLog(): Response
    {
        return view('report/chat');
    }

    /**
     * 获取聊天记录
     * @param Request $request
     * @return Response
     */
    function getChatLog(Request $request)
    {
        $count = $request->input('count',300);
        $user_id = $request->input('user_id');
        $to_user_id = $request->input('to_user_id');
        $time = $request->input('time');
        if (empty($count)){
            $count = 300;
        }
        if (empty($time[0]) || empty($time[1])){
            $time[0] = Date::now()->subYear()->timestamp;
            $time[1] = Date::now()->timestamp;
        }else{
            $time[0] = strtotime($time[0]);
            $time[1] = strtotime($time[1]);
        }
        $user = User::find($user_id);
        $to_user = User::find($to_user_id);
        $result = TencentIM::getInstance()->adminGetroammsg($user_id, $to_user_id, $count,$time[0],$time[1]);
        $result = json_decode($result);
        $result = $result->MsgList;
        $data = [];
        foreach ($result as $item){
            if ($item->MsgBody[0]->MsgType == 'TIMTextElem'|| $item->MsgBody[0]->MsgType == 'TIMImageElem'){
                if ($item->MsgBody[0]->MsgType == 'TIMTextElem'){
                    $content = $item->MsgBody[0]->MsgContent->Text;
                }
                if ($item->MsgBody[0]->MsgType == 'TIMImageElem'){
                    $content = $item->MsgBody[0]->MsgContent->ImageInfoArray[0]->URL;
                }
                $data[] = [
                    'user_nickname'=>$item->From_Account == $user_id ? $user->nickname : $to_user->nickname,
                    'content'=>$content,
                    'time'=>date('Y-m-d H:i:s', $item->MsgTimeStamp)
                ];
            }
        }
        return json(['code' => 0, 'msg' => 'ok', 'count' => count($data), 'data' => $data]);
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
        return view('report/insert');
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
            $result = $request->post('result');
            $status = $request->post('status');
            $id = $request->post('id');
            $row = $this->model->find($id);
            if ($status == 1 && empty($result)){
                return $this->fail('请填写处理结果');
            }
            if ($row->status == 0 && $status == 1){
                //已处理
                Client::send('job', [
                    'event' => 'report_reply',
                    'email' => $row->user->email,
                    'template' => 'report_reply',
                    'name'=>$row->user->name,
                    'last_name' => $row->user->last_name,
                    'result'=>$result
                ]);
            }

            return parent::update($request);
        }
        return view('report/update');
    }

}

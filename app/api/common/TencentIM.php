<?php

namespace app\api\common;

use GuzzleHttp\Client;
use Tencent\TLSSigAPIv2;

class TencentIM
{
    private string $usersig;
    private string $url = 'https://console.tim.qq.com';
    /**
     * @var mixed
     */
    private $app_id;
    /**
     * @var mixed
     */
    private $identifier;

    private $client;

    private static $instance = null;

    // 禁止被实例化
    private function __construct()
    {
        $identifier = 'administrator';
        $appid = '1600067517';
        $key = '8f00cb63054ab6d5516bd15bc7c770db18db105c3fe7bbbe4e78cd3fbfb129e7';
        $this->usersig = (new TLSSigAPIV2($appid, $key))->genUserSig($identifier);
        $this->app_id = $appid;
        $this->identifier = $identifier;
        $this->client = new Client();
    }

    // 禁止clone
    private function __clone()
    {

    }

    public static function getInstance(): object
    {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * 发送消息  uid不填 以管理员身份发送消息
     * @return bool|string
     */
    function sendMsg($uid = '', $toUid = '', $MsgBody = [], $CloudCustomData = '', $SendMsgControl = [])
    {
        $url = $this->url . '/v4/openim/sendmsg?sdkappid=' . $this->app_id . '&identifier=' . $this->identifier . '&usersig=' . $this->usersig . '&random=' . mt_rand(0, 4294967295) . '&contenttype=json';
        $data = [
            'SyncOtherMachine' => 1,
            'To_Account' => (string)$toUid,
            'MsgRandom' => mt_rand(0, 4294967295),
            'MsgBody' => $MsgBody,

        ];
        if (!empty($uid)) {
            $data['From_Account'] = (string)$uid;
        }
        if (!empty($SendMsgControl)) {
            $data['SendMsgControl'] = $SendMsgControl;
        }
        if (!empty($CloudCustomData)) {
            $data['CloudCustomData'] = $CloudCustomData;
        }
        return Http::post($url, json_encode($data));
    }

    /**
     * 导入账号
     * @param $uid
     * @return mixed|string
     */
    function accountImport($uid, $Nick, $FaceUrl)
    {
        $url = $this->url . '/v4/im_open_login_svc/account_import?sdkappid=' . $this->app_id . '&identifier=' . $this->identifier . '&usersig=' . $this->usersig . '&random=' . mt_rand(0, 4294967295) . '&contenttype=json';
        $data = [
            'UserID' => (string)$uid,
            'Nick' => $Nick,
            'FaceUrl' => $FaceUrl
        ];
        return Http::post($url, json_encode($data));
    }


    /**
     * 设置资料
     * @param $uid
     * @param $ProfileItem
     * @return mixed|string
     */
    function portraitSet($uid, $ProfileItem)
    {
        $url = $this->url . '/v4/profile/portrait_set?sdkappid=' . $this->app_id . '&identifier=' . $this->identifier . '&usersig=' . $this->usersig . '&random=' . mt_rand(0, 4294967295) . '&contenttype=json';
        $data = [
            'From_Account' => (string)$uid,
            'ProfileItem' => $ProfileItem,
        ];
        return Http::post($url, json_encode($data));
    }

    #删除单聊历史记录
    function deleteC2cMsgRamble($Operator_Account, $Peer_Account, $MsgKeyList)
    {
        $url = $this->url . '/v4/openim/delete_c2c_msg_ramble?sdkappid=' . $this->app_id . '&identifier=' . $this->identifier . '&usersig=' . $this->usersig . '&random=' . mt_rand(0, 4294967295) . '&contenttype=json';

    }

    #删除会话
    function delete(string $From_Account, int $Type, string $To_Account = '', string $ToGroupid = '', int $ClearRamble = 0)
    {
        $url = $this->url . '/v4/recentcontact/delete?sdkappid=' . $this->app_id . '&identifier=' . $this->identifier . '&usersig=' . $this->usersig . '&random=' . mt_rand(0, 4294967295) . '&contenttype=json';
        $data = [
            'From_Account' => $From_Account,
            'Type' => $Type,
        ];
        if (!empty($To_Account)) {
            $data['To_Account'] = $To_Account;
        }
        if (!empty($ToGroupid)) {
            $data['ToGroupid'] = $ToGroupid;
        }
        if (!empty($ClearRamble)) {
            $data['ClearRamble'] = $ClearRamble;
        }
        $response = $this->client->post($url, [
//            'headers' => [
//                'Content-Type' => 'application/json'
//            ],
            'json' => $data
        ]);
        return $response->getBody()->getContents();
    }


    #删除会话
    function adminGetroammsg(string $Operator_Account, string $Peer_Account, int $MaxCnt , int $MinTime, int $MaxTime )
    {
        $url = $this->url . '/v4/openim/admin_getroammsg?sdkappid=' . $this->app_id . '&identifier=' . $this->identifier . '&usersig=' . $this->usersig . '&random=' . mt_rand(0, 4294967295) . '&contenttype=json';
        $data = [
            'Operator_Account' => $Operator_Account,
            'Peer_Account' => $Peer_Account,
            'MaxCnt'=>$MaxCnt,
            'MinTime'=>$MinTime,
            'MaxTime'=>$MaxTime,
        ];

        $response = $this->client->post($url, [
            'json' => $data
        ]);
        return $response->getBody()->getContents();
    }

    /**
     * 获取资料
     * @param $uid
     * @param $TagList
     * @return mixed|string
     */
    function portrait_get($uid = [], $TagList = [])
    {
        $url = $this->url . '/v4/profile/portrait_get?sdkappid=' . $this->app_id . '&identifier=' . $this->identifier . '&usersig=' . $this->usersig . '&random=' . mt_rand(0, 4294967295) . '&contenttype=json';
        $data = [
            'To_Account' => explode(',', implode(',', $uid)),
            'TagList' => $TagList,
        ];
        return Http::post($url, json_encode($data));
    }

    #添加群成员
    function add_group_member($GroupId, $MemberList)
    {
        $url = $this->url . '/v4/group_open_http_svc/add_group_member?sdkappid=' . $this->app_id . '&identifier=' . $this->identifier . '&usersig=' . $this->usersig . '&random=' . mt_rand(0, 4294967295) . '&contenttype=json';
        $data = [
            'GroupId' => $GroupId,
            'MemberList' => $MemberList,
        ];
        return Http::post($url, json_encode($data));
    }

    #删除群成员
    function delete_group_member($GroupId, $MemberToDel_Account)
    {
        $url = $this->url . '/v4/group_open_http_svc/delete_group_member?sdkappid=' . $this->app_id . '&identifier=' . $this->identifier . '&usersig=' . $this->usersig . '&random=' . mt_rand(0, 4294967295) . '&contenttype=json';
        $data = [
            'GroupId' => $GroupId,
            'MemberToDel_Account' => $MemberToDel_Account,
        ];
        return Http::post($url, json_encode($data));
    }

    #删除群组
    function destroy_group($GroupId)
    {
        $url = $this->url . '/v4/group_open_http_svc/destroy_group?sdkappid=' . $this->app_id . '&identifier=' . $this->identifier . '&usersig=' . $this->usersig . '&random=' . mt_rand(0, 4294967295) . '&contenttype=json';
        $data = [
            'GroupId' => $GroupId,
        ];
        return Http::post($url, json_encode($data));
    }

    #转让群主
    function change_group_owner($GroupId, $NewOwner_Account, $From_Account)
    {
        $url = $this->url . '/v4/group_open_http_svc/change_group_owner?sdkappid=' . $this->app_id . '&identifier=' . $this->identifier . '&usersig=' . $this->usersig . '&random=' . mt_rand(0, 4294967295) . '&contenttype=json';
        $data = [
            'GroupId' => $GroupId,
            'NewOwner_Account' => (string)$NewOwner_Account,
            'From_Account' => (string)$From_Account,
        ];
        return Http::post($url, json_encode($data));
    }

    #修改群基础资料
    function modify_group_base_info($GroupId, $Name = '', $Introduction = '', $Notification = '', $FaceUrl = '', $MaxMemberNum = '', $ApplyJoinOption = '', $MuteAllMember = '', $InviteJoinOption = '', $From_Account = '')
    {
        $url = $this->url . '/v4/group_open_http_svc/modify_group_base_info?sdkappid=' . $this->app_id . '&identifier=' . $this->identifier . '&usersig=' . $this->usersig . '&random=' . mt_rand(0, 4294967295) . '&contenttype=json';
        $data = [
            'GroupId' => $GroupId,
        ];
        if (!empty($Name)) {
            $data['Name'] = $Name;
        }
        if (!empty($Introduction)) {
            $data['Introduction'] = $Introduction;
        }
        if (!empty($Notification)) {
            $data['Notification'] = $Notification;
        }
        if (!empty($FaceUrl)) {
            $data['FaceUrl'] = $FaceUrl;
        }
        if (!empty($MaxMemberNum)) {
            $data['MaxMemberNum'] = $MaxMemberNum;
        }
        if (!empty($ApplyJoinOption)) {
            $data['ApplyJoinOption'] = $ApplyJoinOption;
        }
        if (!empty($MuteAllMember)) {
            $data['MuteAllMember'] = $MuteAllMember;
        }
        if (!empty($InviteJoinOption)) {
            $data['InviteJoinOption'] = $InviteJoinOption;
        }
        if (!empty($From_Account)) {
            $data['From_Account'] = (string)$From_Account;
        }
        return Http::post($url, json_encode($data));
    }

    #获取群详细资料
    function get_group_info($GroupIdList)
    {
        $url = $this->url . '/v4/group_open_http_svc/get_group_info?sdkappid=' . $this->app_id . '&identifier=' . $this->identifier . '&usersig=' . $this->usersig . '&random=' . mt_rand(0, 4294967295) . '&contenttype=json';
        $data = [
            'GroupIdList' => $GroupIdList,

        ];
        return Http::post($url, json_encode($data));
    }

    #创建新群
    function create_group($Owner_Account, $Name, $Notification, $InviteJoinOption, $ApplyJoinOption = '', $FaceUrl = '', $MemberList = [], $Tags = '', $Mark = '')
    {
        $url = $this->url . '/v4/group_open_http_svc/create_group?sdkappid=' . $this->app_id . '&identifier=' . $this->identifier . '&usersig=' . $this->usersig . '&random=' . mt_rand(0, 4294967295) . '&contenttype=json';
        $data = [
            'Owner_Account' => (string)$Owner_Account,
            'Type' => 'Public',
            'Name' => $Name,
            'Notification' => $Notification,
            'InviteJoinOption' => $InviteJoinOption,
        ];

        if (!empty($FaceUrl)) {
            $data['FaceUrl'] = $FaceUrl;
        }
        if (!empty($ApplyJoinOption)) {
            $data['ApplyJoinOption'] = $ApplyJoinOption;
        }
        if (!empty($MemberList)) {
            $data['MemberList'] = $MemberList;
        }
        if (!empty($Tags)) {
            $data['AppDefinedData'][] = ['Key' => 'Tags', 'Value' => $Tags];
        }
        if (!empty($Mark)) {
            $data['AppDefinedData'][] = ['Key' => 'Mark', 'Value' => $Mark];
        }

        return Http::post($url, json_encode($data));
    }
}
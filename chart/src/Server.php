<?php
namespace WebIM;

use Swoole;
use Swoole\Filter;

class Server extends Swoole\Protocol\CometServer {
    /**
     * @var Store\File;
     */
    protected $store;
    protected $users;
    /**
     * 上一次发送消息的时间
     * @var array
     */
    protected $lastSentTime = array();

    const MESSAGE_MAX_LEN = 1024; //单条消息不得超过1K
    const WORKER_HISTORY_ID = 0;

    function __construct($config = array()) {
        //检测日志目录是否存在
        $log_dir = dirname($config['webim']['log_file']);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0777, true);
        }
//        if (!empty($config['webim']['log_file'])) {
//            $logger = new Swoole\Log\FileLog($config['webim']['log_file']);
//        } else {
//            $logger = new Swoole\Log\EchoLog(array());
//        }

        $logger = new Swoole\Log\EchoLog(array());

        $this->setLogger($logger);   //Logger

        /**
         * 使用文件或redis存储聊天信息
         */
        $this->setStore(new \WebIM\Store\File($config['webim']['data_dir']));
        $this->origin = $config['server']['origin'];
        parent::__construct($config);
    }

    function setStore($store) {
        $this->store = $store;
    }

    /**
     * 下线时，通知所有人
     */
    function onExit($fd) {
        $userInfo = $this->store->getUserFd($fd);
        if ($userInfo) {
            $resMsg = array(
                'cmd' => 'offline',
                'uId' => $userInfo['uId'],
                'from' => 0,
                'channal' => 0,
                'data' => $userInfo['name'] . "下线了",
            );
            $this->store->logout($userInfo['uId']);
            unset($this->uasers[$userInfo['uId']]);
            //将下线消息发送给所有人
            $this->broadcastJson($fd, $resMsg);
        }
        $this->log("onOffline: " . $fd);
    }

    function onTask($serv, $task_id, $from_id, $data) {
        $req = unserialize($data);
        if ($req) {
            switch ($req['cmd']) {
                case 'getHistory':
                    $history = array('cmd' => 'getHistory', 'history' => $this->store->getHistory());
                    if ($this->isCometClient($req['fd'])) {
                        return $req['fd'] . json_encode($history);
                    } else {
                        $this->sendJson(intval($req['fd']), $history);
                    }
                    break;
                case 'addHistory':
                    if (empty($req['msg'])) {
                        $req['msg'] = '';
                    }
                    $this->store->addHistory($req['msg']['from'], $req['msg']);
                    break;
                default:
                    break;
            }
        }
    }

    function onFinish($serv, $task_id, $data) {
        $this->log("F_" . $data);
        $this->send(substr($data, 0, 32), substr($data, 32));
    }

    /**
     * 获取在线列表
     */
    function cmd_getOnline($fd, $msg) {
        $resMsg = array(
            'cmd' => 'getOnline',
        );
        $users = $this->store->getOnlineUsers();
        $info = $this->store->getUsers(array_slice($users, 0, 100));
        $resMsg['users'] = $users;
        $resMsg['list'] = $info;
        $this->sendJson($fd, $resMsg);
    }

    /**
     * 获取历史聊天记录
     */
    function cmd_getHistory($fd, $msg) {
        $task = array();
        $task['fd'] = $fd;
        $task['uId'] = $msg['uId'];
        $task['cmd'] = 'getHistory';
        $task['offset'] = '0,100';
        //在task worker中会直接发送给客户端
        $this->getSwooleServer()->task(serialize($task), self::WORKER_HISTORY_ID);
    }

    /**
     * 登录
     * @param $fd
     * @param $msg
     */
    function cmd_login($fd, $msg) {
        $info = array();
        $info['name'] = Filter::escape(strip_tags($msg['name']));
        $info['avatar'] = Filter::escape($msg['avatar']);
        $info['uId'] = Filter::escape($msg['uId']);

        //回复给登录用户
        $resMsg = array(
            'cmd' => 'login',
            'fd' => $fd,
            'name' => $info['name'],
            'avatar' => $info['avatar'],
            'uId' => $info['uId'],
        );

        //把会话存起来
        $this->users[$info['uId']] = $resMsg;

        $this->store->login($info['uId'], $resMsg);
        $this->sendJson($fd, $resMsg);

        //广播给其它在线用户
        $resMsg['cmd'] = 'newUser';
        //将上线消息发送给所有人
        $this->broadcastJson($fd, $resMsg);

        //用户登录消息
        $loginMsg = array(
            'cmd' => 'fromMsg',
            'from' => 0,
            'channal' => 0,
            'data' => $info['name'] . "上线了",
        );
        $this->broadcastJson($fd, $loginMsg);
    }

    /**
     * 发送信息请求
     */
    function cmd_message($fd, $msg) {
        $resMsg = $msg;
        $resMsg['cmd'] = 'fromMsg';

        if (strlen($msg['data']) > self::MESSAGE_MAX_LEN) {
            $this->sendErrorMessage($fd, 102, 'message max length is ' . self::MESSAGE_MAX_LEN);
            return;
        }

        $now = time();

        //上一次发送的时间超过了允许的值，每N秒可以发送一次
        if ($this->lastSentTime[$fd] > $now - $this->config['webim']['send_interval_limit']) {
            $this->sendErrorMessage($fd, 104, 'over frequency limit');
            return;
        }
        //记录本次消息发送的时间
        $this->lastSentTime[$fd] = $now;

        //表示群发
        if ($msg['channal'] == 0) {
            $this->broadcastJson($fd, $resMsg);

        } else if ($msg['channal'] == 1) {
            //表示私聊
            $toUser = $this->store->getUser($msg['to']);
            $this->sendJson($toUser['fd'], $resMsg);
        }

        $this->getSwooleServer()->task(serialize(array(
            'cmd' => 'addHistory',
            'msg' => $msg,
            'fd' => $fd,
        )), self::WORKER_HISTORY_ID);
    }

    /**
     * 接收到消息时
     * @see WSProtocol::onMessage()
     */
    function onMessage($fd, $ws) {
        $msg = json_decode($ws['message'], true);
        if (empty($msg['cmd'])) {
            $this->sendErrorMessage($fd, 101, "invalid command");
            return;
        }
        $func = 'cmd_' . $msg['cmd'];
        if (method_exists($this, $func)) {
            $this->$func($fd, $msg);
        } else {
            $this->sendErrorMessage($fd, 102, "command $func no support.");
            return;
        }
    }

    /**
     * 发送错误信息
     * @param $fd
     * @param $code
     * @param $msg
     */
    function sendErrorMessage($fd, $code, $msg) {
        $this->sendJson($fd, array('cmd' => 'error', 'code' => $code, 'msg' => $msg));
    }

    /**
     * 发送JSON数据
     * @param $fd
     * @param $array
     */
    function sendJson($fd, $array) {
        $msg = json_encode($array);
        if ($this->send($fd, $msg) === false) {
            $this->close($fd);
        }
    }

    /**
     * 广播JSON数据
     * @param $fd
     * @param $array
     */
    function broadcastJson($currentFd, $array) {
        $msg = json_encode($array);
        $this->broadcast($currentFd, $msg);
    }

    function broadcast($currentFd, $msg) {
        foreach ($this->users as $uId => $user) {
            if ($user['fd'] != $currentFd) {
                $this->send($user['fd'], $msg);
            }
        }
    }
}


<?php
namespace Swoole\Client;
use Swoole\Exception\InvalidParam;
use Swoole\Protocol\RPCServer;
use Swoole\Tool;

class RPC
{
    /**
     * Server的实例列表
     * @var array
     */
    protected $servers = array();

    protected $requestIndex = 0;

    protected $env = array();

    protected $waitList = array();
    protected $timeout = 0.5;
    protected $packet_maxlen = 2097152;   //最大不超过2M的数据包

    /**
     * 启用长连接
     * @var bool
     */
    protected $keepConnection = false;

    protected $haveSwoole = false;
    protected $haveSockets = false;

    const OK = 0;

    /**
     * 版本号
     */
    const VERSION = 1004;

    protected static $_instances = array();

    protected $encode_gzip = false;
    protected $encode_json = false;

    protected $user;
    protected $password;

    function __construct($id = null)
    {
        $key = empty($id) ? 'default' : $id;
        self::$_instances[$key] = $this;
        $this->haveSwoole = extension_loaded('swoole');
        $this->haveSockets = extension_loaded('sockets');
    }

    /**
     * 设置编码类型
     * @param $json
     * @param $gzip
     */
    function setEncodeType($json, $gzip)
    {
        if ($json)
        {
            $this->encode_json = true;
        }
        if ($gzip)
        {
            $this->encode_gzip = true;
        }
    }

    /**
     * 获取SOA服务实例
     * @param $id
     * @return RPC
     */
    static function getInstance($id = null)
    {
        $key = empty($id) ? 'default' : $id;
        if (empty(self::$_instances[$key]))
        {
            $object = new static($id);
        }
        else
        {
            $object = self::$_instances[$key];
        }
        return $object;
    }

    protected function beforeRequest($retObj)
    {

    }

    protected function afterRequest($retObj)
    {

    }

    /**
     * 生成请求串号
     * @return int
     */
    static function getRequestId()
    {
        $us = strstr(microtime(), ' ', true);
        return intval(strval($us * 1000 * 1000) . rand(100, 999));
    }

    /**
     * 连接到服务器
     * @param SOA_Result $retObj
     * @return bool
     * @throws \Exception
     */
    protected function connectToServer(SOA_Result $retObj)
    {
        $ret = false;
        //循环连接
        while (count($this->servers) > 0)
        {
            $svr = $this->getServer();
            //基于Swoole扩展
            if ($this->haveSwoole)
            {
                $key = $svr['host'].':'.$svr['port'].'-'.$retObj->index;
                $socket = new \swoole_client(SWOOLE_SOCK_TCP | SWOOLE_KEEP, SWOOLE_SOCK_SYNC, $key);
                $socket->set(array(
                    'open_length_check' => true,
                    'package_max_length' => $this->packet_maxlen,
                    'package_length_type' => 'N',
                    'package_body_offset' => RPCServer::HEADER_SIZE,
                    'package_length_offset' => 0,
                ));
                /**
                 * 尝试重连一次
                 */
                for ($i = 0; $i < 2; $i++)
                {
                    $ret = $socket->connect($svr['host'], $svr['port'], $this->timeout);
                    if ($ret === false and ($socket->errCode == 114 or $socket->errCode == 115))
                    {
                        //强制关闭，重连
                        $socket->close(true);
                        continue;
                    }
                    else
                    {
                        break;
                    }
                }
                $socketFd = $socket->sock;
            }
            //基于sockets扩展
            elseif ($this->haveSockets)
            {
                $socket = new TCP;
                $socket->try_reconnect = false;
                $ret = $socket->connect($svr['host'], $svr['port'], $this->timeout);
                $socketFd = intval($socket->getSocket());
            }
            //基于stream
            else
            {
                $socket = new Stream();
                $ret = $socket->connect($svr['host'], $svr['port'], $this->timeout);
                $socketFd = intval($socket->getSocket());
            }

            //连接被拒绝，证明服务器已经挂了
            //TODO 如果连接失败，需要上报机器存活状态
            if ($ret === false and $socket->errCode == 111)
            {
                $this->onConnectServerFailed($svr);
                unset($socket);
            }
            else
            {
                $retObj->socket = $socket;
                $retObj->server_host = $svr['host'];
                $retObj->server_port = $svr['port'];
                //使用SOCKET的编号作为ID
                $retObj->id = $socketFd;
                break;
            }
        }
        return $ret;
    }

    /**
     * 发送请求
     * @param $send
     * @param SOA_result $retObj
     * @return bool
     */
    protected function request($send, $retObj)
    {
        $retObj->send = $send;
        $this->beforeRequest($retObj);

        $retObj->index = $this->requestIndex ++;
        connect_to_server:
        if ($this->connectToServer($retObj) === false)
        {
            $retObj->code = SOA_Result::ERR_CONNECT;
            return false;
        }
        //请求串号
        $retObj->requestId = self::getRequestId();
        //打包格式
        $encodeType = $this->encode_json ? RPCServer::DECODE_JSON : RPCServer::DECODE_PHP;
        if ($this->encode_gzip)
        {
            $encodeType |= RPCServer::DECODE_GZIP;
        }
        //发送失败了
        if ($retObj->socket->send(RPCServer::encode($retObj->send, $encodeType, 0, $retObj->requestId)) === false)
        {
            //连接被重置了，重现连接到服务器
            if ($this->haveSwoole and $retObj->socket->errCode == 104)
            {
                goto connect_to_server;
            }
            $retObj->code = SOA_Result::ERR_SEND;
            unset($retObj->socket);
            return false;
        }
        $retObj->code = SOA_Result::ERR_RECV;
        //加入wait_list
        $this->waitList[$retObj->id] = $retObj;
        return true;
    }

    /**
     * 设置环境变量
     * @return array
     */
    public function getEnv()
    {
        return $this->env;
    }

    /**
     * 获取环境变量
     * @param array $env
     */
    public function setEnv($env)
    {
        $this->env = $env;
    }

    /**
     * 设置一项环境变量
     * @param $k
     * @param $v
     */
    public function putEnv($k, $v)
    {
        $this->env[$k] = $v;
    }


    /**
     * 设置超时时间，包括连接超时和接收超时
     * @param $timeout
     */
    function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * 设置用户名和密码
     * @param $user
     * @param $password
     */
    function auth($user, $password)
    {
        $this->putEnv('user', $user);
        $this->putEnv('password', $password);
    }

    /**
     * 完成请求
     * @param $retData
     * @param $retObj
     */
    protected function finish($retData, $retObj)
    {
        //解包失败了
        if ($retData === false)
        {
            $retObj->code = SOA_Result::ERR_UNPACK;
        }
        //调用成功
        elseif ($retData['errno'] === self::OK)
        {
            $retObj->code = self::OK;
            $retObj->data = $retData['data'];
        }
        //服务器返回失败
        else
        {
            $retObj->code = $retData['errno'];
            $retObj->data = null;
        }
        unset($this->waitList[$retObj->id]);
        //执行after钩子函数
        $this->afterRequest($retObj);
        //执行回调函数
        if ($retObj->callback)
        {
            call_user_func($retObj->callback, $retObj);
        }
    }

    /**
     * 添加服务器
     * @param array $servers
     */
    function addServers(array $servers)
    {
        if (isset($servers['host']))
        {
            self::formatServerConfig($servers);
            $this->servers[] = $servers;
        }
        else
        {
            //兼容老的写法
            foreach ($servers as $svr)
            {
                // 127.0.0.1:8001 的写法
                if (is_string($svr))
                {
                    list($config['host'], $config['port']) = explode(':', $svr);
                }
                else
                {
                    $config = $svr;
                }
                self::formatServerConfig($config);
                $this->servers[] = $config;
            }
        }
    }

    /**
     * @param $config
     * @throws InvalidParam
     */
    static protected function formatServerConfig(&$config)
    {
        if (empty($config['host']))
        {
            throw new InvalidParam("require 'host' option.");
        }
        if (empty($config['port']))
        {
            throw new InvalidParam("require 'port' option.");
        }
        if (empty($config['status']))
        {
            $config['status'] = 'online';
        }
        if (empty($config['weight']))
        {
            $config['weight'] = 100;
        }
    }

    /**
     * 设置服务器
     * @param array $servers
     */
    function setServers(array $servers)
    {
        foreach($servers as &$svr)
        {
            self::formatServerConfig($svr);
        }
        $this->servers = $servers;
    }

    /**
     * 从配置中取出一个服务器配置
     * @return array
     * @throws \Exception
     */
    function getServer()
    {
        if (empty($this->servers))
        {
            throw new \Exception("servers config empty.");
        }
        return Tool::getServer($this->servers);
    }

    /**
     * 连接服务器失败了
     * @param $svr
     * @return bool
     */
    function onConnectServerFailed($svr)
    {
        foreach($this->servers as $k => $v)
        {
            if ($v['host'] == $svr['host'] and $v['port'] == $svr['port'])
            {
                //从Server列表中移除
                unset($this->servers[$k]);
                return true;
            }
        }
        return false;
    }

    /**
     * RPC调用
     *
     * @param $function
     * @param $params
     * @param $callback
     * @return SOA_Result
     */
    function task($function, $params = array(), $callback = null)
    {
        $retObj = new SOA_Result($this);
        $send = array('call' => $function, 'params' => $params);
        if (count($this->env) > 0)
        {
            //调用端环境变量
            $send['env'] = $this->env;
        }
        $this->request($send, $retObj);
        $retObj->callback = $callback;
        return $retObj;
    }

    /**
     * 侦测服务器是否存活
     */
    function ping()
    {
        return $this->task('PING')->getResult() === 'PONG';
    }

    /**
     * 使用sockets扩展
     * @param $timeout
     * @return int
     */
    protected function recvWaitWithSockets($timeout)
    {
        $st = microtime(true);
        $t_sec = (int)$timeout;
        $t_usec = (int)(($timeout - $t_sec) * 1000 * 1000);
        $buffer = $header = array();
        $success_num = 0;

        while (true)
        {
            $write = $error = $read = array();
            if(empty($this->waitList))
            {
                break;
            }
            foreach($this->waitList as $obj)
            {
                if($obj->socket !== null)
                {
                    $read[] = $obj->socket->getSocket();
                }
            }
            if (empty($read))
            {
                break;
            }
            $n = socket_select($read, $write, $error, $t_sec, $t_usec);
            if ($n > 0)
            {
                //可读
                foreach($read as $sock)
                {
                    $id = (int)$sock;

                    /**
                     * @var $retObj SOA_Result
                     */
                    $retObj = $this->waitList[$id];
                    $data = $retObj->socket->recv();
                    //socket被关闭了
                    if (empty($data))
                    {
                        $retObj->code = SOA_Result::ERR_CLOSED;
                        unset($this->waitList[$id], $retObj->socket);
                        continue;
                    }
                    //一个新的请求，缓存区中没有数据
                    if (!isset($buffer[$id]))
                    {
                        //这里仅使用了length和type，uid,serid未使用
                        $header[$id] = unpack(RPCServer::HEADER_STRUCT, substr($data, 0, RPCServer::HEADER_SIZE));
                        //错误的包头
                        if ($header[$id] === false or $header[$id]['length'] <= 0)
                        {
                            $retObj->code = SOA_Result::ERR_HEADER;
                            unset($this->waitList[$id]);
                            continue;
                        }
                        //错误的长度值
                        elseif ($header[$id]['length'] > $this->packet_maxlen)
                        {
                            $retObj->code = SOA_Result::ERR_TOOBIG;
                            unset($this->waitList[$id]);
                            continue;
                        }
                        $buffer[$id] = substr($data, RPCServer::HEADER_SIZE);
                    }
                    else
                    {
                        $buffer[$id] .= $data;
                    }
                    //达到规定的长度
                    if (strlen($buffer[$id]) == $header[$id]['length'])
                    {
                        //请求串号与响应串号不一致
                        if ($retObj->requestId != $header[$id]['serid'])
                        {
                            trigger_error(__CLASS__." requestId[{$retObj->requestId}]!=responseId[{$header['serid']}]", E_USER_WARNING);
                            continue;
                        }
                        //成功处理
                        $this->finish(RPCServer::decode($buffer[$id], $header[$id]['type']), $retObj);
                        $success_num++;
                    }
                    //继续等待数据
                }
            }
            //发生超时
            if ((microtime(true) - $st) > $timeout)
            {
                foreach($this->waitList as $obj)
                {
                    //TODO 如果请求超时了，需要上报服务器负载
                    $obj->code = ($obj->socket->connected) ? SOA_Result::ERR_TIMEOUT : SOA_Result::ERR_CONNECT;
                    //执行after钩子函数
                    $this->afterRequest($obj);
                }
                //清空当前列表
                $this->waitList = array();
                return $success_num;
            }
        }
        //未发生任何超时
        $this->waitList = array();
        $this->requestIndex = 0;
        return $success_num;
    }

    /**
     * 使用Swoole扩展
     * @param $timeout
     * @return int
     */
    protected function recvWaitWithSwoole($timeout)
    {
        $st = microtime(true);
        $success_num = 0;

        while (true)
        {
            $write = $error = $read = array();
            if (empty($this->waitList))
            {
                break;
            }
            foreach ($this->waitList as $obj)
            {
                if ($obj->socket !== null)
                {
                    $read[] = $obj->socket;
                }
            }
            if (empty($read))
            {
                break;
            }

            $n = swoole_client_select($read, $write, $error, $timeout);
            if ($n > 0)
            {
                //可读
                foreach($read as $sock)
                {
                    $id = $sock->sock;

                    /**
                     * @var $retObj SOA_Result
                     */
                    $retObj = $this->waitList[$id];
                    $data = $retObj->socket->recv();
                    //socket被关闭了
                    if (empty($data))
                    {
                        $retObj->code = SOA_Result::ERR_CLOSED;
                        unset($this->waitList[$id], $retObj->socket);
                        continue;
                    }
                    else
                    {
                        $header = unpack(RPCServer::HEADER_STRUCT, substr($data, 0, RPCServer::HEADER_SIZE));
                        //串号不一致，丢弃结果
                        if ($header['serid'] != $retObj->requestId)
                        {
                            trigger_error(__CLASS__." requestId[{$retObj->requestId}]!=responseId[{$header['serid']}]", E_USER_WARNING);
                            continue;
                        }
                        //成功处理
                        $this->finish(RPCServer::decode(substr($data, RPCServer::HEADER_SIZE), $header['type']), $retObj);
                        $success_num++;
                    }
                }
            }
            //发生超时
            if ((microtime(true) - $st) > $timeout)
            {
                foreach ($this->waitList as $obj)
                {
                    $obj->code = ($obj->socket->isConnected()) ? SOA_Result::ERR_TIMEOUT : SOA_Result::ERR_CONNECT;
                    //执行after钩子函数
                    $this->afterRequest($obj);
                }
                //清空当前列表
                $this->waitList = array();
                return $success_num;
            }
        }

        //未发生任何超时
        $this->waitList = array();
        $this->requestIndex = 0;
        return $success_num;
    }

    /**
     * 并发请求
     * @param float $timeout
     * @return int
     */
    function wait($timeout = 0.5)
    {
        if ($this->haveSwoole)
        {
            return $this->recvWaitWithSwoole($timeout);
        }
        elseif ($this->haveSockets)
        {
            return $this->recvWaitWithSockets($timeout);
        }

        $st = microtime(true);
        $t_sec = (int)$timeout;
        $t_usec = (int)(($timeout - $t_sec) * 1000 * 1000);
        $buffer = $header = array();
        $success_num = 0;

        while (true)
        {
            $write = $error = $read = array();
            if(empty($this->waitList))
            {
                break;
            }
            foreach($this->waitList as $obj)
            {
                if($obj->socket !== null)
                {
                    $read[] = $obj->socket->getSocket();
                }
            }
            if (empty($read))
            {
                break;
            }

            $n = stream_select($read, $write, $error, $t_sec, $t_usec);
            if ($n > 0)
            {
                //可读
                foreach($read as $sock)
                {
                    $id = (int)$sock;

                    /**
                     * @var $retObj SOA_Result
                     */
                    $retObj = $this->waitList[$id];
                    $data = $retObj->socket->recv();
                    //socket被关闭了
                    if (empty($data))
                    {
                        $retObj->code = SOA_Result::ERR_CLOSED;
                        unset($this->waitList[$id], $retObj->socket);
                        continue;
                    }
                    //一个新的请求，缓存区中没有数据
                    if (!isset($buffer[$id]))
                    {
                        //这里仅使用了length和type，uid,serid未使用
                        $header[$id] = unpack(RPCServer::HEADER_STRUCT, substr($data, 0, RPCServer::HEADER_SIZE));
                        //错误的包头
                        if ($header[$id] === false or $header[$id]['length'] <= 0)
                        {
                            $retObj->code = SOA_Result::ERR_HEADER;
                            unset($this->waitList[$id]);
                            continue;
                        }
                        //错误的长度值
                        elseif ($header[$id]['length'] > $this->packet_maxlen)
                        {
                            $retObj->code = SOA_Result::ERR_TOOBIG;
                            unset($this->waitList[$id]);
                            continue;
                        }
                        $buffer[$id] = substr($data, RPCServer::HEADER_SIZE);
                    }
                    else
                    {
                        $buffer[$id] .= $data;
                    }
                    //达到规定的长度
                    if (strlen($buffer[$id]) == $header[$id]['length'])
                    {
                        //请求串号与响应串号不一致
                        if ($retObj->requestId != $header[$id]['serid'])
                        {
                            trigger_error(__CLASS__." requestId[{$retObj->requestId}]!=responseId[{$header['serid']}]", E_USER_WARNING);
                            continue;
                        }
                        //成功处理
                        $this->finish(RPCServer::decode($buffer[$id], $header[$id]['type']), $retObj);
                        $success_num++;
                    }
                    //继续等待数据
                }
            }
            //发生超时
            if ((microtime(true) - $st) > $timeout)
            {
                foreach($this->waitList as $obj)
                {
                    //TODO 如果请求超时了，需要上报服务器负载
                    $obj->code = ($obj->socket->connected) ? SOA_Result::ERR_TIMEOUT : SOA_Result::ERR_CONNECT;
                    //执行after钩子函数
                    $this->afterRequest($obj);
                }
                //清空当前列表
                $this->waitList = array();
                return $success_num;
            }
        }
        //未发生任何超时
        $this->waitList = array();
        $this->requestIndex = 0;
        return $success_num;
    }

}

/**
 * SOA服务请求结果对象
 * Class SOA_Result
 * @package Swoole\Client
 */
class SOA_Result
{
    public $id;
    public $code = self::ERR_NO_READY;
    public $msg;
    public $data = null;
    public $send;  //要发送的数据
    public $type;
    public $index;

    /**
     * 请求串号
     */
    public $requestId;

    /**
     * 回调函数
     * @var mixed
     */
    public $callback;

    /**
     * @var \Swoole\Client\TCP
     */
    public $socket = null;

    /**
     * SOA服务器的IP地址
     * @var string
     */
    public $server_host;

    /**
     * SOA服务器的端口
     * @var int
     */
    public $server_port;

    /**
     * @var RPC
     */
    protected $soa_client;

    const ERR_NO_READY   = 8001; //未就绪
    const ERR_CONNECT    = 8002; //连接服务器失败
    const ERR_TIMEOUT    = 8003; //服务器端超时
    const ERR_SEND       = 8004; //发送失败

    const ERR_SERVER     = 8005; //server返回了错误码
    const ERR_UNPACK     = 8006; //解包失败了

    const ERR_HEADER     = 8007; //错误的协议头
    const ERR_TOOBIG     = 8008; //超过最大允许的长度
    const ERR_CLOSED     = 8009; //连接被关闭

    const ERR_RECV       = 8010; //等待接收数据

    function __construct($soa_client)
    {
        $this->soa_client = $soa_client;
    }

    function getResult($timeout = 0.5)
    {
        if ($this->code == self::ERR_RECV)
        {
            $this->soa_client->wait($timeout);
        }
        return $this->data;
    }
}

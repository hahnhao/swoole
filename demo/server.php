<?php
/**
 * Created by PhpStorm.
 * User: xiaoxi
 * Date: 16-12-22
 * Time: 11:13
 */

class Server {
    private $serv;

    public function __construct() {
        $this->serv = new swoole_server("0.0.0.0", 9501);
        $this->serv->set(array(
            'worker_num' => 4,
            'task_worker_num' => 4,
            'daemonize' => false
        ));

        $this->serv->on('Start', array($this, 'onStart'));
        $this->serv->on('Connect', array($this, 'onConnect'));
        $this->serv->on('Receive', array($this, 'onReceive'));
        $this->serv->on('Close', array($this, 'onClose'));

        $this->serv->on('Task', array($this, 'onTask'));
        $this->serv->on('Finish', array($this, 'onFinish'));

        $this->serv->start();
    }

    public function onTask(swoole_server $serv, $task_id, $from_id, $data ) {
        echo "Task {$data}\n";
        return "hello";
    }

    public function onFinish(swoole_server $serv, $task_id, $data) {
        echo "Task Finish {$data}\n";
    }

    public function onStart($serv) {
        echo "start \n";
    }

    public function onConnect($serv, $fd, $from_id) {
        $info = $serv->connection_info($fd);
        print_r($info);
        $serv->send($fd, "hello {$fd}!");
    }

    public function onReceive(swoole_server $serv, $fd, $from_id, $data) {
        echo "Get Message From Client {$fd}:{$data}\n";
        echo "{$from_id}\n";
        $serv->send($fd, $data);

        $serv->task("test");
    }

    public function onClose($serv, $fd, $from_id ) {
        echo "Client {$fd} close connection\n";
    }
}

$server = new Server();
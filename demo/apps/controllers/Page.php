<?php
/**
 * Created by PhpStorm.
 * User: xiaoxi
 * Date: 16-12-15
 * Time: 19:27
 */

namespace App\Controller;

use Swoole;
use App;

class Page extends Swoole\Controller {

    protected $userInfo = array();
    function __construct($swoole) {

        parent::__construct($swoole);

        $this->checkLogin();
    }

    function sendMessage() {

        model('Message')->addMessage();
        echo json_encode(array('code' => 0));
    }

    function index() {
//        $array = $this->codb->query("select * from message");
//        $this->codb->wait(1.0);
//        $data = $array->result->fetchall();


        $data = model('Message')->getMessageList();
        $this->tpl->assign('data', $data);
        $this->tpl->assign("userInfo", $this->userInfo);
        $this->tpl->assign("title", "demo");
        $this->tpl->display("page/index.html");
    }

    protected function checkLogin() {
        $this->session->start();
        $uId = $this->session->get('uId');
        $name = $this->session->get('name');

        if (empty($name)) {
            $url = '/user/login/?refer=' . urlencode($_SERVER["REQUEST_URI"]);
            $this->http->redirect($url);
        } else {
            $this->userInfo['name'] = $name;
            $this->userInfo['uId'] = $uId;
        }
    }
}
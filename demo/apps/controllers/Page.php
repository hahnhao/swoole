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

    function index() {

        $result = $this->db->queryAll("SELECT * FROM user WHERE user_id = :uId",  array('uId' => 1));

        $this->tpl->assign("data", $result);
        $this->tpl->assign("userInfo", $this->userInfo);
        $this->tpl->assign("title", "demo");
        $this->tpl->display("page/index.html");
    }

    protected function checkLogin() {
        $this->session->start();
        $uId = $_SESSION['uId'];
        $name = $_SESSION['name'];

        if (empty($name)) {
            $this->http->redirect('/user/login');
        } else {
            $this->userInfo['name'] = $name;
            $this->userInfo['uId'] = $uId;
        }
    }
}
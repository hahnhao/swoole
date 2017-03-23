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
        $url = "/main.html?name={$this->userInfo['user_name']}&avatar={$this->userInfo['user_avatar']}&uId={$this->userInfo['user_id']}";
        //$this->http->redirect($url);
        echo $url . "<br/>";
    }

    protected function checkLogin() {
        $this->session->start();

        $uId = $this->session->get('uId');

        if (empty($uId)) {
            $url = '/user/login/?refer=' . urlencode($_SERVER["REQUEST_URI"]);
            $this->http->redirect($url);
        } else {
            $model = model('User');
            $user = $model->get($uId);
            $this->userInfo = $user->get();
        }
    }
}
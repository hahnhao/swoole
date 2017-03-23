<?php
/**
 * Created by PhpStorm.
 * User: xiaoxi
 * Date: 16-12-16
 * Time: 10:42
 */

namespace App\Controller;


class User extends \Swoole\Controller {

    function login() {

        $this->session->start();
        $name = $this->request->post['username'];
        $password = $this->request->post['password'];

        if ($name) {
            $model = model('User');
            $user = $model->get($name, 'user_name');
            $user = $user->get();

            $pwd = md5($password);
            if ($user && $user['user_password'] == $pwd) {
                $this->session->set("uId", $user['user_id']);
                $this->session->set("name", $name);
                $this->http->redirect('/');
            } else {
                $msg = "密码错误！！！";
                $this->tpl->assign('msg', $msg);
            }
        }

        $this->tpl->display("login.html");
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: xiaoxi
 * Date: 16-12-19
 * Time: 16:07
 */

namespace App\Model;
use Swoole;

class Message extends Swoole\Model {
    public $table = 'message';
    public $primary = 'm_id';

    public function getMessageList() {
        $data = $this->gets(array('where' => 1));
        return $data;
    }

    public function addMessage() {
        $content = $_REQUEST['content'];
        if ($content) {
            $array = array();
            $array['m_content'] = $content;
            $array['m_dateline'] = time();
            $array['user_name'] = $_SESSION['name'];
            $res = $this->put($array);
            return $res;
        }
    }
}
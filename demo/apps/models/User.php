<?php
/**
 * Created by PhpStorm.
 * User: xiaoxi
 * Date: 16-12-19
 * Time: 16:07
 */

namespace App\Model;
use Swoole;

class User extends Swoole\Model {
    public $table = 'user';
    public $primary = 'user_id';
}
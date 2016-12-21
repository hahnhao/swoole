<?php
$db['master'] = array(
    'type'       => Swoole\Database::TYPE_PDO,
    'host'       => "localhost",
    'port'       => 3306,
    'dbms'       => 'mysql',
    'engine'     => 'InnoDB',
    'user'       => "root",
    'passwd'     => "root",
    'name'       => "swoole",
    'charset'    => "utf8",
    'setname'    => true,
    'persistent' => false, //MySQL长连接
    'use_proxy'  => false,  //启动读写分离Proxy
    'slaves'     => array(
        array('host' => 'localhost', 'port' => '3306', 'weight' => 100,),
    ),
);

$db['slave'] = array(
    'type'       => Swoole\Database::TYPE_PDO,
    'host'       => "127.0.0.1",
    'port'       => 3306,
    'dbms'       => 'mysql',
    'engine'     => 'InnoDB',
    'user'       => "root",
    'passwd'     => "root",
    'name'       => "swoole",
    'charset'    => "utf8",
    'setname'    => true,
    'persistent' => false, //MySQL长连接
);

return $db;
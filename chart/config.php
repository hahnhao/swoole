<?php
$config['server'] = array(
    //监听的HOST
    'host'   => '0.0.0.0',
    //监听的端口
    'port'   => '9503',
    //WebSocket的URL地址，供浏览器使用的
    'url'    => 'ws://127.0.0.1:9503',
    //用于Comet跨域，必须设置为html所在的URL
    'origin' => 'http://127.0.0.1:9501',
);

$config['swoole'] = array(
    'log_file'        => __DIR__ . '/log/swoole.log',
    'worker_num'      => 1,
    //不要修改这里
    'max_request'     => 0,
    'task_worker_num' => 1,
    //是否要作为守护进程
    'daemonize'       => 0,
);

$config['webim'] = array(
    //聊天记录存储的目录
    'data_dir' => __DIR__ . '/data/',
    'log_file' => __DIR__ . '/log/webim.log',
    'send_interval_limit' => 2, //只允许1秒发送一次
);

return $config;
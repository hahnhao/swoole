<?php
define('DEBUG', 'on');
define('WEBPATH', __DIR__);

// 直接将swoole/framework放到当前目录下

require WEBPATH . '/framework/libs/lib_config.php';

/**
 * 注册命名空间到自动载入器中
 */
Swoole\Loader::addNameSpace('WebIM', __DIR__.'/src/');

//设置PID文件的存储路径
Swoole\Network\Server::setPidFile(__DIR__ . '/log/webim_server.pid');

/**
 * 显示Usage界面
 * php app_server.php start|stop|reload
 */
Swoole\Network\Server::start(function ()
{
    $config = require __DIR__.'/config.php';
    $webim = new WebIM\Server($config);
    $webim->loadSetting(__DIR__ . "/swoole.ini"); //加载配置文件

    /**
     * webim必须使用swoole扩展
     */
    $server = new Swoole\Network\Server($config['server']['host'], $config['server']['port']);
    $server->setProtocol($webim);
    $server->run($config['swoole']);
});

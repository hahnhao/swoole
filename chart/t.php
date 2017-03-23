<?php

$dir = './dev/shm/swoole_webim';
$files = scandir($dir);
$files = array_slice($files, 2);

foreach ($files as $file) {
    if (strpos($file, 'FD') === false) {
        $online_users[] = $file;
    }
}

echo "<pre>";
print_r($online_users);
<?php

require_once __DIR__ . '/vendor/autoload.php';


use \Swoole\Http\Server;
use \Swoole\Process;
use \Core\server\HttpServer;

if ($argc == 2) {
    $cmd = $argv[1];
    if ($cmd == 'start') {
        $http = new HttpServer();
        $http->run();
    } elseif ($cmd == 'stop') {
        // 获取上一次程序运行的 master ID
        $getPid = intval(file_get_contents("./aliliin.pid"));
        if ($getPid && trim($getPid) != 0) {
            Process::kill($getPid);
        }
    } else {
        echo '无效命令' . PHP_EOL;
    }
}
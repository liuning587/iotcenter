<?php
require_once __DIR__ . '/vendor/workerman/Autoloader.php';
use Workerman\Worker;

// 标记是全局启动
define('GLOBAL_START', 1);

require_once __DIR__ . '/vendor/workerman/Autoloader.php';

// 加载所有start.php，以便启动所有服务
foreach (glob(__DIR__ . '/start_*.php') as $start_file) {
    require_once $start_file;
}
// 运行所有服务
Worker::runAll();
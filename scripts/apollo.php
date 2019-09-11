#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use \Client\ApolloClient;

echo "检查配置中...\n";

$env = \Conf\ApolloConf::ENV;
$db_host = \Conf\ApolloConf::DB_HOST;
$db_port = \Conf\ApolloConf::DB_PORT;
$db_database = \Conf\ApolloConf::DB_DATABASE;
$db_username = \Conf\ApolloConf::DB_USERNAME;
$db_password = \Conf\ApolloConf::DB_PASSWORD;
$configs_dir = \Conf\ApolloConf::$CONFIGS_DIR;
$server = '192.168.33.66:8080';

$all_files = array();
foreach ($configs_dir as $dir) {
    if (!file_exists($dir)) {
        var_dump($dir . '目录不存在');die;
    }
    $path = $dir . '/' . '*.schema.php';
    $files = glob($path);
    foreach ($files as $file) {
        $all_files[] = $file;
    }
}

$request_param = array();
foreach ($all_files as $file) {
    $flag = strrpos($file, '/') + 1;
    $path = substr($file, 0, $flag);
    $need_param = str_replace('.schema.php','',substr($file, $flag));
    if (isset($request_param[$need_param])) {
        if (in_array($path, $request_param[$need_param])) {

        } else {
            $request_param[$need_param][] = $path;
        }
    } else {
        $request_param[$need_param][] = $path;
    }
}

$apollo = new ApolloClient($server);
ini_set('memory_limit', '128M');
$pid = getmypid();
echo "start [$pid]\n";
$restart = false; // 失败自动重启
$callback = function(){};
do {
    //$error = $apollo->start($callback);
    $error = $apollo->startNew($request_param, $callback);
}while($error && $restart);





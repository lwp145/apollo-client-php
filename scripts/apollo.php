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

$need_update_files = array();
foreach ($configs_dir as $dir) {
    if (!file_exists($dir)) {
        var_dump($dir . '目录不存在');die;
    }
    $path = $dir . '/' . '*.schema.php';
    $files = glob($path);
    foreach ($files as $file) {
        $need_update_files[] = $file;
    }
}

$request_params = array();

$others = array();
foreach ($need_update_files as $file) {
    $start = strrpos($file, '/') + 1;
    $tmp = explode('.', substr($file, $start));
    $appId = $tmp[0];
    $cluster = $tmp[1];
    $namespace = $tmp[2];
    $appId_cluster = $appId . '.' . $cluster;
    $other['appId'] = $tmp[0];
    $other['cluster'] = $tmp[1];
    $other['namespaces'] = $namespace;
    $other['notifications'] = array(
        array('namespaceName' => $namespace,
            'notificationId' => -1)
    );
    if (isset($request_params[$appId_cluster]) && in_array($namespace, $request_params[$appId_cluster]['namespaces'])) {

    } else {
        $request_params[$appId_cluster]['namespaces'][] = $namespace;
        $request_params[$appId_cluster]['notifications'][] = array(
            'namespaceName' => $namespace,
            'notificationId' => -1
        );
    }
    $others[] = $other;
}
//print_r('<pre>');
//print_r($others);die;

$apollo = new ApolloClient($server);
ini_set('memory_limit', '128M');
$pid = getmypid();
echo "start [$pid]\n";
$restart = false; // 失败自动重启
$callback = function(){};
do {
    //$error = $apollo->start($callback);
    $error = $apollo->startNew($need_update_files,$request_params, $others, $callback);
}while($error && $restart);





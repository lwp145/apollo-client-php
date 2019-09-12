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

$needToBeLoadedFiles = array();
foreach ($configs_dir as $dir) {
    if (!file_exists($dir)) {
        var_dump($dir . '目录不存在');die;
    }
    $path = $dir . '/' . '*.schema.php';
    $files = glob($path);
    foreach ($files as $file) {
        $needToBeLoadedFiles[] = $file;
    }
}

$listenChangeParams = array();

foreach ($needToBeLoadedFiles as $file) {

    $expectGenerateConfigFile = str_replace('.schema', '', $file);

    if (file_exists($expectGenerateConfigFile)) {
        $content = include $expectGenerateConfigFile;
    } else {
        $content = include $file;
    }

    $appId = $content['appId'];
    $cluster = $content['cluster'];
    $namespaceName = $content['namespaceName'];
    $notificationId = $content['notificationId'];
    $releaseKey = $content['releaseKey'];
    $appId_cluster = $appId . '.' . $cluster;

    if (isset($listenChangeParams[$appId_cluster]) && in_array($namespaceName, $listenChangeParams[$appId_cluster]['namespaceNames'])) {

    } else {
        $listenChangeParams[$appId_cluster]['appId'] = $appId;
        $listenChangeParams[$appId_cluster]['cluster'] = $cluster;
        $listenChangeParams[$appId_cluster]['namespaceNames'][] = $namespaceName;
        $listenChangeParams[$appId_cluster]['notifications'][] = array(
            'namespaceName' => $namespaceName,
            'notificationId' => $notificationId,
            'releaseKey' => $releaseKey
        );
    }
}

$apollo = new ApolloClient($server);

ini_set('memory_limit', '128M');

$pid = getmypid();

echo "start [$pid]\n";

$restart = false; // 失败自动重启

$callback = function(){};

do {
    $apollo->startNew($needToBeLoadedFiles,$listenChangeParams, $callback);
    sleep(10);
}while(true);





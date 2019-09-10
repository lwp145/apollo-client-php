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

$msgs = checkConf($env, $db_host, $db_port, $db_database, $db_username, $db_password, $configs_dir);
foreach ($msgs as $msg) {
    echo $msg;
}


function checkConf($env, $db_host, $db_port, $db_database, $db_username, $db_password, $configs_dir)
{
    $msg = array();
    if (empty($env) || !is_string($env)) {
        $msg[] = "ENV          ................................................. fail\n";
    } else {
        $msg[] = "ENV          ................................................. ok\n";
    }
    if (empty($db_host) || !is_string($db_host)) {
        $msg[] = "DB_HOST      ................................................. fail\n";
    } else {
        $msg[] = "DB_HOST      ................................................. ok\n";
    }
    if (empty($db_port) || !is_string($db_port)) {
        $msg[] = "DB_PORT      ................................................. fail\n";
    } else {
        $msg[] = "DB_PORT      ................................................. ok\n";
    }
    if (empty($db_database) || !is_string($db_database)) {
        $msg[] = "DB_DATABASE  ................................................. fail\n";
    } else {
        $msg[] = "DB_DATABASE  ................................................. ok\n";
    }
    if (empty($db_username) || !is_string($db_username)) {
        $msg[] = "DB_USERNAME  ................................................. fail\n";
    } else {
        $msg[] = "DB_USERNAME  ................................................. ok\n";
    }
    if (empty($db_password) || !is_string($db_password)) {
        $msg[] = "DB_PASSWORD  ................................................. fail\n";
    } else {
        $msg[] = "DB_PASSWORD  ................................................. ok\n";
    }
    if (empty($configs_dir) || !is_array($configs_dir)) {
        $msg[] = "CONFIGS_DIR  ................................................. fail\n";
    } else {
        $msg[] = "CONFIGS_DIR  ................................................. ok\n";
    }
    return $msg;
}
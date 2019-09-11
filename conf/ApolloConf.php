<?php
namespace Conf;

class ApolloConf
{
    /**
     * 当前加载的配置环境,值为apollo 配置管理平台环境列表中的某个值(大写).
     *
     * @var string
     */
    const ENV = 'LOCAL';

    /**
     * 数据库地址.
     *
     * @var string
     */
   const DB_HOST = '192.168.33.66';

    /**
     * 数据库端口.
     *
     * @var string
     */
    const DB_PORT = '3306';

    /**
     * 存储配置的apollo数据库.
     *
     * @var string
     */
    const DB_DATABASE = 'ApolloConfigDB';

    /**
     * 用于登录数据库的账户.
     *
     * @var string
     */
    const DB_USERNAME = 'root';

    /**
     * 数据库密码.
     *
     * @var string
     */
    const DB_PASSWORD = '123456';

    /**
     * 等待加载配置的所有目录.
     *
     * @var array
     */
    public static $CONFIGS_DIR = array(
        '/home/wenpol/www/think-5.1/CustomConfig',
        '/home/wenpol/www/json/CustomConfig',
    );
}

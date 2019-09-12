<?php

namespace Client;

class ApolloClient
{
    protected $configServer; //apollo服务端地址
    protected $appId; //apollo配置项目的appid
    protected $cluster = 'default';
    protected $clientIp = '127.0.0.1'; //绑定IP做灰度发布用
    protected $notifications = [];
    protected $pullTimeout = 10; //获取某个namespace配置的请求超时时间
    protected $intervalTimeout = 80; //每次请求获取apollo配置变更时的超时时间
    public $save_dir; //配置保存目录

    /**
     * ApolloClient constructor.
     * @param string $configServer apollo服务端地址
     * @param string $appId apollo配置项目的appid
     * @param array $namespaces apollo配置项目的namespace
     */
    public function __construct($configServer, $appId = '', array $namespaces = array())
    {
        $this->configServer = $configServer;
//        foreach ($namespaces as $namespace) {
//            $this->notifications[$namespace] = ['namespaceName' => $namespace, 'notificationId' => -1];
//        }
        //$this->save_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    }

    public function setCluster($cluster)
    {
        $this->cluster = $cluster;
    }

    public function setClientIp($ip)
    {
        $this->clientIp = $ip;
    }

    public function setPullTimeout($pullTimeout) {
        $pullTimeout = intval($pullTimeout);
        if ($pullTimeout < 1 || $pullTimeout > 300) {
            return;
        }
        $this->pullTimeout = $pullTimeout;
    }

    public function setIntervalTimeout($intervalTimeout) {
        $intervalTimeout = intval($intervalTimeout);
        if ($intervalTimeout < 1 || $intervalTimeout > 300) {
            return;
        }
        $this->intervalTimeout = $intervalTimeout;
    }

    private function _getReleaseKey($config_file) {
        $releaseKey = '';
        if (file_exists($config_file)) {
            $last_config = require $config_file;
            is_array($last_config) && isset($last_config['releaseKey']) && $releaseKey = $last_config['releaseKey'];
        }
        return $releaseKey;
    }

    //获取单个namespace的配置文件路径
    public function getConfigFile($namespaceName) {
        return $this->save_dir.DIRECTORY_SEPARATOR.'apolloConfig.'.$namespaceName.'.php';
    }

    //获取单个namespace的配置-无缓存的方式
    public function pullConfig($namespaceName) {
        $base_api = rtrim($this->configServer, '/').'/configs/'.$this->appId.'/'.$this->cluster.'/';
        $api = $base_api.$namespaceName;

        $args = [];
        $args['ip'] = $this->clientIp;
        $config_file = $this->getConfigFile($namespaceName);
        $args['releaseKey'] = $this->_getReleaseKey($config_file);

        $api .= '?' . http_build_query($args);

        $ch = curl_init($api);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->pullTimeout);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode == 200) {
            $result = json_decode($body, true);
            $content = '<?php return ' . var_export($result, true) . ';';
            file_put_contents($config_file, $content);
        }elseif ($httpCode != 304) {
            echo $body ?: $error."\n";
            return false;
        }
        return true;
    }

    //获取多个namespace的配置-无缓存的方式
    public function pullConfigBatch(array $namespaceNames) {
        if (! $namespaceNames) return [];
        $multi_ch = curl_multi_init();
        $request_list = [];
        $base_url = rtrim($this->configServer, '/').'/configs/'.$this->appId.'/'.$this->cluster.'/';
        $query_args = [];
        $query_args['ip'] = $this->clientIp;
        foreach ($namespaceNames as $namespaceName) {
            $request = [];
            $config_file = $this->getConfigFile($namespaceName);
            $request_url = $base_url.$namespaceName;
            $query_args['releaseKey'] = $this->_getReleaseKey($config_file);
            $query_string = '?'.http_build_query($query_args);
            $request_url .= $query_string;
            $ch = curl_init($request_url);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->pullTimeout);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $request['ch'] = $ch;
            $request['config_file'] = $config_file;
            $request_list[$namespaceName] = $request;
            curl_multi_add_handle($multi_ch, $ch);
        }

        $active = null;
        // 执行批处理句柄
        do {
            $mrc = curl_multi_exec($multi_ch, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($multi_ch) == -1) {
                usleep(100);
            }
            do {
                $mrc = curl_multi_exec($multi_ch, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        }

        // 获取结果
        $response_list = [];
        foreach ($request_list as $namespaceName => $req) {
            $response_list[$namespaceName] = true;
            $result = curl_multi_getcontent($req['ch']);
            $code = curl_getinfo($req['ch'], CURLINFO_HTTP_CODE);
            $error = curl_error($req['ch']);
            curl_multi_remove_handle($multi_ch,$req['ch']);
            curl_close($req['ch']);
            if ($code == 200) {
                $result = json_decode($result, true);
                $content = '<?php return '.var_export($result, true).';';
                file_put_contents($req['config_file'], $content);
            }elseif ($code != 304) {
                echo 'pull config of namespace['.$namespaceName.'] error:'.($result ?: $error)."\n";
                $response_list[$namespaceName] = false;
            }
        }
        curl_multi_close($multi_ch);
        return $response_list;
    }

    protected function _listenChange(&$ch, $callback = null) {
        $base_url = rtrim($this->configServer, '/').'/notifications/v2?';
        $params = [];
        $params['appId'] = $this->appId;
        $params['cluster'] = $this->cluster;
        do {
            $params['notifications'] = json_encode(array_values($this->notifications));
            $query = http_build_query($params);
            curl_setopt($ch, CURLOPT_URL, $base_url.$query);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            if ($httpCode == 200) {
                $res = json_decode($response, true);
                $change_list = [];
                foreach ($res as $r) {
                    if ($r['notificationId'] != $this->notifications[$r['namespaceName']]['notificationId']) {
                        $change_list[$r['namespaceName']] = $r['notificationId'];
                    }
                }
                $response_list = $this->pullConfigBatch(array_keys($change_list));
                foreach ($response_list as $namespaceName => $result) {
                    $result && ($this->notifications[$namespaceName]['notificationId'] = $change_list[$namespaceName]);
                }
                //如果定义了配置变更的回调，比如重新整合配置，则执行回调
                ($callback instanceof \Closure) && call_user_func($callback);
                var_dump($httpCode);
            }elseif ($httpCode != 304) {
                var_dump($httpCode);
                throw new \Exception($response ?: $httpCode . $error);
            }
        }while (true);
    }

    /**
     * @param $callback 监听到配置变更时的回调处理
     * @return mixed
     */
    public function start($callback = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->intervalTimeout);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        try {
            $this->_listenChange($ch, $callback);
        }catch (\Exception $e) {
            curl_close($ch);
            return $e->getMessage();
        }
    }

    public function startNew(array $needToBeLoadedFiles, array $listenChangeParams, $callback)
    {
        try {
            // 获取有变化的namespace列表.
            $result = $this->getRequestList($listenChangeParams);
            $change_list = $this->getChangeList($result);
            // 拉取有变化的namespace最新的内容并生成文件.
            $this->batchPullChangeConfigAndGeneralLatestConfigFiles($change_list, $needToBeLoadedFiles);

        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    private function getRequestList($listenChangeParams)
    {
        $return = array(
            'request_list' => array(),
            'multi_ch' => ''
        );

        $base_url = rtrim($this->configServer, '/') . '/notifications/v2?';

        $multi_ch = curl_multi_init();

        foreach ($listenChangeParams as $key => $param) {
            $notifications = $param['notifications'];
            foreach ($notifications as &$notification) {
                unset($notification['releaseKey']);
            }
            $requestParam = array(
                'appId' => $param['appId'],
                'cluster' => $param['cluster'],
                'notifications' => json_encode($notifications)
            );

            $url = $base_url . http_build_query($requestParam);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 80);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $param['ch'] = $ch;
            $return['request_list'][$key] = $param;
            curl_multi_add_handle($multi_ch, $ch);
        }

        $active = null;
        // 执行批处理句柄
        do {
            $mrc = curl_multi_exec($multi_ch, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($multi_ch) == -1) {
                usleep(100);
            }
            do {
                $mrc = curl_multi_exec($multi_ch, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        }
        $return['multi_ch'] = $multi_ch;
        return $return;
    }

    private function getChangeList($param)
    {
        $change_list = array();
        $request_list = $param['request_list'];
        $multi_ch = $param['multi_ch'];
        foreach ($request_list as $key => $req) {
            $result = curl_multi_getcontent($req['ch']);
            $httpCode = curl_getinfo($req['ch'], CURLINFO_HTTP_CODE);
            $error = curl_error($req['ch']);
            curl_multi_remove_handle($multi_ch, $req['ch']);
            curl_close($req['ch']);
            if ($httpCode == 200) {
                // 成功
                $list = json_decode($result, true);
                foreach ($list as $change) {
                    $cc['appId'] = $req['appId'];
                    $cc['cluster'] = $req['cluster'];
                    $cc['namespaceName'] = $change['namespaceName'];
                    $cc['notificationId'] = $change['notificationId'];
                    $cc['ip'] = $this->clientIp;
                    $cc['details'] = $change['messages']['details'];
                    $releaseKey = '';
                    foreach ($req['notifications'] as $notification) {
                        if ($notification['namespaceName'] == $change['namespaceName']) {
                            $releaseKey = $notification['releaseKey'];
                            break;
                        }
                    }
                    $cc['releaseKey'] = $releaseKey;
                    $change_list[] = $cc;
                }
            } elseif ($httpCode != 304) {
                // 此处要写日志
                throw new \Exception('[Code]: ' . $httpCode . ' [Result] ' . $result);
            }
        }
        curl_multi_close($multi_ch);
        return $change_list;
    }

    private function batchPullChangeConfigAndGeneralLatestConfigFiles($change_list, $needToBeLoadedFiles)
    {
        $multi_ch = curl_multi_init();

        $request_list = array();

        foreach ($change_list as $k => $change) {
            $request = array();
            $query = array(
                'releaseKey' => $change['releaseKey'],
                'ip' => $change['ip']
            );
            $url = rtrim($this->configServer, '/').'/configs/' . $change['appId'] . '/' . $change['cluster'] . '/' . $change['namespaceName'] . '?'.http_build_query($query);;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $request['ch'] = $ch;
            $request['appId'] = $change['appId'];
            $request['cluster'] = $change['cluster'];
            $request['namespaceName'] = $change['namespaceName'];
            $request['notificationId'] = $change['notificationId'];
            $request['details'] = $change['details'];
            $request['willGeneralConfigFileName'] = $change['appId'] . '.' . $change['cluster'] . '.' . $change['namespaceName'];
            $request_list[] = $request;
            curl_multi_add_handle($multi_ch, $ch);

        }

        $active = null;
        // 执行批处理句柄
        do {
            $mrc = curl_multi_exec($multi_ch, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($multi_ch) == -1) {
                usleep(100);
            }
            do {
                $mrc = curl_multi_exec($multi_ch, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        }

        // 获取结果

        foreach ($request_list as $k => $req) {
            $result = curl_multi_getcontent($req['ch']);
            $httpCode = curl_getinfo($req['ch'], CURLINFO_HTTP_CODE);
            $error = curl_error($req['ch']);
            curl_multi_remove_handle($multi_ch, $req['ch']);
            curl_close($req['ch']);
            if ($httpCode == 200) {
                // 写入文件
                $result = json_decode($result, true);
                $result['notificationId'] = $req['notificationId'];
                foreach ($needToBeLoadedFiles as $file) {
                    if (strpos($file, $req['willGeneralConfigFileName']) !== false) {
                        $file = str_replace('.schema', '', $file);
                        $content = '<?php' . "\n" . 'return ' .var_export($result, true). ';';
                        file_put_contents($file, $content);
                        echo $file;
                        echo "\n";
                    }
                }

            } elseif ($httpCode != 304) {
                // 要记日志
            }
        }
        curl_multi_close($multi_ch);

    }

    public function startNew2(array $need_update_files, array $request_param, array $others,$callback = null)
    {
        $multi_ch = curl_multi_init();
        $request_list = array();
        $base_url = rtrim($this->configServer, '/') . '/notifications/v2?';
        $params = array();
        foreach ($others as $key => $val) {
            $request = array();
            $params['appId'] = $val['appId'];
            $params['cluster'] = $val['cluster'];
            $params['notifications'] = json_encode($val['notifications']);
            $query = http_build_query($params);
            $request_url = $base_url . $query;
            $ch = curl_init($request_url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 80);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $request['ch'] = $ch;
            $request_list[$key] = $request;
            curl_multi_add_handle($multi_ch, $ch);
        }

        $active = null;
        // 执行批处理句柄
        do {
            $mrc = curl_multi_exec($multi_ch, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($multi_ch) == -1) {
                usleep(100);
            }
            do {
                $mrc = curl_multi_exec($multi_ch, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        }

        // 获取结果
        foreach ($request_list as $key => $req) {
            $result = curl_multi_getcontent($req['ch']);
            $httpCode = curl_getinfo($req['ch'],CURLINFO_HTTP_CODE);
            $error = curl_error($req['ch']);
            curl_multi_remove_handle($multi_ch,$req['ch']);
            curl_close($req['ch']);
            var_dump($result);
            var_dump($httpCode);
            var_dump($error);
        }
        die;

    }
}

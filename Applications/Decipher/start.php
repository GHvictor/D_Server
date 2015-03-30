<?php 
use \Workerman\WebServer;
use \GatewayWorker\Gateway;
use \GatewayWorker\BusinessWorker;

// gateway
$gateway = new Gateway("Websocket://0.0.0.0:8585");

$gateway->name = 'DecipherGateway';

$gateway->count = 4;

$gateway->lanIp = '127.0.0.1';

$gateway->startPort = 4000;

$gateway->pingInterval = 10;

$gateway->pingData = '{"type":"ping"}';



$gateway_text = new Gateway("Text://0.0.0.0:8283");
// 进程名称，主要是status时方便识别
$gateway_text->name = 'DecipherGatewayText';
// 开启多少text协议的gateway进程
$gateway_text->count = 4;
// 本机ip（分布式部署时需要设置成内网ip）
$gateway_text->lanIp = '127.0.0.1';
// gateway内部通讯起始端口，起始端口不要重复
$gateway_text->startPort = 2500;

/* 
// 当客户端连接上来时，设置连接的onWebSocketConnect，即在websocket握手时的回调
$gateway->onConnect = function($connection)
{
    $connection->onWebSocketConnect = function($connection , $http_header)
    {
        // 可以在这里判断连接来源是否合法，不合法就关掉连接
        // $_SERVER['SERVER_NAME']标识来自哪个站点的页面发起的websocket链接
        if($_SERVER['SERVER_NAME'] != 'kedou.workerman.net')
        {
            $connection->close();
        }
        // onWebSocketConnect 里面$_GET $_SERVER是可用的
        // var_dump($_GET, $_SERVER);
    };
}; 
*/


// bussinessWorker
$worker = new BusinessWorker();

$worker->name = 'DecipherBusinessWorker';

$worker->count = 4;

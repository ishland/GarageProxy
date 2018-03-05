<?php
echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Info] Initializing...\r\n";
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Lib\Timer;
require_once __DIR__ . '/Autoloader.php';
@mkdir(getcwd() . "/logs");
Worker::$stdoutFile = getcwd() . '/logs/latest.log';
Worker::$pidFile = getcwd() . '/.pid';
Worker::$logFile = getcwd() . '/logs/workerman.log';
$workerid = 0;

function loadConfig()
{
    if (! file_exists(getcwd() . "/config.php")) {
        echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Info] Configration not found, create one.\r\n";
        file_put_contents(getcwd() . "/config.php", file_get_contents(__DIR__ . "/defaults/config.php"));
    }
    require_once getcwd() . "/config.php";
}

function setWorker($listening, $remote, $workers)
{
    global $workerid;
    $workerid = $workerid + 1;
    global $$workerid;
    echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Info] Setting up Worker-{$workerid}...\r\n";
    if (! $listening or ! $remote or ! $workers) {
        echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Error] Configuration is not vaild! While setting up Worker-{$workerid}!";
        echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Error] Configuration of Worker-{$workerid} has failed!";
        return false;
    }
    echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Debug] The settings of Worker-{$workerid} is:\r\n";
    echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Debug] Worker count: {$workers}\r\n";
    echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Debug] Forwarding: {$listening} -> {$remote}\r\n";
    $$workerid = new Worker($listening);
    $$workerid->count = $workers;
    $$workerid->name = "worker" . $workerid;
    $$workerid->listening = $listening;
    $$workerid->remote = $remote;
    $$workerid->proxyid = $workerid;
    $$workerid->onWorkerStart = function ($worker) {
        onWorkerStart($worker);
    };
    $$workerid->onConnect = 'onConnect';
    echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Info] Configuration of Worker-{$workerid} has completed.\r\n";
}

$timer = new Worker();
$timer->count = 1;
$timer->name = "timer";
$timer->onWorkerStart = function($worker) {
    echo "[" . date('Y-m-d H:i:s') . "][Master][Startup][Info] Timer started.\r\n";
    $conn_to_master = new AsyncTcpConnection("tcp://127.0.0.1:4400");
    $conn_to_master->onClose = function ($connection) {
        $connection->close();
        $connection->connect();
    };
    $conn_to_master->onError = function ($connection_to_server) {
        $connection->close();
        $connection->connect();
    };
    $conn_to_master->connect();
    Timer::add(1, function() use ($conn_to_master){
        $conn_to_master->send(json_encode(Array("action" => "timer")));
    });
};

$master = new Worker("tcp://127.0.0.1:4400");
$master->count = 1;
$master->name = "master";
$master->onWorkerStart = function($worker) {
    $worker->pps = 0;
    $worker->pps_temp = 0;
    $worker->active_conn = 0;
    echo "[" . date('Y-m-d H:i:s') . "][Master][Startup][Info] Master started.\r\n";
};
$master->onMessage = function($connection, $buffer) use ($master){
    $arr = json_decode($buffer, true);
    if($arr['action'] == "new"){
        $connection->workerid = $arr['worker'];
        $connection->proxyid = $arr['proxy'];
    }
    if($arr['action'] == "new_conn"){
        $connection->ip = $arr['ip'];
        $connection->port = $arr['port'];
        $master->active_conn ++;
    }
    if($arr['action'] == "new_msg"){
        $master->pps_temp ++;
    }
    if($arr['action'] == "close_conn"){
        $master->active_conn --;
    }
    if($arr['action'] == "timer"){
        $master->pps = $master->pps_temp;
        $master->pps_temp = 0;
        echo "\rStatus: Active connections: " . $master->active_conn . ", PPS: " . $master->pps . "             \r";
    }
};

function onWorkerStart($worker)
{
    sleep(1);
    global $conn_to_master;
    $conn_to_master = new AsyncTcpConnection("tcp://127.0.0.1:4400");
    $conn_to_master->onClose = function ($connection) {
        $connection->close();
        $connection->connect();
    };
    $conn_to_master->onError = function ($connection_to_server) {
        $connection->close();
        $connection->connect();
    };
    $conn_to_master->connect();
    global $ADDRESS, $global_uid, $workerid;
    global $$workerid;
    $global_uid = 0;
    $ADDRESS = $worker->remote;
    $workerid = $worker->proxyid;
    $conn_to_master->send(json_encode(Array("action" => "new", "worker" => $worker->id, "proxy" => $worker->proxyid)));
};

echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Debug] Registration of the Worker Starting function has completed.\r\n";
echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Debug] Registering Worker Working functions...\r\n";

function onConnect($connection)
{
    global $workerid, $ADDRESS, $global_uid;
    global $$workerid;
    global $conn_to_master;
    $connection->uid = ++ $global_uid;
    $connection->worker = $$workerid->id;
    $connection->proxyid = $$workerid->proxyid;
    $conn_to_master->send(json_encode(Array("action" => "new_conn", "ip" => $connection->getRemoteIp(), "port" => $connection->getRemotePort(), "uid" => $connection->uid)));
    $connection_to_server = new AsyncTcpConnection($ADDRESS);
    $connection_to_server->onMessage = function ($connection_to_server, $buffer) use ($connection) {
        global $conn_to_master;
        $conn_to_master->send(json_encode(Array("action" => "new_msg")));
        $connection->send($buffer);
    };
    $connection_to_server->onClose = function ($connection_to_server) use ($connection) {
        $connection->close();
    };
    $connection_to_server->onError = function ($connection_to_server, $errcode, $errmsg) use ($connection) {
        $connection->close();
    };
    
    $connection_to_server->connect();
    
    $connection->onMessage = function ($connection, $buffer) use ($connection_to_server) {
        global $conn_to_master;
        $conn_to_master->send(json_encode(Array("action" => "new_msg")));
        $connection_to_server->send($buffer);
    };
    $connection->onClose = function ($connection) use ($connection_to_server) {
        global $conn_to_master;
        $conn_to_master->send(json_encode(Array("action" => "close_conn", "uid" => $connection->uid)));
        $connection_to_server->close();
    };
    $connection->onError = function ($connection, $errcode, $errormsg) use ($connection_to_server) {
        $connection_to_server->close();
    };
}
;

echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Info] Reading Settings...\r\n";

loadConfig();

echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Info] Configuration has completed.\r\n";
echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Info] Initializtion has completed.\r\n";
echo "[" . date('Y-m-d H:i:s') . "][Main][Startup][Info] Launching Workerman...\r\n";
echo "[" . date('Y-m-d H:i:s') . "][Main][Startup][Info] ";

Worker::runAll();

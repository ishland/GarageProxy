<?php
echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Info] Initializing...\r\n";
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;
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

function onWorkerStart($worker)
{
    $global_uid = 0;
    global $ADDRESS, $global_uid, $workerid;
    global $$workerid;
    $ADDRESS = $worker->remote;
    $workerid = $worker->proxyid;
    echo "[" . date('Y-m-d H:i:s') . "][worker:{$worker->proxyid}-{$worker->id}][Startup][Info] Worker {$worker->proxyid}-{$worker->id} started.\r\n";
}
;

echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Debug] Registration of the Worker Starting function has completed.\r\n";
echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Debug] Registering Worker Working functions...\r\n";

function onConnect($connection)
{
    global $workerid, $ADDRESS, $global_uid;
    global $$workerid;
    $connection->uid = ++ $global_uid;
    $connection->msgid = 0;
    $connection->worker = $$workerid->id;
    $connection->proxyid = $$workerid->proxyid;
    echo "[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Info] A client connected this proxy using " . $connection->getRemoteIp() . ":" . $connection->getRemotePort() . ", and its uid is " . $connection->uid . ".\r\n[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Info] PreParing to connect to the server.\r\n";
    $connection_to_server = new AsyncTcpConnection($ADDRESS);
    $connection_to_server->onMessage = function ($connection_to_server, $buffer) use ($connection) {
        $connection->msgid ++;
        echo "[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Debug] Received a message from the server. Sending it to the client.\r\n";
        $connection->send($buffer);
        echo "[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Debug] The message sent to the client.\r\n";
    };
    $connection_to_server->onClose = function ($connection_to_server) use ($connection) {
        echo "[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Debug] The connection closed by the server. Closing the connection to the client.\r\n";
        $connection->close();
        echo "[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Debug] Closed the connection to the client.\r\n";
    };
    $connection_to_server->onError = function ($connection_to_server, $errcode, $errmsg) use ($connection) {
        echo "[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Error] The connection to the server made a mistake.({$errcode} {$errmsg}) Closing the connection to the client.\r\n";
        $connection->close();
        echo "[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Info] Closed the connection to the client.\r\n";
    };
    
    echo "[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Info] Connecting...\r\n";
    $conn = $connection_to_server->connect();
    if ($conn == true)
        echo "[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Info] Connected.\r\n";
    
    $connection->onMessage = function ($connection, $buffer) use ($connection_to_server) {
        $connection->msgid ++;
        echo "[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Debug] Received a message from the client. Sending it to the server.\r\n";
        $connection_to_server->send($buffer);
        echo "[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Debug] A message sent to the server.\r\n";
    };
    $connection->onClose = function ($connection) use ($connection_to_server) {
        echo "[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Debug] A connection closed by the client. Closing the connection to the server.\r\n";
        $connection_to_server->close();
        echo "[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Debug] Closed the connection to the server.\r\n";
    };
    $connection->onError = function ($connection, $errcode, $errormsg) use ($connection_to_server) {
        echo "[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Error] The connection to the client made a mistake.({$errcode} {$errmsg}) Closing the connection to the server.\r\n";
        $connection_to_server->close();
        echo "[" . date('Y-m-d H:i:s') . "][worker:{$connection->proxyid}-{$connection->id}][uid:{$connection->uid}][Msgs:{$connection->msgid}][Info] Closed a connection to the server.\r\n";
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

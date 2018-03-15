<?php
echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Info] Initializing...\n";
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Connection\TcpConnection;
use Workerman\Lib\Timer;
require_once __DIR__ . '/Autoloader.php';
@mkdir(getcwd() . "/logs");
Worker::$stdoutFile = getcwd() . '/logs/latest.log';
Worker::$pidFile = getcwd() . '/.pid';
Worker::$logFile = getcwd() . '/logs/workerman.log';
TcpConnection::$defaultMaxSendBufferSize = 256*1024*1024;
TcpConnection::$maxPackageSize = 256*1024*1024;
$workerid = 0;

function loadConfig()
{
    if (! file_exists(getcwd() . "/config.php")) {
        echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Info] Configration not found, create one.\n";
        file_put_contents(getcwd() . "/config.php", file_get_contents(__DIR__ . "/defaults/config.php"));
    }
    require_once getcwd() . "/config.php";
}

function setWorker($listening, $remote, $workers)
{
    global $workerid;
    $workerid = $workerid + 1;
    global $$workerid;
    echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Info] Setting up Worker-{$workerid}...\n";
    if (! $listening or ! $remote or ! $workers) {
        echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Error] Configuration is not vaild! While setting up Worker-{$workerid}!";
        echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Error] Configuration of Worker-{$workerid} has failed!";
        return false;
    }
    echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Debug] The settings of Worker-{$workerid} is:\n";
    echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Debug] Worker count: {$workers}\n";
    echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Debug] Forwarding: {$listening} -> {$remote}\n";
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
    echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Info] Configuration of Worker-{$workerid} has completed.\n";
}

$timer = new Worker();
$timer->count = 1;
$timer->name = "timer";
$timer->onWorkerStart = function($worker) {;
    $conn_to_master = new AsyncTcpConnection("tcp://127.0.0.1:4400");
    $conn_to_master->onClose = function ($connection) {
        $connection->reConnect();
        $connection->send(json_encode(Array("action" => "reconn", "worker" => "timer")));
    };
    $conn_to_master->onError = function ($connection_to_server) {
        $connection_to_server->close();
    };
    $conn_to_master->connect();
    $conn_to_master->send(json_encode(Array("action" => "new", "worker" => "timer")));
    Timer::add(1, function() use ($conn_to_master){
        $conn_to_master->send(json_encode(Array("action" => "timer")));
    });
};

$master = new Worker("tcp://127.0.0.1:4400");
$master->count = 1;
$master->name = "proxy-master";
$master->onWorkerStart = function($worker) {
    $worker->pps = Array("in" => 0, "out" => 0);
    $worker->pps_temp = Array("in" => 0, "out" => 0);
    $worker->active_conn = 0;
    $worker->speed = Array("in" => 0, "out" => 0);
    $worker->speed_temp = Array("in" => 0, "out" => 0);
    echo "[" . date('Y-m-d H:i:s') . "][Master][Startup][Info] Master started.\n";
};
$master->onMessage = function($connection, $buffer) use ($master){
    $arr = json_decode($buffer, true);
    if($arr['action'] == "reconn"){
        if($arr['worker'] == "timer"){
            $connection->workerid = "timer";
            echo "\r[" . date('Y-m-d H:i:s') . "][Master][Info] Timer reconnected.          \n";
            echo "\rStatus: Conns: " . $master->active_conn . ", PPS: in: " . $master->pps["in"] . ", out: " . $master->pps["out"] . " Speed: in: " . round($master->speed["in"] / 1024, 3) . "KB/s, out: " . round($master->speed["out"] / 1024, 3) . "KB/s             \r";
        } else {
            $connection->workerid = $arr['worker'];
            $connection->proxyid = $arr['proxy'];
            echo "\r[" . date('Y-m-d H:i:s') . "][Master][Info] Worker {$connection->proxyid}-{$connection->workerid} reconnected.          \n";
            echo "\rStatus: Conns: " . $master->active_conn . ", PPS: in: " . $master->pps["in"] . ", out: " . $master->pps["out"] . " Speed: in: " . round($master->speed["in"] / 1024, 3) . "KB/s, out: " . round($master->speed["out"] / 1024, 3) . "KB/s             \r";
        }
    }
    if($arr['action'] == "new"){
        if($arr['worker'] == "timer"){
            $connection->workerid = "timer";
            echo "\r[" . date('Y-m-d H:i:s') . "][Master][Info] Timer started.          \n";
            echo "\rStatus: Conns: " . $master->active_conn . ", PPS: in: " . $master->pps["in"] . ", out: " . $master->pps["out"] . " Speed: in: " . round($master->speed["in"] / 1024, 3) . "KB/s, out: " . round($master->speed["out"] / 1024, 3) . "KB/s             \r";
        } else {
            $connection->workerid = $arr['worker'];
            $connection->proxyid = $arr['proxy'];
            echo "\r[" . date('Y-m-d H:i:s') . "][Master][Info] Worker {$connection->proxyid}-{$connection->workerid} started.          \n";
            echo "\rStatus: Conns: " . $master->active_conn . ", PPS: in: " . $master->pps["in"] . ", out: " . $master->pps["out"] . " Speed: in: " . round($master->speed["in"] / 1024, 3) . "KB/s, out: " . round($master->speed["out"] / 1024, 3) . "KB/s             \r";
        }
    }
    if($arr['action'] == "new_conn"){
        $connection->ip = $arr['ip'];
        $connection->port = $arr['port'];
        $connection->uid = $arr['uid'];
        $master->active_conn ++;
        echo "\r[" . date('Y-m-d H:i:s') . "][Master][Info][User: {$connection->proxyid}-{$connection->workerid}-{$connection->uid}] Server bridge [/{$connection->ip}:$connection->port] connected.      \n";
        echo "\rStatus: Conns: " . $master->active_conn . ", PPS: in: " . $master->pps["in"] . ", out: " . $master->pps["out"] . " Speed: in: " . round($master->speed["in"] / 1024, 3) . "KB/s, out: " . round($master->speed["out"] / 1024, 3) . "KB/s             \r";
        
    }
    if($arr['action'] == "new_msg"){
        //$master->pps_temp ++;
        //$master->speed_temp = $master->speed_temp + $arr["strlen"];
        if($arr['handle'] == "in"){
            $master->pps_temp["in"]++;
            $master->speed_temp["in"] = $master->speed_temp["in"] + $arr["strlen"];
        }
        if($arr['handle'] == "out"){
            $master->pps_temp["out"]++;
            $master->speed_temp["out"] = $master->speed_temp["out"] + $arr["strlen"];
        }
    }
    if($arr['action'] == "close_conn"){
        $connection->ip = $arr['ip'];
        $connection->port = $arr['port'];
        $connection->uid = $arr['uid'];
        $master->active_conn --;
        echo "\r[" . date('Y-m-d H:i:s') . "][Master][Info][User: {$connection->proxyid}-{$connection->workerid}-{$connection->uid}] Server bridge [/{$connection->ip}:$connection->port] disconnected.      \n";
        echo "\rStatus: Conns: " . $master->active_conn . ", PPS: in: " . $master->pps["in"] . ", out: " . $master->pps["out"] . " Speed: in: " . round($master->speed["in"] / 1024, 3) . "KB/s, out: " . round($master->speed["out"] / 1024, 3) . "KB/s             \r";
    }
    if($arr['action'] == "timer"){
        $master->pps = $master->pps_temp;
        $master->pps_temp = Array("in" => 0, "out" => 0);
        $master->speed = $master->speed_temp;
        $master->speed_temp = Array("in" => 0, "out" => 0);
        echo "\rStatus: Conns: " . $master->active_conn . ", PPS: in: " . $master->pps["in"] . ", out: " . $master->pps["out"] . " Speed: in: " . round($master->speed["in"] / 1024, 3) . "KB/s, out: " . round($master->speed["out"] / 1024, 3) . "KB/s             \r";
    }
};
$master->onClose = function($connection) use ($master) {
    if($connection->workerid == "timer"){
        echo "[" . date('Y-m-d H:i:s') . "][Master][Info] Timer stopped.\n";
        unset($connection->workerid);
    } else {
        echo "[" . date('Y-m-d H:i:s') . "][Master][Info] Worker {$connection->proxyid}-{$connection->workerid} stopped.\n";
        unset($connection->proxyid);
        unset($connection->workerid);
    }
};
$master->onWorkerStop = function($worker) {
    echo "[" . date('Y-m-d H:i:s') . "][Master][Info] Master stopped.\n";
};

function onWorkerStart($worker)
{
    sleep(1);
    global $conn_to_master;
    $conn_to_master = new AsyncTcpConnection("tcp://127.0.0.1:4400");
    $conn_to_master->onClose = function ($connection) use ($worker){
        global $ADDRESS, $global_uid, $workerid;
        $connection->reConnect(1);
        $connection->send(json_encode(Array("action" => "reconn", "worker" => $worker->id + 1, "proxy" => $worker->proxyid)));
    };
    $conn_to_master->onError = function ($connection_to_server) {
        $connection_to_server->close();
    };
    $conn_to_master->connect();
    global $ADDRESS, $global_uid, $workerid;
    global $$workerid;
    $global_uid = 0;
    $ADDRESS = $worker->remote;
    $workerid = $worker->proxyid;
    $conn_to_master->send(json_encode(Array("action" => "new", "worker" => $worker->id + 1, "proxy" => $worker->proxyid)));
};

echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Debug] Registration of the Worker Starting function has completed.\n";
echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Debug] Registering Worker Working functions...\n";

function onConnect($connection)
{
    global $workerid, $ADDRESS, $global_uid;
    global $$workerid;
    global $conn_to_master;
    $connection->uid = ++ $global_uid;
    $connection->worker = $$workerid->id;
    $connection->proxyid = $$workerid->proxyid;
    $connection->compression = false;
    $connection->firstmsg = true;
    $conn_to_master->send(json_encode(Array("action" => "new_conn", "ip" => $connection->getRemoteIp(), "port" => $connection->getRemotePort(), "uid" => $connection->uid)));
    $connection_to_server = new AsyncTcpConnection($ADDRESS);
    $connection_to_server->onMessage = function ($connection_to_server, $buffer) use ($connection) {
        global $conn_to_master;
        if($connection->compression){
            $conn_to_master->send(json_encode(Array("action" => "new_msg", "handle" => "in", "strlen" => strlen($buffer), "uid" => $connection->uid)));
            $compressed = gzdeflate($buffer, 9);
            $connection->send($compressed);
            $conn_to_master->send(json_encode(Array("action" => "new_msg", "handle" => "out", "strlen" => strlen($compressed), "uid" => $connection->uid)));
            return;
        }
        $conn_to_master->send(json_encode(Array("action" => "new_msg", "handle" => "in", "strlen" => strlen($buffer), "uid" => $connection->uid)));
        $connection->send($buffer);
        $conn_to_master->send(json_encode(Array("action" => "new_msg", "handle" => "out", "strlen" => strlen($buffer), "uid" => $connection->uid)));
    };
    $connection_to_server->onClose = function ($connection_to_server) use ($connection) {
        $connection->close();
    };
    $connection_to_server->onError = function ($connection_to_server, $errcode, $errmsg) use ($connection) {
        $connection->close();
    };
    $connection_to_server->onBufferFull = function ($connection_to_server) use ($connection){
        $connection_to_server->pauseRecv();
    };
    $connection_to_server->onBufferDrain = function ($connection_to_server) use ($connection){
        $connection_to_server->resumeRecv();
    };
    
    $connection_to_server->connect();
    
    $connection->onMessage = function ($connection, $buffer) use ($connection_to_server) {
        global $conn_to_master;
        if($connection->firstmsg){
            if(substr($buffer, 0, 17) !== "GarageProxyClient"){
                $conn_to_master->send(json_encode(Array("action" => "new_msg", "handle" => "in", "strlen" => strlen($buffer), "uid" => $connection->uid)));
                $connection_to_server->send($buffer);
                $conn_to_master->send(json_encode(Array("action" => "new_msg", "handle" => "out", "strlen" => strlen($buffer), "uid" => $connection->uid)));
                $connection->firstmsg = false;
                return;
            }
            $arr = json_decode(substr($buffer, 17), true);
            $connection->compression = $arr['compression'];
            $connection->firstmsg = false;
            $connection->send("GarageProxy-OK");
            $conn_to_master->send(json_encode(Array("action" => "new_msg", "handle" => "in", "strlen" => strlen($buffer), "uid" => $connection->uid)));
            return;
        }
        if($connection->compression){
            $conn_to_master->send(json_encode(Array("action" => "new_msg", "handle" => "in", "strlen" => strlen($buffer), "uid" => $connection->uid)));
            $uncompressed = gzinflate($buffer);
            $connection_to_server->send($uncompressed);
            $conn_to_master->send(json_encode(Array("action" => "new_msg", "handle" => "out", "strlen" => strlen($uncompressed), "uid" => $connection->uid)));
            return;
        }
        $conn_to_master->send(json_encode(Array("action" => "new_msg", "handle" => "in", "strlen" => strlen($buffer), "uid" => $connection->uid)));
        $connection_to_server->send($buffer);
        $conn_to_master->send(json_encode(Array("action" => "new_msg", "handle" => "out", "strlen" => strlen($buffer), "uid" => $connection->uid)));
        $connection->firstmsg = false;
    };
    $connection->onClose = function ($connection) use ($connection_to_server) {
        global $conn_to_master;
        $conn_to_master->send(json_encode(Array("action" => "close_conn", "ip" => $connection->getRemoteIp(), "port" => $connection->getRemotePort(), "uid" => $connection->uid)));
        $connection_to_server->close();
    };
    $connection->onError = function ($connection, $errcode, $errormsg) use ($connection_to_server) {
        global $conn_to_master;
        $conn_to_master->send(json_encode(Array("action" => "close_conn", "ip" => $connection->getRemoteIp(), "port" => $connection->getRemotePort(), "uid" => $connection->uid)));
        $connection_to_server->close();
    };
    $connection->onBufferFull = function ($connection) use ($connection_to_server){
        $connection->pauseRecv();
    };
    $connection->onBufferDrain = function ($connection) use ($connection_to_server){
        $connection->resumeRecv();
    };
};

echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Info] Reading Settings...\n";

loadConfig();

echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Info] Configuration has completed.\n";
echo "[" . date('Y-m-d H:i:s') . "][Main][Init][Info] Initializtion has completed.\n";
echo "[" . date('Y-m-d H:i:s') . "][Main][Startup][Info] Launching Workerman...\n";
echo "[" . date('Y-m-d H:i:s') . "][Main][Startup][Info] ";

Worker::runAll();

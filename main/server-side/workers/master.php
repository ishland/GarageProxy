<?php
use Workerman\Worker;

$master = new Worker("unix://master.sock");
$master->count = 1;
$master->name = "proxy-master";
$master->onWorkerStart = function ($worker)
{
    $worker->active_conn = 0;

    echo "[" . date('Y-m-d H:i:s') . "][Master][Startup][Info] Master started.\n";
};
$master->onMessage = function ($connection, $buffer) use ( $master)
{
    $arr = json_decode($buffer, true);
    if ($arr['action'] == "reconn") {

        $connection->workerid = $arr['worker'];
        $connection->proxyid = $arr['proxy'];
        echo "\r[" . date('Y-m-d H:i:s') .
                "][Master][Info] Worker {$connection->proxyid}-{$connection->workerid} reconnected.          \n";
    }
    if ($arr['action'] == "new") {

        $connection->workerid = $arr['worker'];
        $connection->proxyid = $arr['proxy'];
        echo "\r[" . date('Y-m-d H:i:s') .
                "][Master][Info] Worker {$connection->proxyid}-{$connection->workerid} started.          \n";
    }
    if ($arr['action'] == "new_conn") {
        $connection->ip = $arr['ip'];
        $connection->port = $arr['port'];
        $connection->uid = $arr['uid'];
        $master->active_conn ++;
        echo "\r[" . date('Y-m-d H:i:s') .
                "][Master][Info][User: {$connection->proxyid}-{$connection->workerid}-{$connection->uid}] Server bridge [/{$connection->ip}:$connection->port] connected.      \n";
    }
    if ($arr['action'] == "close_conn") {
        $connection->ip = $arr['ip'];
        $connection->port = $arr['port'];
        $connection->uid = $arr['uid'];
        $master->active_conn --;
        echo "\r[" . date('Y-m-d H:i:s') .
                "][Master][Info][User: {$connection->proxyid}-{$connection->workerid}-{$connection->uid}] Server bridge [/{$connection->ip}:$connection->port] disconnected.      \n";
    }
};
$master->onClose = function ($connection) use ( $master)
{
    if ($connection->workerid == "timer") {
        echo "[" . date('Y-m-d H:i:s') . "][Master][Info] Timer stopped.\n";
        unset($connection->workerid);
    } else {
        echo "[" . date('Y-m-d H:i:s') .
                "][Master][Info] Worker {$connection->proxyid}-{$connection->workerid} stopped.\n";
        unset($connection->proxyid);
        unset($connection->workerid);
    }
};
$master->onWorkerStop = function ($worker)
{
    echo "[" . date('Y-m-d H:i:s') . "][Master][Info] Master stopped.\n";
};

<?php
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Connection\TcpConnection;
use Workerman\Lib\Timer;

class ProxyWorker {
    public function onWorkerStart($worker)
    {
        sleep(1);
        global $conn_to_master, $masterport;
        $conn_to_master = new AsyncTcpConnection("tcp://127.0.0.1:" . $masterport);
        $conn_to_master->onClose = function ($connection) use ($worker) {
            global $ADDRESS, $global_uid, $workerid;
            $connection->reConnect();
            $connection->send(json_encode(Array(
                "action" => "reconn",
                "worker" => $worker->id + 1,
                "proxy" => $worker->proxyid
                )));
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
        $conn_to_master->send(json_encode(Array(
            "action" => "new",
            "worker" => $worker->id + 1,
            "proxy" => $worker->proxyid
            )));
    }
    
    public function onConnectMode1($connection)
    {
        global $workerid, $ADDRESS, $global_uid;
        global $$workerid;
        global $conn_to_master;
        $connection->uid = ++ $global_uid;
        $connection->worker = $$workerid->id;
        $connection->proxyid = $$workerid->proxyid;
        $connection->compression = false;
        $connection->firstmsg = true;
        $conn_to_master->send(json_encode(Array(
            "action" => "new_conn",
            "ip" => $connection->getRemoteIp(),
            "port" => $connection->getRemotePort(),
            "uid" => $connection->uid
            )));
        $connection_to_server = new AsyncTcpConnection($ADDRESS);
        $connection_to_server->onMessage = function ($connection_to_server, $buffer) use ($connection) {
            global $conn_to_master;
            if ($connection->compression) {
                $conn_to_master->send(json_encode(Array(
                    "action" => "new_msg",
                    "handle" => "in",
                    "strlen" => strlen($buffer),
                    "uid" => $connection->uid
                    )));
                $compressed = gzdeflate($buffer, 9);
                $connection->send($compressed);
                $conn_to_master->send(json_encode(Array(
                    "action" => "new_msg",
                    "handle" => "out",
                    "strlen" => strlen($compressed),
                    "uid" => $connection->uid
                    )));
                return;
            }
            $conn_to_master->send(json_encode(Array(
                "action" => "new_msg",
                "handle" => "in",
                "strlen" => strlen($buffer),
                "uid" => $connection->uid
                )));
            $connection->send($buffer);
            $conn_to_master->send(json_encode(Array(
                "action" => "new_msg",
                "handle" => "out",
                "strlen" => strlen($buffer),
                "uid" => $connection->uid
                )));
        };
        $connection_to_server->onClose = function ($connection_to_server) use ($connection) {
            $connection->close();
        };
        $connection_to_server->onError = function ($connection_to_server, $errcode, $errmsg) use ($connection) {
            $connection->close();
        };
        $connection_to_server->onBufferFull = function ($connection_to_server) use ($connection) {
            $connection_to_server->pauseRecv();
        };
        $connection_to_server->onBufferDrain = function ($connection_to_server) use ($connection) {
            $connection_to_server->resumeRecv();
        };
        
        $connection_to_server->connect();
        
        $connection->onMessage = function ($connection, $buffer) use ($connection_to_server) {
            global $conn_to_master;
            if ($connection->firstmsg) {
                if (substr($buffer, 0, 17) !== "GarageProxyClient") {
                    $conn_to_master->send(json_encode(Array(
                        "action" => "new_msg",
                        "handle" => "in",
                        "strlen" => strlen($buffer),
                        "uid" => $connection->uid
                        )));
                    $connection_to_server->send($buffer);
                    $conn_to_master->send(json_encode(Array(
                        "action" => "new_msg",
                        "handle" => "out",
                        "strlen" => strlen($buffer),
                        "uid" => $connection->uid
                        )));
                    $connection->firstmsg = false;
                    return;
                }
                $arr = json_decode(substr($buffer, 17), true);
                $connection->compression = $arr['compression'];
                $connection->firstmsg = false;
                $connection->send("GarageProxy-OK");
                $conn_to_master->send(json_encode(Array(
                    "action" => "new_msg",
                    "handle" => "in",
                    "strlen" => strlen($buffer),
                    "uid" => $connection->uid
                    )));
                return;
            }
            if ($connection->compression) {
                $conn_to_master->send(json_encode(Array(
                    "action" => "new_msg",
                    "handle" => "in",
                    "strlen" => strlen($buffer),
                    "uid" => $connection->uid
                    )));
                $uncompressed = gzinflate($buffer);
                $connection_to_server->send($uncompressed);
                $conn_to_master->send(json_encode(Array(
                    "action" => "new_msg",
                    "handle" => "out",
                    "strlen" => strlen($uncompressed),
                    "uid" => $connection->uid
                    )));
                return;
            }
            $conn_to_master->send(json_encode(Array(
                "action" => "new_msg",
                "handle" => "in",
                "strlen" => strlen($buffer),
                "uid" => $connection->uid
                )));
            $connection_to_server->send($buffer);
            $conn_to_master->send(json_encode(Array(
                "action" => "new_msg",
                "handle" => "out",
                "strlen" => strlen($buffer),
                "uid" => $connection->uid
                )));
            $connection->firstmsg = false;
        };
        $connection->onClose = function ($connection) use ($connection_to_server) {
            global $conn_to_master;
            $conn_to_master->send(json_encode(Array(
                "action" => "close_conn",
                "ip" => $connection->getRemoteIp(),
                "port" => $connection->getRemotePort(),
                "uid" => $connection->uid
                )));
            $connection_to_server->close();
        };
        $connection->onError = function ($connection, $errcode, $errormsg) use ($connection_to_server) {
            global $conn_to_master;
            $conn_to_master->send(json_encode(Array(
                "action" => "close_conn",
                "ip" => $connection->getRemoteIp(),
                "port" => $connection->getRemotePort(),
                "uid" => $connection->uid
                )));
            $connection_to_server->close();
        };
        $connection->onBufferFull = function ($connection) use ($connection_to_server) {
            $connection->pauseRecv();
        };
        $connection->onBufferDrain = function ($connection) use ($connection_to_server) {
            $connection->resumeRecv();
        };
    }
}
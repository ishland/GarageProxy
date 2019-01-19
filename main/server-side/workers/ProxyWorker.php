<?php
use Workerman\Connection\AsyncTcpConnection;

class ProxyWorker
{

    public function onWorkerStart ($worker)
    {
        sleep(1);
        global $conn_to_master, $masterport;
        $conn_to_master = new AsyncTcpConnection(
                "unix://" . getcwd() . "/master.sock");
        $conn_to_master->onClose = function ($connection) use ( $worker)
        {
            global $ADDRESS, $global_uid, $workerid;
            $connection->reConnect();
            $connection->send(
                    json_encode(
                            Array(
                                    "action" => "reconn",
                                    "worker" => $worker->id + 1,
                                    "proxy" => $worker->proxyid
                            )));
        };
        $conn_to_master->onError = function ($connection_to_server)
        {
            $connection_to_server->close();
        };
        $conn_to_master->connect();
        global $ADDRESS, $global_uid, $workerid;
        global $$workerid;
        $global_uid = 0;
        $ADDRESS = $worker->remote;
        $workerid = $worker->proxyid;
        $conn_to_master->send(
                json_encode(
                        Array(
                                "action" => "new",
                                "worker" => $worker->id + 1,
                                "proxy" => $worker->proxyid
                        )));
    }

    public function onConnectMode1 ($connection)
    {
        global $workerid, $ADDRESS, $global_uid;
        global $$workerid;
        global $conn_to_master;
        $connection->uid = ++ $global_uid;
        $connection->worker = $$workerid->id;
        $connection->proxyid = $$workerid->proxyid;
        $conn_to_master->send(
                json_encode(
                        Array(
                                "action" => "new_conn",
                                "ip" => $connection->getRemoteIp(),
                                "port" => $connection->getRemotePort(),
                                "uid" => $connection->uid
                        )));
        $connection_to_server = new AsyncTcpConnection($ADDRESS);
        $connection_to_server->onMessage = function ($connection_to_server,
                $buffer) use ( $connection)
        {
            global $conn_to_master;
            $connection->send($buffer);
        };
        $connection_to_server->onClose = function ($connection_to_server) use ( 
        $connection)
        {
            $connection->close();
        };
        $connection_to_server->onError = function ($connection_to_server,
                $errcode, $errmsg) use ( $connection)
        {
            $connection->close();
        };
        $connection_to_server->onBufferFull = function ($connection_to_server) use ( 
        $connection)
        {
            $connection_to_server->pauseRecv();
        };
        $connection_to_server->onBufferDrain = function ($connection_to_server) use ( 
        $connection)
        {
            $connection_to_server->resumeRecv();
        };

        $connection_to_server->connect();

        $connection->onMessage = function ($connection, $buffer) use ( 
        $connection_to_server)
        {
            global $conn_to_master;

            $connection_to_server->send($buffer);
        };
        $connection->onClose = function ($connection) use ( 
        $connection_to_server)
        {
            global $conn_to_master;
            $conn_to_master->send(
                    json_encode(
                            Array(
                                    "action" => "close_conn",
                                    "ip" => $connection->getRemoteIp(),
                                    "port" => $connection->getRemotePort(),
                                    "uid" => $connection->uid
                            )));
            $connection_to_server->close();
        };
        $connection->onError = function ($connection, $errcode, $errormsg) use ( 
        $connection_to_server)
        {
            global $conn_to_master;
            $conn_to_master->send(
                    json_encode(
                            Array(
                                    "action" => "close_conn",
                                    "ip" => $connection->getRemoteIp(),
                                    "port" => $connection->getRemotePort(),
                                    "uid" => $connection->uid
                            )));
            $connection_to_server->close();
        };
        $connection->onBufferFull = function ($connection) use ( 
        $connection_to_server)
        {
            $connection->pauseRecv();
        };
        $connection->onBufferDrain = function ($connection) use ( 
        $connection_to_server)
        {
            $connection->resumeRecv();
        };
    }

    public function onConnectMode2 ($connection)
    {
        global $workerid, $ADDRESS, $global_uid;
        global $$workerid;
        $connection->uid = ++ $global_uid;
        $connection->worker = $$workerid->id;
        $connection->proxyid = $$workerid->proxyid;
        $conn_to_master->send(
                json_encode(
                        Array(
                                "action" => "new_conn",
                                "ip" => $connection->getRemoteIp(),
                                "port" => $connection->getRemotePort(),
                                "uid" => $connection->uid
                        )));
        $connection_to_server = new AsyncTcpConnection($ADDRESS);
        $connection->pipe($connection_to_server);
        $connection_to_server->pipe($connection);
        $connection->onClose = function ($connection) use ( 
        $connection_to_server)
        {
            global $conn_to_master;
            $conn_to_master->send(
                    json_encode(
                            Array(
                                    "action" => "close_conn",
                                    "ip" => $connection->getRemoteIp(),
                                    "port" => $connection->getRemotePort(),
                                    "uid" => $connection->uid
                            )));
            $connection_to_server->close();
        };
        $connection_to_server->onClose = function ($connection_to_server) use ( 
        $connection)
        {
            $connection->close();
        };
        $connection_to_80->connect();
    }
}

<?php
require_once __DIR__ . '/Autoloader.php';
use Workerman\Worker;

function checkEverything ()
{
    $errCount = 0;
    if (strstr(PHP_OS, "WIN")) {
        echo "[  Fatal ] Operating System is Windows.\n";
        exit(1);
    }
    $phpver = substr(phpversion(), 0, 3);
    if ($phpver < 5.3) {
        echo "[  Fatal ] PHP version {$phpver} < 5.3.\n";
        exit(1);
    }
    if (! extension_loaded("posix")) {
        echo "[  Error ] PHP Module Posix could not found.\n";
        $errCount ++;
    }
    if (! extension_loaded("pcntl")) {
        echo "[  Error ] PHP Module Pcntl could not found.\n";
        $errCount ++;
    }
    if (! extension_loaded("Phar")) {
        echo "[  Error ] PHP Module Phar could not found.\n";
        $errCount ++;
    }
    if (! extension_loaded("sockets")) {
        echo "[  Error ] PHP Module Sockets could not found.\n";
        $errCount ++;
    }
    if (! exec("git")) {
        echo "[  Error ] \"git\" command is not available.\n";
        $errCount ++;
    }
    $file = fopen("./.tmp", "a");
    if (! fputs($file, ".")) {
        echo "[  Error ] Writing failed.            \n";
        $errCount ++;
    }
    fclose($file);
    if (! file_get_contents("./.tmp")) {
        echo "[  Error ] Reading failed.            \n";
        $errCount ++;
    }
    unlink("./.tmp");
    // finish
    if ($errCount == 0) {
        echo "\r[   OK   ] Finished with no errors! Continue.\n";
    } else {
        echo "\r[  Error ] Finished with {$errCount} errors. Please fix them and try again.\n";
        exit(1);
    }
}

function allocatePorts ()
{
    global $masterport;
    $check = new PortChecker();
    while (true) {
        $masterport = rand(40000, 65535);
        echo "[" . date('Y-m-d H:i:s') .
                "][Main][Init][Info] Allocating port {$masterport} to master... ";
        if ($check->check("127.0.0.1", $masterport) == 1 &&
                $check->check("127.0.0.1", $masterport) == 0) {
            echo "failed.\n";
            continue;
        }
        echo "success.\n";
        break;
    }
}

function loadConfig ()
{
    global $CONFIG;
    if (! file_exists(getcwd() . "/config.php")) {
        echo "[" . date('Y-m-d H:i:s') .
                "][Main][Init][Info] Configration not found, create one.\n";
        file_put_contents(getcwd() . "/config.php",
                file_get_contents(__DIR__ . "/defaults/config.php"));
    }
    require_once getcwd() . "/config.php";
    $config = $CONFIG; // For older than PHP 7.0 versions
    if ($config["settings"]["mode"] == 1) {
        foreach ($config["workers"] as $arr) {
            setWorker($arr["addr"], $arr["remote"], $arr["processes"]);
        }
    } elseif (a) {
        foreach ($config["workers"] as $arr) {
            setWorker($arr["addr"], $arr["remote"], $arr["processes"], 2);
        }
    }
}

function setWorker ($listening, $remote, $workers, $mode = 1)
{
    global $workerid;
    $workerid = $workerid + 1;
    global $$workerid;
    echo "[" . date('Y-m-d H:i:s') .
            "][Main][Init][Info] Setting up Worker-{$workerid}...\n";
    if (! $listening or ! $remote or ! $workers) {
        echo "[" . date('Y-m-d H:i:s') .
                "][Main][Init][Error] Configuration is not vaild! While setting up Worker-{$workerid}!";
        echo "[" . date('Y-m-d H:i:s') .
                "][Main][Init][Error] Configuration of Worker-{$workerid} has failed!";
        return false;
    }
    echo "[" . date('Y-m-d H:i:s') .
            "][Main][Init][Debug] The settings of Worker-{$workerid} is:\n";
    echo "[" . date('Y-m-d H:i:s') .
            "][Main][Init][Debug] Worker count: {$workers}\n";
    echo "[" . date('Y-m-d H:i:s') .
            "][Main][Init][Debug] Forwarding: {$listening} -> {$remote}\n";
    $$workerid = new Worker($listening);
    $$workerid->count = $workers;
    $$workerid->name = "worker" . $workerid;
    $$workerid->listening = $listening;
    $$workerid->remote = $remote;
    $$workerid->proxyid = $workerid;
    $$workerid->onWorkerStart = array(
            new ProxyWorker(),
            'onWorkerStart'
    );
    $$workerid->onConnect = array(
            new ProxyWorker(),
            'onConnectMode' . $mode
    );
    echo "[" . date('Y-m-d H:i:s') .
            "][Main][Init][Info] Configuration of Worker-{$workerid} has completed.\n";
}


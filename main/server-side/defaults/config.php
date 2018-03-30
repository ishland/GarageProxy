<?php
//This is the configration of GarageProxy.
//Config format: setWorker(string $lisening, string $remote-server, int $worker-count)
//$listening and $remote-server: [Protocol]://[Address]:[Port]
//Example: setWorker("tcp://0.0.0.0:12345", "tcp://233.233.233.233:26777", 10);
//Warning: This version can only set one worker, or start fail.

setWorker("tcp://0.0.0.0:12345", "tcp://www.google.com:80", 6);

<?php
// This is the configration of GarageProxy.
$CONFIG = Array(
        'workers' => Array(
                Array(
                        'addr' => "tcp://0.0.0.0:12345",
                        'remote' => "tcp://www.baidu.com:443",
                        'processes' => 6
                ),
                Array(
                        'addr' => "tcp://0.0.0.0:12346",
                        'remote' => "tcp://minecraft.net:443",
                        'processes' => 6
                )
        ),
        'settings' => Array(
                'mode' => 1
        )
);

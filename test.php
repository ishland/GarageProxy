<?php
$content = file_get_contents('http://127.0.0.1:12345');
if (! $content) {
    system("php GarageProxyServer.phar stop");
    exit(1);
}
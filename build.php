<?php
mkdir("cache");
echo "Downloading workerman(changed)...\n";
system("git clone -b patch-1 https://github.com/ishland/Workerman.git cache");
echo "Done.\n";
echo "Building...\n";
copy("./start.php", "./cache/start.php");
$phar = new Phar('./GarageProxy.phar');
$phar->buildFromDirectory(__DIR__ . "/cache");
$phar->setDefaultStub('start.php', null);
echo "Done.\n";

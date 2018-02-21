<?php
mkdir("build");
$phar = new Phar('./build/GarageProxy.phar');
$phar->buildFromDirectory(__DIR__);
$phar->setDefaultStub('start.php', null);

#!/bin/sh
php build.php build normal
cp ./target/GarageProxyServer.phar ./test/
cd test
php GarageProxyServer.phar start -d
php GarageProxyServer.phar stop
cd ..

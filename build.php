#!/usr/bin/php
<?php
$process = 0;
$fileErrors = 0;
function process(){
    global $process;
    $process++;
    if($process == 5) $process = 1;
    if($process == 1) return "|";
    if($process == 2) return "/";
    if($process == 3) return "-";
    if($process == 4) return "\\";
}
function makephar($dir, $name, $default){
    @unlink($name);
    $phar = new Phar($name);
    if(!$phar) exit("[Fatal Error] Error while making phar. Please ensure that phar.readonly is disabled in php.ini.\n");
    $phar->buildFromDirectory($dir);
    $phar->setDefaultStub($default, null);
}
function checkEverything(){
    $errCount = 0;
    echo "\r[    " . process() . "   ] Checking Operating System...\r";
    if(!strstr(PHP_OS, "WIN")){
        echo "\r[   OK   ] Operating System is not Windows.\n";
    } else {
        echo "\r[  Fatal ] Operating System is Windows.\n";
        exit(1);
    }
    echo "\r[    " . process() . "   ] Checking PHP version...\r";
    $phpver = substr(phpversion(), 0, 3);
    if($phpver >= 5.3){
        echo "\r[   OK   ] PHP version {$phpver} >= 5.3.\n";
    } else {
        echo "\r[  Fatal ] PHP version {$phpver} < 5.3.\n";
        exit(1);
    }
    echo "\r[    " . process() . "   ] Checking required PHP Modules..." . process() . "\r";
    if(extension_loaded("posix")){
        echo "\r[   OK   ] PHP Module Posix currently installed and loaded.\n\r[    " . process() . "   ] Checking required PHP Modules...\r";
    } else {
        echo "\r[  Error ] PHP Module Posix could not found.\n\r[    " . process() . "   ] Checking required PHP Modules...\r";
        $errCount++;
    }
    if(extension_loaded("pcntl")){
        echo "\r[   OK   ] PHP Module Pcntl currently installed and loaded.\n\r[    " . process() . "   ] Checking required PHP Modules...\r";
    } else {
        echo "\r[  Error ] PHP Module Pcntl could not found.\n\r[    " . process() . "   ] Checking required PHP Modules...\r";
        $errCount++;
    }
    if(extension_loaded("Phar")){
        echo "\r[   OK   ] PHP Module Phar currently installed and loaded.\n\r[    " . process() . "   ] Checking required PHP Modules...\r";
    } else {
        echo "\r[  Error ] PHP Module Phar could not found.\n\r[    " . process() . "   ] Checking required PHP Modules...\r";
        $errCount++;
    }
    if(extension_loaded("sockets")){
        echo "\r[   OK   ] PHP Module Sockets currently installed and loaded.\n";
    } else {
        echo "\r[  Error ] PHP Module Sockets could not found.\n";
        $errCount++;
    }
    echo "\r[    " . process() . "   ] Checking \"git\" command...\r";
    if(exec("git")){
        echo "\r[   OK   ] \"git\" command is available.\n";
    } else {
        echo "\r[  Error ] \"git\" command is not available.\n";
        $errCount++;
    }
    echo "\r[    " . process() . "   ] Checking writing...\r";
    $i = 10000;
    while($i > 0){
        $file = fopen("./.tmp", "a");
        if(!$file) break;
        fputs($file, ".");
        fclose($file);
        $i--;
        $per = (10000 - $i) / 10000 * 100;
        echo "\r[    " . process() . "   ] Checking writing... {$per}%\r";
    }
    if($i == 0){
        echo "\r[   OK   ] Writing successfully.      \n";
    } else {
        echo "\r[  Error ] Writing failed.            \n";
        $errCount++;
    }
    echo "\r[    " . process() . "   ] Checking reading...\r";
    $i = 10000;
    while($i > 0){
        file_get_contents("./.tmp");
        $i--;
        $per = (10000 - $i) / 10000 * 100;
        echo "\r[    " . process() . "   ] Checking reading... {$per}%\r";
    }
    if($i == 0){
        echo "\r[   OK   ] Reading successfully.      \n";
    } else {
        echo "\r[  Error ] Reading failed.            \n";
        $errCount++;
    }
    unlink("./.tmp");
    //finish
    if($errCount == 0){
        echo "\r[   OK   ] Finished with no errors! Continue.\n";
    } else {
        echo "\r[  Error ] Finished with {$errCount} errors. Please fix them and try again.\n";
        exit(1);
    }
}
function checkFiles($src = "./main"){
    global $fileErrors;
    $dir = opendir($src);
    while(($file = readdir($dir)) !== false) { 
        if(($file != '.') && ($file != '..')) { 
            if(is_dir($src . '/' . $file)) { 
                checkFiles($src . '/' . $file); 
            } else { 
                $result = exec("php -l " . $src . '/' . $file);
                if(strchr($result, "Errors parsing")){
                    $fileErrors++;
                    echo "[Error] Parsing error, the script will exit.\n";
                }
                echo $result . "\n";
            } 
        } 
    } 
   closedir($dir);
}
function copydir($src, $dst){ 
    //echo "Copying dir\t{$src}...\n";
    $dir = opendir($src);
    @mkdir($dst);
    while(($file = readdir($dir)) !== false) { 
        if(($file != '.') && ($file != '..')) { 
            if(is_dir($src . '/' . $file)) { 
                copydir($src . '/' . $file, $dst . '/' . $file); 
            } else { 
                //echo "Copying file\t" . $src . '/' . $file . "\n";
                copy($src . '/' . $file, $dst . '/' . $file); 
            } 
        } 
    } 
   closedir($dir); 
}
function copyfile($src, $dst){
    //echo "Copying file\t{$src}\n";
    copy($src, $dst);
    //echo "\n";
}
function deldir($dir){
    //echo "Deleting dir\t{$dir}\n";
    if(!is_dir($dir)) return;
    if(count(scandir($dir))==2){rmdir($dir);return;}
    $dh = opendir($dir);
    while($file=readdir($dh)) {
        if($file != '.' && $file != '..') {
            $fullpath = $dir . "/" . $file;
            if(is_file($fullpath)) {
                //echo "Deleting file\t{$fullpath}\n";
                unlink($fullpath);
            } 
            if(is_dir($fullpath)){
                if(count(scandir($fullpath))==2){
                    rmdir($fullpath);
                } else {
                    deldir($fullpath);
                }
            }
        }
    }
 
    closedir($dh);
    if(rmdir($dir)) {
        return true;
    } else {
        return false;
    }
}
$usage = "Usage: php build.php <command> <args>\nCommands:\nbuild\tBuild this project.\n\tArgs:\n\tnormal\tNormal build. (Delete cached files and download it.)\n\tcached\tUse cached files to build.\n";
if($argv[1] == "build"){
    if(!$argv[2]){
        echo $usage;
        exit(1);
    }
    echo "Checking everything...\n";
    checkEverything();
    switch($argv[2]){
        case "normal":
        @mkdir("cache");
        echo "Downloading workerman...\n";
        deldir("./cache");
        system("git clone https://github.com/walkor/Workerman.git cache");
        echo "Done.\n";
        case "cached":
        echo "Checking if there are some grammer errors.\n";
        checkFiles();
        if($fileErrors > 0) exit(2);
        echo "Deleting the last build files...\n";
        @mkdir("tmp");
        deldir("./tmp");
        echo "Building...\n";
        echo "Building server side...\n";
        @mkdir("tmp");
        echo "Copying files...\n";
        copydir("./cache", "./tmp");
        copydir("./main/server-side", "./tmp");
        echo "Making phar file...\n";
        @mkdir("target");
        makephar(__DIR__ . "/tmp", "./target/GarageProxyServer.phar", "launcher.php");
        echo "Done.\n";
        echo "Building client side...\n";
        deldir("./tmp");
        @mkdir("tmp");
        echo "Copying files...\n";
        copydir("./cache", "./tmp");
        copydir("./main/client-side", "./tmp");
        echo "Making phar file...\n";
        makephar(__DIR__ . "/tmp", "./target/GarageProxyClient.phar", "start.php");
        deldir("./tmp");
        echo "Done.\n";
        exit(0);
    }
}
echo $usage;
exit(1);

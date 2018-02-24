#!/usr/bin/php
<?php
$process = 0;
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
    $phar = new Phar($name);
    if(!$phar) exit("[Fatal Error] Error while making phar. Please ensure that phar.readonly is disabled in php.ini.\n");
    $phar->buildFromDirectory($dir);
    $phar->setDefaultStub($default, null);
}
function checkEverything(){
    $errCount = 0;
    echo "\r[    " . process() . "   ]Checking Operating System...\r";
    if(!strstr(PHP_OS, "WIN")){
        echo "\r[   OK   ] Operating System is not Windows.\n";
    } else {
        echo "\r[  Fatal ] Operating System is Windows.\n";
        exit(-1);
    }
    echo "\r[    " . process() . "   ]Checking PHP version...\r";
    $phpver = substr(phpversion(), 0, 3);
    if($phpver >= 5.3){
        echo "\r[   OK   ] PHP version {$phpver} >= 5.3.\n";
    } else {
        echo "\r[  Fatal ] PHP version {$phpver} < 5.3.\n";
        exit(-1);
    }
    echo "\r[    " . process() . "   ]Checking required PHP Modules..." . process() . "\r";
    if(extension_loaded("posix")){
        echo "\r[   OK   ] PHP Module Posix currently installed and loaded.\n\r[    " . process() . "   ]Checking required PHP Modules...\r";
    } else {
        echo "\r[  Error ] PHP Module Posix could not found.\n\r[    " . process() . "   ]Checking required PHP Modules...\r";
        $errCount++;
    }
    if(extension_loaded("pcntl")){
        echo "\r[   OK   ] PHP Module Pcntl currently installed and loaded.\n\r[    " . process() . "   ]Checking required PHP Modules...\r";
    } else {
        echo "\r[  Error ] PHP Module Pcntl could not found.\n\r[    " . process() . "   ]Checking required PHP Modules...\r";
        $errCount++;
    }
    if(extension_loaded("Phar")){
        echo "\r[   OK   ] PHP Module Phar currently installed and loaded.\n\r[    " . process() . "   ]Checking required PHP Modules...\r";
    } else {
        echo "\r[  Error ] PHP Module Phar could not found.\n\r[    " . process() . "   ]Checking required PHP Modules...\r";
        $errCount++;
    }
    if(extension_loaded("sockets")){
        echo "\r[   OK   ] PHP Module Sockets currently installed and loaded.\n";
    } else {
        echo "\r[  Error ] PHP Module Sockets could not found.\n";
        $errCount++;
    }
    echo "\r[    " . process() . "   ]Checking \"git\" command...\r";
    if(exec("git")){
        echo "\r[   OK   ] \"git\" command is available.\n";
    } else {
        echo "\r[  Error ] \"git\" command is not available.\n";
        $errCount++;
    }
    echo "\r[    " . process() . "   ]Checking writing...\r";
    $i = 10000;
    while($i > 0){
        $file = fopen("./.tmp", "a");
        if(!$file) break;
        fputs($file, ".");
        fclose($file);
        $i--;
        $per = (10000 - $i) / 10000 * 100;
        echo "\r[    " . process() . "   ]Checking writing... {$per}%\r";
    }
    if($i == 0){
        echo "\r[   OK   ] Writing sucessfully.     \n";
    } else {
        echo "\r[  Error ] Writing failed.          \n";
        $errCount++;
    }
    echo "\r[    " . process() . "   ]Checking reading...\r";
    $i = 10000;
    while($i > 0){
        file_get_contents("./.tmp");
        $i--;
        $per = (10000 - $i) / 10000 * 100;
        echo "\r[    " . process() . "   ]Checking reading... {$per}%\r";
    }
    if($i == 0){
        echo "\r[   OK   ] Reading sucessfully.     \n";
    } else {
        echo "\r[  Error ] Reading failed.          \n";
        $errCount++;
    }
    //finish
    if($errCount == 0){
        echo "\r[   OK   ]Finished with no errors! Continue.\n";
    } else {
        echo "\r[  Error ]Finished with {$errCount} errors. Please fix them and try again.\n";
        exit(-2);
    }
}
function copydir($src, $dst){ 
    echo "Copying\t{$src}...";
    $dir = opendir($src);
    @mkdir($dst);
    while(($file = readdir($dir)) !== false) { 
        if(($file != '.') && ($file != '..')) { 
            if(is_dir($src . '/' . $file)) { 
                copydir($src . '/' . $file, $dst . '/' . $file); 
            } else { 
                echo "Copying\t" . $src . '/' . $file . "\n";
                copy($src . '/' . $file, $dst . '/' . $file); 
            } 
        } 
    } 
  closedir($dir); 
} 
function copyfile($src, $dst){
  echo "Copying\t{$src}\n";
  copy($src, $dst);
  echo "\n";
}
function deldir($dir){
  echo "Deleting dir\t{$dir}\n";
  if(!is_dir($dir)) return;
  if(count(scandir($dir))==2){rmdir($dir);return;}
  $dh = opendir($dir);
  while($file=readdir($dh)) {
    if($file != '.' && $file != '..') {
      $fullpath = $dir . "/" . $file;
      if(is_file($fullpath)) {
          echo "Deleting file\t{$fullpath}\n";
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
    if(!$argv[2]) exit($usage);
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
        echo "Building...\n";
        echo "Copying files...\n";
        copy("./start.php", "./cache/start.php");
        copydir("./defaults", "./cache/defaults");
        echo "Making phar file...\n";
        makephar(__DIR__ . "/cache", "./GarageProxy.phar", "start.php");
        echo "Done.\n";
        exit;
    }
}
exit($usage);

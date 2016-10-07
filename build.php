<?php

if (file_exists(__DIR__ . '/vendor/erusev/parsedown/Parsedown.php')) {
    require __DIR__ . '/vendor/erusev/parsedown/Parsedown.php';
} else {
    die("Please do 'composer install' first!\n");
}

$pharName = "e621BRS";
$srcRoot = "src";
$buildRoot = "build";

if (file_exists($buildRoot . '/' . $pharName . '.phar')) {
    unlink($buildRoot . '/' . $pharName . '.phar');
}

$phar = new Phar($buildRoot . "/" . $pharName . ".phar", FilesystemIterator::CURRENT_AS_FILEINFO, $pharName . ".phar");

$phar->buildFromDirectory($srcRoot, '/.php$/');
$phar->setStub($phar->createDefaultStub("run.php"));

if (file_exists($srcRoot . "/config.cfg.example")) {
    copy($srcRoot . "/config.cfg.example", $buildRoot . "/config.cfg.example");
}

if (file_exists($srcRoot . "/run.bat")) {
    copy($srcRoot . "/run.bat", $buildRoot . "/run.bat");
}

if (file_exists($srcRoot . "/run.sh")) {
    copy($srcRoot . "/run.sh", $buildRoot . "/run.sh");
}

$Parsedown = new Parsedown();
file_put_contents($buildRoot . "/README.html", $Parsedown->text(file_get_contents(__DIR__ . '/README.md')));
file_put_contents($buildRoot . "/LICENSE.html", $Parsedown->text(file_get_contents(__DIR__ . '/LICENSE.md')));
file_put_contents($buildRoot . "/CONTRIBUTING.html", $Parsedown->text(file_get_contents(__DIR__ . '/CONTRIBUTING.md')));


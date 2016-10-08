<?php

$pharName = "e621BRS";
$srcRoot = realpath("src");
$buildRoot = realpath("build");

if (ini_get("phar.readonly") == 0) {
    echo "Building...\n";

    echo " Cleaning up...\n";
    if (file_exists($buildRoot . '/' . $pharName . '.phar')) {
        unlink($buildRoot . '/' . $pharName . '.phar');
    }

    $phar = new Phar($buildRoot . "/" . $pharName . ".phar", FilesystemIterator::CURRENT_AS_FILEINFO, $pharName . ".phar");

    echo " Building phar...\n";
    $phar->buildFromDirectory($srcRoot, '/.php$/');
    $phar->setStub($phar->createDefaultStub("run.php"));

    echo " Copying additional files...\n";

    if (file_exists($srcRoot . "/config.cfg.example")) {
        copy($srcRoot . "/config.cfg.example", $buildRoot . "/config.cfg.example");
    }

    if (file_exists($srcRoot . "/run.bat")) {
        copy($srcRoot . "/run.bat", $buildRoot . "/run.bat");
    }

    if (file_exists($srcRoot . "/run.sh")) {
        copy($srcRoot . "/run.sh", $buildRoot . "/run.sh");
    }

    if (file_exists(__DIR__ . '/vendor/erusev/parsedown/Parsedown.php')) {
        echo " Converting markdown files into HTML...\n";
        require __DIR__ . '/vendor/erusev/parsedown/Parsedown.php';

        $Parsedown = new Parsedown();
        file_put_contents($buildRoot . "/README.html", $Parsedown->text(file_get_contents(__DIR__ . '/README.md')));
        file_put_contents($buildRoot . "/LICENSE.html", $Parsedown->text(file_get_contents(__DIR__ . '/LICENSE.md')));
        file_put_contents($buildRoot . "/CONTRIBUTING.html", $Parsedown->text(file_get_contents(__DIR__ . '/CONTRIBUTING.md')));
    } else {
        echo("! Can't parse markdown files into HTML, dependencies not installed - do 'composer install'!\n");
    }

    echo "Done!\n\n";
} else {
    echo "! Can't build - 'phar.readonly' is 'On', check php.ini!\n\n";
}

if (class_exists('ZipArchive')) {
    if (file_exists(__DIR__ . "/build_template.zip")) {
        echo "Packing...\n";

        echo " Cleaning up...\n";
        if (file_exists(__DIR__ . '/build.zip')) {
            unlink(__DIR__ . '/build.zip');
        }

        echo " Copying template...\n";
        copy(__DIR__ . "/build_template.zip", __DIR__ . "/build.zip");

        $zip = new ZipArchive;

        $ignored = ['.', '..', '.gitkeep', 'build_template.zip', 'images', 'logs', 'runtime', 'config.cfg', 'debug_error.txt'];

        if ($zip->open(__DIR__ . "/build.zip") === TRUE) {
            if ($handle = opendir($buildRoot)) {
                while (false !== ($entry = readdir($handle))) {
                    if (!in_array($entry, $ignored)) {
                        echo " Adding '$entry'...\n";
                        $zip->addFile($buildRoot . '/' . $entry, $entry);
                    }
                }
            }

            $zip->close();
        }

        echo "Done!\n\n";
    } else {
        echo "! Can't pack build, no build_template.zip found!\n\n";
    }
} else {
    echo "! Can't pack build, 'php-zip' package not found!\n\n";
}

echo "Finished!\n\n";

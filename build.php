<?php

$pharName = "e621BRS";
$srcRoot = realpath("src");
$buildRoot = realpath("build");

$buildFile = 'build/e621_Batch_Reverse_Search.zip';
$ignoredFiles = ['.', '..', '.gitkeep', 'images', 'logs', 'config.cfg', 'debug_error.txt', 'build.zip', 'e621_Batch_Reverse_Search.zip', '.updatecheck'];

if (ini_get("phar.readonly") == 0) {
    echo "Building...\n";

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

        $license = $Parsedown->text(file_get_contents(__DIR__ . '/LICENSE'));
        $readme = $Parsedown->text(file_get_contents(__DIR__ . '/README.md'));
        $contributing = $Parsedown->text(file_get_contents(__DIR__ . '/CONTRIBUTING.md'));

        $readme = str_replace("https://github.com/jacklul/e621-Batch-Reverse-Search/blob/master/CONTRIBUTING.md", "CONTRIBUTING.html", $readme);
        $readme = str_replace("https://github.com/jacklul/e621-Batch-Reverse-Search/blob/master/LICENSE", "LICENSE.html", $readme);
        $contributing = str_replace("https://github.com/jacklul/e621-Batch-Reverse-Search/blob/master/README.md", "README.html", $contributing);

        file_put_contents($buildRoot . "/README.html", $readme);
        file_put_contents($buildRoot . "/LICENSE.html", $license);
        file_put_contents($buildRoot . "/CONTRIBUTING.html", $contributing);
    } else {
        echo("! Can't parse markdown files into HTML, dependencies not installed - do 'composer install'!\n");
    }

    echo "Done!\n\n";
} else {
    echo "! Can't build - 'phar.readonly' is 'On', check php.ini!\n\n";
}

if (class_exists('ZipArchive')) {
    if (is_dir($buildRoot)) {
        echo "Packing...\n";

        if (file_exists($buildFile)) {
            unlink($buildFile);
        }

        $dirName = basename($buildRoot);

        $zip = new ZipArchive();
        $zip->open($buildFile, ZipArchive::CREATE);

        if (substr($dirName, -1) != '/') {
            $dirName .= '/';
        }

        $dirStack = array($dirName);
        $cutFrom = strrpos($dirName, '/') + 1;

        while (!empty($dirStack)) {
            $currentDir = array_pop($dirStack);
            $filesToAdd = array();

            $dir = dir($currentDir);
            while (false !== ($node = $dir->read())) {
                if (in_array($node, $ignoredFiles)) {
                    continue;
                }
                if (is_dir($currentDir . $node)) {
                    array_push($dirStack, $currentDir . $node . '/');
                }
                if (is_file($currentDir . $node)) {
                    $filesToAdd[] = $node;
                }
            }

            $localDir = substr($currentDir, $cutFrom);
            $zip->addEmptyDir($localDir);

            foreach ($filesToAdd as $file) {
                echo " '$currentDir$file'...\n";
                $zip->addFile($currentDir . $file, $localDir . $file);
            }
        }

        $zip->close();

        echo "Done!\n\n";
    } else {
        echo "! Build directory does not exist!\n\n";
    }
} else {
    echo "! Can't pack build, 'php-zip' package not found!\n\n";
}

echo "Finished!\n\n";

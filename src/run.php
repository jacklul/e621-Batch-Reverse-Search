<?php

require_once("app.php");

/**
 * Run the script
 */
try {
    $app = new jacklul\e621BRS\App();

    if (isset($argv[1])) {
        $app->setImagesPath($argv[1]);
    }

    if (file_exists(realpath("config.cfg"))) {
        $app->readConfig(realpath("config.cfg"));
    }

    $app->run();
} catch (\Exception $e) {
    print("\n" . $e . "\n\n");
}

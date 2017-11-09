<?php
/**
 * e621 Batch Reverse Search Script
 *
 * Copyright (c) 2016 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed
 * with this source code.
 */

/**
 * Include the script
 */
require_once("app.php");

/**
 * Run it!
 */
try {
    $app = new jacklul\e621BRS\App(isset($argv[1]) ? $argv[1] : null);
    $app->run();
} catch (\Exception $e) {
    print("\n" . $e . "\n\n");
}

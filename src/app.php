<?php
/**
 * e621 Batch Reverse Search Script
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed
 * with this source code.
 */

namespace jacklul\e621BRS;

define("ROOT", dirname(str_replace("phar://", "", __DIR__)));

/**
 * Class App
 */
class App
{
    /**
     * App Name
     *
     * @var string
     */
    private $NAME = 'e621 Batch Reverse Search';

    /**
     * App Version
     *
     * @var string
     */
    private $VERSION = '1.8.0';

    /**
     * App update URL
     *
     * @var string
     */
    private $UPDATE_URL = 'https://api.github.com/repos/jacklul/e621-Batch-Reverse-Search/releases/latest';

    /**
     * User-agent for curl requests
     *
     * @var string
     */
    private $USER_AGENT = "e621 Batch Reverse Search (https://github.com/jacklul/e621-Batch-Reverse-Search)";

    /**
     * Set debug mode on or off
     *
     * @var bool
     */
    private $DEBUG = false;

    /**
     * Path to images directory
     *
     * @var string
     */
    private $PATH_IMAGES = ROOT . '/images/';

    /**
     * Path to found directory
     *
     * @var string
     */
    private $PATH_IMAGES_FOUND = ROOT . '/images/found/';

    /**
     * Path to 'not found' directory
     *
     * @var string
     */
    private $PATH_IMAGES_NOT_FOUND = ROOT . '/images/not found/';

    /**
     * Log console output to a file or not
     *
     * @var bool
     */
    private $LOGGING = false;

    /**
     * Path to logs directory
     *
     * @var string
     */
    private $PATH_LOGS = ROOT . '/logs/';

    /**
     * Output links to HTML file or not
     *
     * @var bool
     */
    private $OUTPUT_HTML = true;

    /**
     * Output to HTML destination file
     *
     * @var string
     */
    private $OUTPUT_HTML_FILE = ROOT . '/images/found/links.html';

    /**
     * MD5 search is enabled or not
     *
     * @var bool
     */
    private $MD5_SEARCH = true;

    /**
     * Batch MD5 search is enabled or not
     *
     * @var bool
     */
    private $MD5_BATCH_SEARCH = true;

    /**
     * Reverse search is enabled or not
     *
     * @var bool
     */
    private $REVERSE_SEARCH = true;

    /**
     * Convert image into JPEG with '90' quality before uploading
     *
     * @var bool
     */
    private $USE_CONVERSION = true;

    /**
     * e621 username
     *
     * @var bool
     */
    private $E621_LOGIN = '';

    /**
     * e621 API KEY
     *
     * @var bool
     */
    private $E621_API_KEY = '';

    /**
     * Use additional services for reverse search
     *
     * @var bool
     */
    private $USE_MULTI_SEARCH = true;

    /**
     * Forces searching on all services even when links are already found
     *
     * @var bool
     */
    private $FORCE_MULTI_SEARCH = false;

    /**
     * API key for 'saucenao.com' service
     *
     * @var string
     */
    private $SAUCENAO_API_KEY = '';

    /**
     * API key for 'fuzzysearch.net' service
     *
     * @var string
     */
    private $FUZZYSEARCH_API_KEY = '';

    /**
     * Is the main loop running? (for signal handler)
     *
     * @var bool
     */
    private $IS_RUNNING = false;

    /**
     * Is the script being run on Linux?
     *
     * @var string
     */
    private $IS_LINUX = false;

    /**
     * Script start time
     *
     * @var int
     */
    private $START_TIME = null;

    /**
     * Log name format (set on first log output)
     *
     * @var string
     */
    private $LOG_NAME = '';

    /**
     * Is custom path set (argument)
     *
     * @var bool
     */
    private $CUSTOM_PATH = false;

    /**
     * Line buffer (for download progress handler)
     *
     * @var string
     */
    private $LINE_BUFFER = '';

    /**
     * cURL return buffer
     *
     * @var string
     */
    private $RETURN_BUFFER = '';

    /**
     * cURL return timeout
     *
     * @var string
     */
    private $RETURN_TIMEOUT = 60;

    /**
     * Cache for MD5 search
     *
     * @var string
     */
    private $MD5_CACHE = [];

    /**
     * App constructor
     *
     * @param string $arg
     */
    public function __construct($arg = null)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        error_reporting(E_ERROR);

        if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
            $this->IS_LINUX = true;
        }

        if (!extension_loaded('curl')) {
            if ($this->IS_LINUX) {
                die("Required package 'php-curl' not found!\n");
            } else {
                die("'php_curl.dll' extension is not loaded!\n");
            }
        }

        if (!extension_loaded('openssl')) {
            if ($this->IS_LINUX) {
                die("Required package 'php-openssl' not found!\n");
            } else {
                die("'php_openssl.dll' extension is not loaded!\n");
            }
        }

        if (!extension_loaded('fileinfo')) {
            if ($this->IS_LINUX) {
                die("Required package 'php-fileinfo' not found!\n");
            } else {
                die("'php_fileinfo.dll' extension is not loaded!\n");
            }
        }

        if ($this->IS_LINUX && function_exists('pcntl_signal')) {
            declare(ticks=1);
            pcntl_signal(SIGINT, [$this, 'interruptHandler']);
        }

        $short_opts = "p::c::vu";
        $long_opts = ["path::", "config::", "version", "update"];

        $options = getopt($short_opts, $long_opts);

        if (isset($options['v']) || isset($options['version'])) {
            $this->showVersion();
        }

        if (isset($options['u']) || isset($options['update'])) {
            $this->showUpdater();
        }

        if (isset($options['p'])) {
            $options['path'] = $options['p'];
        }

        if (isset($options['path'])) {
            $this->setImagesPath($options['path']);
        }

        if (isset($options['c'])) {
            $options['config'] = $options['c'];
        }

        if (isset($options['config'])) {
            $this->readConfig(realpath($options['config']));
        } elseif (file_exists(ROOT . "/config.cfg")) {
            $this->readConfig(ROOT . "/config.cfg");
        }

        if (empty($options) && isset($arg)) {
            $this->setImagesPath($arg);
        }

        $this->START_TIME = microtime(true);
    }

    /**
     * Just show version...
     */
    private function showVersion()
    {
        die($this->NAME . ' v' . $this->VERSION . "\n");
    }

    /**
     * Just show updater...
     */
    private function showUpdater()
    {
        $this->updater();
        die();
    }

    /**
     * Updater
     */
    private function updater()
    {
        if (!empty($this->UPDATE_URL)) {
            $updatecheckfile = ROOT . '/.updatecheck';

            if (!file_exists($updatecheckfile) || filemtime($updatecheckfile) + 300 < time()) {
                touch($updatecheckfile);

                if (!$this->IS_LINUX) {
                    exec('attrib +H "' . $updatecheckfile . '"');
                }

                $this->printout("Checking for updates...");

                $ch = curl_init($this->UPDATE_URL);
                curl_setopt($ch, CURLOPT_USERAGENT, $this->USER_AGENT);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                /** @noinspection CurlSslServerSpoofingInspection */
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                /** @noinspection CurlSslServerSpoofingInspection */
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

                $update_check = curl_exec($ch);
                curl_close($ch);

                if (!empty($update_check)) {
                    $update_check = json_decode($update_check, true);
                }

                $REMOTE_VERSION = $update_check['tag_name'];
                $REMOTE_DOWNLOAD = $update_check['assets'][0]['browser_download_url'];

                if ($REMOTE_VERSION !== "" && !empty($REMOTE_DOWNLOAD)) {
                    $update_file = ROOT . "/update.zip";
                    $update_file_html = ROOT . "/update.html";

                    if (version_compare($this->VERSION, $REMOTE_VERSION, '<')) {
                        $this->printout(" update available (v" . $REMOTE_VERSION . ")\n");
                        $this->printout("For changelog check https://github.com/jacklul/e621-Batch-Reverse-Search/releases\n\n");

                        $this->printout("Do you wish to update now? [Y]es*/[N]o: ");

                        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                            $line = stream_get_line(STDIN, 1024, PHP_EOL);
                        } else {
                            $line = readline('');
                        }

                        if (strtolower($line) != "n" && strtolower($line) != "no") {
                            $this->printout("\n");

                            $this->LINE_BUFFER = "Downloading update package...";
                            $this->printout($this->LINE_BUFFER);

                            $ch = curl_init($REMOTE_DOWNLOAD);
                            curl_setopt($ch, CURLOPT_USERAGENT, $this->USER_AGENT);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                            /** @noinspection CurlSslServerSpoofingInspection */
                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                            /** @noinspection CurlSslServerSpoofingInspection */
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
                            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'cURLProgress']);

                            $output = curl_exec($ch);
                            curl_close($ch);

                            file_put_contents($update_file, $output);

                            print("\r" . $this->LINE_BUFFER);

                            if ($fh = @fopen($update_file, "r")) {
                                $blob = fgets($fh, 5);
                                fclose($fh);
                            }

                            if (isset($blob) && strpos($blob, 'PK') !== false) {
                                $this->printout(" done!\n");
                                $this->printout("Unpacking...");

                                $zip = false;
                                if (class_exists("ZipArchive")) {
                                    $zip = new \ZipArchive;
                                }

                                if ($zip && $zip->open($update_file) === true) {
                                    $zip->extractTo(ROOT);
                                    $zip->close();
                                    $this->printout(" done!\n\n");
                                    unlink($update_file);

                                    $this->printout("Restart the script to use the new version!\n\n");
                                } else {
                                    $this->printout(" failed\n\n");
                                    $this->printout("Extract 'update.zip' manually.\n\n");
                                }
                            } else {
                                $this->printout(" failed!\n\n");

                                if (file_exists($update_file) && filesize($update_file) <= 1) {
                                    unlink($update_file);
                                }

                                file_put_contents($update_file_html, '<meta http-equiv="refresh" content="0; url=' . $REMOTE_DOWNLOAD . '">Redirecting to <a href="' . $REMOTE_DOWNLOAD . '">' . $REMOTE_DOWNLOAD . '</a>...');

                                $this->printout("Open 'update.html' in a web browser to download the update, then extract it manually.\n\n");
                            }

                            exit();
                        }
                    } else {
                        $this->printout(" up to date!\n");

                        if (file_exists($update_file)) {
                            unlink($update_file);
                        }

                        if (file_exists($update_file_html)) {
                            unlink($update_file_html);
                        }
                    }
                } else {
                    $this->printout(" failed!\n");
                }
            }
        }
    }

    /**
     * Log / Output function
     *
     * @param $text
     */
    private function printout($text)
    {
        print $text;

        if ($this->LOGGING) {
            if (empty($this->LOG_NAME)) {
                $this->LOG_NAME = basename(__FILE__, '.php') . '_' . date("Ymd\_His");
            }

            file_put_contents($this->PATH_LOGS . '/' . $this->LOG_NAME . '.log', $text, FILE_APPEND);
        }
    }

    /**
     * Set 'images' path
     *
     * @param string $path
     */
    private function setImagesPath($path)
    {
        if (!empty($path)) {
            $this->CUSTOM_PATH = true;
            $this->PATH_IMAGES = realpath($path);

            if (!$this->PATH_IMAGES) {
                die("Path is not valid: " . $path . "\n");
            }

            $this->PATH_IMAGES_FOUND = $this->PATH_IMAGES . '/found/';
            $this->PATH_IMAGES_NOT_FOUND = $this->PATH_IMAGES . '/not found/';
            $this->OUTPUT_HTML_FILE = $this->PATH_IMAGES_FOUND . '/links.html';
        }
    }

    /**
     * Load config file if it exists
     *
     * @param string $file
     */
    public function readConfig($file)
    {
        if (file_exists($file)) {
            $config = parse_ini_file($file);

            if (isset($config['DEBUG'])) {
                $this->DEBUG = $config['DEBUG'];
            }

            if (isset($config['LOGGING'])) {
                $this->LOGGING = $config['LOGGING'];
            }

            if (isset($config['PATH_LOGS'])) {
                $this->PATH_LOGS = $config['PATH_LOGS'];
            }

            if (isset($config['PATH_IMAGES']) && !$this->CUSTOM_PATH) {
                $this->PATH_IMAGES = $config['PATH_IMAGES'];
                $this->PATH_IMAGES_FOUND = $this->PATH_IMAGES . '/found/';
                $this->PATH_IMAGES_NOT_FOUND = $this->PATH_IMAGES . '/not found/';
                $this->OUTPUT_HTML_FILE = $this->PATH_IMAGES_FOUND . '/links.html';
            }

            if (isset($config['OUTPUT_HTML'])) {
                $this->OUTPUT_HTML = $config['OUTPUT_HTML'];
            }

            if (isset($config['OUTPUT_HTML_FILE'])) {
                $this->OUTPUT_HTML_FILE = (bool)$config['OUTPUT_HTML_FILE'];
            }

            if (isset($config['MD5_SEARCH'])) {
                $this->MD5_SEARCH = (bool)$config['MD5_SEARCH'];
            }

            if (isset($config['MD5_BATCH_SEARCH'])) {
                $this->MD5_BATCH_SEARCH = (bool)$config['MD5_BATCH_SEARCH'];
            }

            if (isset($config['REVERSE_SEARCH'])) {
                $this->REVERSE_SEARCH = (bool)$config['REVERSE_SEARCH'];
            }

            if (!$this->REVERSE_SEARCH && !$this->MD5_SEARCH) {
                die("No search method set, check config!\n\n");
            }

            if (isset($config['USE_CONVERSION'])) {
                $this->USE_CONVERSION = (bool)$config['USE_CONVERSION'];
            }

            if (isset($config['E621_LOGIN'])) {
                $this->E621_LOGIN = $config['E621_LOGIN'];
            }

            if (isset($config['E621_API_KEY'])) {
                $this->E621_API_KEY = $config['E621_API_KEY'];
            }

            if (isset($config['USE_MULTI_SEARCH'])) {
                $this->USE_MULTI_SEARCH = (bool)$config['USE_MULTI_SEARCH'];
            }

            if (isset($config['FORCE_MULTI_SEARCH'])) {
                $this->FORCE_MULTI_SEARCH = (bool)$config['FORCE_MULTI_SEARCH'];
            }

            if (isset($config['SAUCENAO_API_KEY'])) {
                $this->SAUCENAO_API_KEY = $config['SAUCENAO_API_KEY'];
            }

            if (isset($config['FUZZYSEARCH_API_KEY'])) {
                $this->FUZZYSEARCH_API_KEY = $config['FUZZYSEARCH_API_KEY'];
            }

            if (isset($config['RETURN_TIMEOUT'])) {
                $this->RETURN_TIMEOUT = $config['RETURN_TIMEOUT'];
            }
        }
    }

    /**
     * Pre-main function
     */
    public function run()
    {
        $this->PATH_IMAGES = str_replace("//", "/", $this->PATH_IMAGES);

        if (!$this->CUSTOM_PATH && !is_dir($this->PATH_IMAGES)) {
            /** @noinspection MkdirRaceConditionInspection */
            mkdir($this->PATH_IMAGES);
        }

        $this->PATH_IMAGES_FOUND = str_replace("//", "/", $this->PATH_IMAGES_FOUND);

        if (!is_dir($this->PATH_IMAGES_FOUND)) {
            /** @noinspection MkdirRaceConditionInspection */
            mkdir($this->PATH_IMAGES_FOUND);
        }

        $this->PATH_IMAGES_NOT_FOUND = str_replace("//", "/", $this->PATH_IMAGES_NOT_FOUND);

        if (!is_dir($this->PATH_IMAGES_NOT_FOUND)) {
            /** @noinspection MkdirRaceConditionInspection */
            mkdir($this->PATH_IMAGES_NOT_FOUND);
        }

        $this->PATH_LOGS = str_replace("//", "/", $this->PATH_LOGS);

        if ($this->LOGGING && !is_dir($this->PATH_LOGS)) {
            /** @noinspection MkdirRaceConditionInspection */
            mkdir($this->PATH_LOGS);
        }

        $this->OUTPUT_HTML_FILE = str_replace("//", "/", $this->OUTPUT_HTML_FILE);

        if ($this->OUTPUT_HTML && !is_file($this->OUTPUT_HTML_FILE)) {
            touch($this->OUTPUT_HTML_FILE);
        }

        if ($this->DEBUG) {
            error_reporting(E_ALL);
            $this->printout("ROOT = " . ROOT . "\n");
            $this->printout("PATH_IMAGES = " . $this->PATH_IMAGES . "\n");
            $this->printout("PATH_IMAGES_FOUND = " . $this->PATH_IMAGES_FOUND . "\n");
            $this->printout("PATH_IMAGES_NOT_FOUND = " . $this->PATH_IMAGES_NOT_FOUND . "\n");
            $this->printout("PATH_LOGS = " . $this->PATH_LOGS . "\n");
            $this->printout("OUTPUT_HTML_FILE = " . $this->OUTPUT_HTML_FILE . "\n");
            $this->printout("RETURN_TIMEOUT = " . $this->RETURN_TIMEOUT . "\n");
        }

        $this->showASCIISplash();

        if (!extension_loaded('zip')) {
            if ($this->IS_LINUX) {
                $this->printout("WARNING: 'php-zip' package not found - update packages will not be extracted automatically!\n\n");
            } else {
                $this->printout("WARNING: 'php_zip.dll' extension not loaded - update packages will not be extracted automatically!\n\n");
            }
        }

        if (!extension_loaded('gd')) {
            if ($this->IS_LINUX) {
                $this->printout("WARNING: 'php-gd' package not found - conversion to JPEG will be disabled!\n\n");
            } else {
                $this->printout("WARNING: 'php_gd2.dll' extension not loaded - conversion to JPEG will be disabled!\n\n");
            }

            $this->USE_CONVERSION = false;
        }

        $this->updater();

        $this->printout("\n");

        if ($this->CUSTOM_PATH) {
            $this->printout("Using path: " . $this->PATH_IMAGES . "\n\n");
        }

        $this->main();

        exit;
    }

    /**
     * Just show ASCII splash...
     */
    private function showASCIISplash()
    {
        print '        __ ___  __   ____        _       _
       / /|__ \/_ | |  _ \      | |     | |    v' . $this->VERSION . (($this->DEBUG) ? " DEBUG MODE" : '') . '
  ___ / /_   ) || | | |_) | __ _| |_ ___| |__
 / _ \ \'_ \ / / | | |  _ < / _` | __/ __| \'_ \    Created by Jack\'lul
|  __/ (_) / /_ | | | |_) | (_| | || (__| | | |       jacklul.github.io
 \___|\___/____||_| |____/ \__,_|\__\___|_| |_|
 _____                                 _____                     _
|  __ \                               / ____|                   | |
| |__) |_____   _____ _ __ ___  ___  | (___   ___  __ _ _ __ ___| |__
|  _  // _ \ \ / / _ \ \'__/ __|/ _ \  \___ \ / _ \/ _` | \'__/ __| \'_ \
| | \ \  __/\ | /  __/ |  \__ \  __/  ____) |  __/ (_| | | | (__| | | |
|_|  \_\___| \_/ \___|_|  |___/\___| |_____/ \___|\__,_|_|  \___|_| |_|

';
    }

    /**
     * Main function
     */
    private function main()
    {
        $files = [];
        $files_error = [];
        $files_count = 0;
        $files_total = 0;
        $files_found = 0;

        if (is_dir($this->PATH_IMAGES)) {
            $this->printout("Scanning for images...");

            if ($handle = opendir($this->PATH_IMAGES)) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != ".." && $entry != "desktop.ini" && !is_dir($this->PATH_IMAGES . '/' . $entry)) {
                        $file_size = filesize($this->PATH_IMAGES . '/' . $entry);
                        $image_size = getimagesize($this->PATH_IMAGES . '/' . $entry);

                        if (urlencode($entry) != $entry && (float)PHP_VERSION < 7.2) {
                            $files_error['encoding'] = true;
                        } elseif (!in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif'])) {
                            $files_error['file_type'] = true;
                        } elseif (!$this->USE_CONVERSION && $file_size > 8388608) {
                            $files_error['file_size'] = true;
                        } elseif ($image_size[0] > 7500 || $image_size[1] > 7500) {
                            $files_error['image_size'] = true;
                        } else {
                            $files_total++;
                            $files[] = $entry;
                        }
                    }
                }
            }

            $this->printout(' ' . $files_total . " found!\n");

            if (!empty($files_error)) {
                $this->printout("\n");
            }

            if (isset($files_error['encoding'])) {
                $this->printout("WARNING: Some files contained UTF-8 characters in their names and were ignored, please use at least PHP 7.2 to support them!\n");
            }

            if (isset($files_error['file_type'])) {
                $this->printout("WARNING: Some files were not a supported image files (JPEG, PNG and GIF) and were ignored!\n");
            }

            if (isset($files_error['file_size'])) {
                $this->printout("WARNING: Some files exceeded 8192 KB file size limit and were ignored!\n");
            }

            if (isset($files_error['image_size'])) {
                $this->printout("WARNING: Some files exceeded 7500x7500 image dimensions limit and were ignored!\n");
            }

            $this->printout("\n");
            $this->IS_RUNNING = true;

            if ($this->MD5_SEARCH && $this->MD5_BATCH_SEARCH && count($files) > 0) {
                $this->LINE_BUFFER = "Batch MD5 search...";
                $this->printout($this->LINE_BUFFER . "\r");

                $this->MD5_CACHE = [];

                $page = 1;
                $perPage = 100;
                $totalPages = ceil(count($files) / $perPage);

                while (true) {
                    $new_files = array_slice($files, $perPage * ($page - 1), $perPage);

                    if (count($new_files) === 0) {
                        break;
                    }

                    $this->printout("\r");
                    $this->printout($this->LINE_BUFFER . ' ' . $page . '/' . $totalPages);
                    $page++;

                    $md5_list = '';
                    foreach ($new_files as $entry) {
                        if (!empty($md5_list)) {
                            $md5_list .= ',';
                        }

                        $md5_file = md5_file($this->PATH_IMAGES . '/' . $entry);
                        $this->MD5_CACHE[$md5_file] = [];
                        $md5_list .= $md5_file;
                    }

                    $raw = $this->apiRequest('md5:' . $md5_list, 1, 100);
                    $results = json_decode($raw, true);

                    if (isset($results['posts']) && count($results['posts']) > 0) {
                        foreach ($results['posts'] as $post) {
                            $this->MD5_CACHE[$post['file']['md5']] = $post['id'];
                        }
                    }
                }

                $this->printout("\r" . $this->LINE_BUFFER . " done\n");
                $this->printout("\n");
            }

            foreach ($files as $entry) {
                if ($this->IS_RUNNING) {
                    $files_count++;
                    $this->printout('[' . ($files_count) . "/$files_total] Searching '" . $entry . "':\n");

                    $results = null;
                    if ($this->MD5_SEARCH) {
                        $service = 'e621.net';

                        $this->LINE_BUFFER = " Trying md5 sum...";
                        $this->printout($this->LINE_BUFFER);

                        $md5_file = md5_file($this->PATH_IMAGES . '/' . $entry);
                        if ($this->MD5_BATCH_SEARCH && isset($this->MD5_CACHE[$md5_file])) {
                            $results = [];

                            if (is_numeric($this->MD5_CACHE[$md5_file])) {
                                $results[0]['id'] = $this->MD5_CACHE[$md5_file];
                            }
                        } else {
                            $raw = $this->apiRequest('md5:' . $md5_file);
                            $results = json_decode($raw, true);

                            if (isset($results['posts']) && count($results['posts']) > 0) {
                                $results = $results['posts'];
                            }
                        }

                        print("\r" . $this->LINE_BUFFER);
                    }

                    if (isset($results[0]['id'])) {
                        $results[0] = 'https://e621.net/post/show/' . $results[0]['id'];
                    } else {
                        if ($this->MD5_SEARCH) {
                            if (is_array($results)) {
                                $this->printout(" no matching posts found!\n");

                                if (!$this->REVERSE_SEARCH) {
                                    $this->safeRename($this->PATH_IMAGES . '/' . $entry, $this->PATH_IMAGES_NOT_FOUND . '/' . $entry);
                                }
                            } else {
                                $this->printout(" failed!\n");
                            }
                        }

                        if ($this->REVERSE_SEARCH) {
                            $service = 'e621.net/iqdb_queries';

                            if ($this->USE_MULTI_SEARCH) {
                                $this->LINE_BUFFER = " Trying reverse search #1 (e621.net/iqdb_queries)...";
                            } else {
                                $this->LINE_BUFFER = " Trying reverse search...";
                            }

                            $this->printout($this->LINE_BUFFER);

                            $results = $this->reverseSearch($this->PATH_IMAGES . '/' . $entry);

                            if (isset($results['error']) && $results['error'] === 'ShortLimitReached') {
                                $results = $this->applyCooldown(
                                    1,
                                    $results,
                                    function () use ($entry) {
                                        return $this->reverseSearch($this->PATH_IMAGES . '/' . $entry);
                                    }
                                );
                            }

                            print("\r" . $this->LINE_BUFFER);

                            if ($this->USE_MULTI_SEARCH) {
                                if (!empty($this->FUZZYSEARCH_API_KEY) && (!is_array($results) || isset($results['error']) || $this->FORCE_MULTI_SEARCH)) {
                                    if ($this->FORCE_MULTI_SEARCH) {
                                        $results_prev = null;
                                        if (is_array($results) && count($results) > 0 && !isset($results['error'])) {
                                            $results_prev = $results;
                                            $this->printout(" success!\n");
                                        }

                                        $service_prev = $service;
                                    }

                                    $service = 'fuzzysearch.net';

                                    if (isset($results['error']) || !is_array($results)) {
                                        $this->parseError(is_array($results) ? $results['error'] : null);
                                    }

                                    $this->LINE_BUFFER = " Trying reverse search #2 (fuzzysearch.net)...";
                                    $this->printout($this->LINE_BUFFER);

                                    $results = $this->reverseSearchFuzzySearch($this->PATH_IMAGES . '/' . $entry);

                                    if ($this->FORCE_MULTI_SEARCH) {
                                        if (isset($results_prev) && isset($service_prev) && $results_prev !== null) {
                                            /** @noinspection SlowArrayOperationsInLoopInspection */
                                            $results = array_merge($results_prev, $results);
                                            $results = array_unique($results);
                                            $service = $service_prev . ', ' . $service;
                                        }
                                    }

                                    print("\r" . $this->LINE_BUFFER);
                                }

                                if (!empty($this->SAUCENAO_API_KEY) && (!is_array($results) || isset($results['error']) || $this->FORCE_MULTI_SEARCH)) {
                                    if ($this->FORCE_MULTI_SEARCH) {
                                        $results_prev = null;
                                        if (is_array($results) && count($results) > 0 && !isset($results['error'])) {
                                            $results_prev = $results;
                                            $this->printout(" success!\n");
                                        }

                                        $service_prev = $service;
                                    }

                                    $service = 'saucenao.com';

                                    if (isset($results['error']) || !is_array($results)) {
                                        $this->parseError(is_array($results) ? $results['error'] : null);
                                    }

                                    $this->LINE_BUFFER = " Trying reverse search #2 (saucenao.com)...";
                                    $this->printout($this->LINE_BUFFER);

                                    $results = $this->reverseSearchSaucenao($this->PATH_IMAGES . '/' . $entry);

                                    if (isset($results['error']) && $results['error'] === 'ShortLimitReached') {
                                        $results = $this->applyCooldown(
                                            30,
                                            $results,
                                            function () use ($entry) {
                                                return $this->reverseSearchSaucenao($this->PATH_IMAGES . '/' . $entry);
                                            }
                                        );
                                    }

                                    if ($this->FORCE_MULTI_SEARCH) {
                                        if (isset($results_prev) && isset($service_prev) && $results_prev !== null) {
                                            /** @noinspection SlowArrayOperationsInLoopInspection */
                                            $results = array_merge($results_prev, $results);
                                            $results = array_unique($results);
                                            $service = $service_prev . ', ' . $service;
                                        }
                                    }

                                    print("\r" . $this->LINE_BUFFER);
                                }
                            }
                        }
                    }

                    if (is_array($results)) {
                        $results = array_unique($results);
                    }

                    if (isset($results['error'])) {
                        $this->parseError(is_array($results) ? $results['error'] : null);

                        if ($results['error'] == 'NoResults') {
                            $this->safeRename($this->PATH_IMAGES . '/' . $entry, $this->PATH_IMAGES_NOT_FOUND . '/' . $entry);
                        }
                    } elseif (is_array($results) && count($results) > 0 && !isset($results['posts'])) {
                        $this->printout(" success!\n");

                        $files_found++;
                        $html_output = '';
                        $results_text = '';
                        for ($i = 0, $iMax = count($results); $i < $iMax; $i++) {
                            if (empty($results[$i])) {
                                continue;
                            }

                            $results_text .= '  ' . $results[$i] . "\n";

                            if ($this->OUTPUT_HTML) {
                                $html_output .= '&nbsp;<a href="' . $results[$i] . '" target="_blank">' . $results[$i] . '</a>' . " ($service)\n<br>\n";
                            }
                        }

                        $this->printout($results_text);

                        if ($this->OUTPUT_HTML) {
                            if (!file_exists($this->OUTPUT_HTML_FILE) || filesize($this->OUTPUT_HTML_FILE) == 0) {
                                $javascript = "<script src=\"https://code.jquery.com/jquery-3.3.1.min.js\"></script>
<script>
    window.onload = function() {
        if (window.jQuery) {
            $(document).ready(function () {
                var yOff = 5;
                var xOff = 5;

                $(\".text-hover-image\").hover(function (e) {
                        $(\"body\").append(\"<p id='image-when-hovering-text'><img src='\" + $(this).html() + \"' id='image-when-hovering-image'/></p>\");
                        $(\"#image-when-hovering-text\")
                            .css(\"position\", \"absolute\")
                            .css(\"top\", (e.pageY - yOff) + \"px\")
                            .css(\"left\", (e.pageX + xOff) + \"px\")
                            .fadeIn(\"fast\");
                        $(\"#image-when-hovering-image\")
                            .css(\"max-width\", ($(window).height() / 2) + \"px\")
                            .css(\"max-height\", ($(window).width() / 2) + \"px\");
                    },

                    function () {
                        $(\"#image-when-hovering-text\").remove();
                    });

                $(\".text-hover-image\").mousemove(function (e) {
                    $(\"#image-when-hovering-text\")
                        .css(\"top\", (e.pageY - yOff) + \"px\")
                        .css(\"left\", (e.pageX + xOff) + \"px\");
                });
            });
        } else {
            alert(\"Unable to load jQuery from remote server, image popups will be unavailable!\");
        }
    };
</script>
<style>
    html {
        padding-bottom: 800px;
    }
</style>";

                                file_put_contents($this->OUTPUT_HTML_FILE, "<html>\n<head>\n<meta charset=\"UTF-8\">\n<title>Post Links (e621 Batch Reverse Search)</title>\n$javascript\n</head>\n<body>\n<h4>Generated by " . $this->NAME . "</h4>\n", LOCK_EX);
                            }

                            if (!empty($html_output)) {
                                file_put_contents($this->OUTPUT_HTML_FILE, "<b class=\"text-hover-image\">" . ($entry) . "</b>:\n<br>\n$html_output<br>\n", FILE_APPEND | LOCK_EX);
                            }
                        } else {
                            file_put_contents($this->PATH_IMAGES_FOUND . '/' . $entry . '.txt', "  " . trim(strip_tags($results_text)) . "\n");
                        }

                        $this->safeRename($this->PATH_IMAGES . '/' . $entry, $this->PATH_IMAGES_FOUND . '/' . $entry);
                    } elseif ($this->REVERSE_SEARCH) {
                        $this->printout(" failed!\n");

                        if ($this->LOGGING) {
                            $date = date("Ymd\_His");
                            file_put_contents($this->PATH_LOGS . '/raw_result_' . $date . '.html', $results);
                            $this->printout("\nWARNING: Unhandled error occurred! See '" . realpath($this->PATH_LOGS . '/raw_result_' . $date . '.html') . "' for service reply.\n");
                        } else {
                            $this->printout("\nWARNING: Unhandled error occurred! Turn on LOGGING to see more details!\n");
                        }
                    }

                    $this->printout("\n");
                } else {
                    $this->printout("WARNING: Interrupted by user!\n\n");
                    break;
                }
            }

            if ($files_total > 0) {
                $this->printout("Found links for " . $files_found . "/" . $files_total . " images.\n");
            }

            $this->printout("Finished in " . round(microtime(true) - $this->START_TIME, 3) . " seconds.\n");

            closedir($handle);
        } else {
            die("Path '" . $this->PATH_IMAGES . "' is invalid, check config!\n");
        }
    }

    /**
     * Make a query to e621 API
     *
     * @param string $tags
     * @param int    $page
     * @param int    $limit
     * @param bool   $retry
     *
     * @return string|bool
     */
    private function apiRequest($tags, $page = 1, $limit = 1, $retry = true)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://e621.net/posts.json?limit=' . $limit . '&page=' . $page . '&tags=' . $tags);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->USER_AGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        /** @noinspection CurlSslServerSpoofingInspection */
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->RETURN_TIMEOUT);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'cURLProgress']);

        $output = curl_exec($ch);

        if ($this->DEBUG) {
            print "\nOUTPUT:\n" . $output . "\n";
        }

        if ($retry) {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http_code === 429) {
                print "\r";
                return $this->applyCooldown(
                    1,
                    ['error' => 'ShortLimitReached'],
                    function () use ($tags, $page, $limit) {
                        return $this->apiRequest($tags, $page, $limit, false);
                    }
                );
            }
        }

        return $output;
    }

    /**
     * @param int      $delay
     * @param array    $results
     * @param callable $callable
     *
     * @return callable
     */
    private function applyCooldown($delay = 60, array $results, callable $callable)
    {
        $this->printout($this->LINE_BUFFER);
        $this->parseError(is_array($results) ? $results['error'] : null);

        $this->printout(' Waiting ' . $delay . ' seconds...');
        sleep($delay);

        return $callable();
    }

    /**
     * Parse error name
     *
     * @param $error
     */
    private function parseError($error)
    {
        if ($error == 'NoResults') {
            $this->printout(" no matching images found!\n");
        } elseif ($error == 'NotImage') {
            $this->printout(" not a valid image!\n");
        } elseif ($error == 'EmptyResult') {
            $this->printout(" no reply from the server!\n");
        } elseif ($error == 'UploadError') {
            $this->printout(" upload error!\n");
        } elseif ($error == 'NotResource') {
            $this->printout(" conversion failed or image is corrupted!\n");
        } elseif ($error == 'ShortLimitReached') {
            $this->printout(" too many requests!\n");
        } elseif ($error == 'LimitReached') {
            $this->printout(" exceeded daily search limit!\n");
        } elseif ($error == 'FailedLimitReached') {
            $this->printout(" too many failed search attempts!\n");
        } elseif (!empty($error)) {
            $this->printout(" error: " . $error . "\n");
        } else {
            $this->printout(" empty response!\n");
        }
    }

    /**
     * Prevent overwriting files
     *
     * @param string $from
     * @param string $to
     *
     * @return bool
     */
    private function safeRename($from, $to)
    {
        if (!file_exists($to)) {
            return rename($from, $to);
        }

        $this->printout("\nWARNING: Couldn't move the file because it already exists in destination directory!\n");

        return false;
    }

    /**
     * Perform reverse search using e621's iqdb
     *
     * @param string $file
     *
     * @return array|string|bool
     */
    private function reverseSearch($file)
    {
        if (empty($this->E621_LOGIN) && empty($this->E621_API_KEY)) {
            return ['error' => 'Authentication required, check configuration!'];
        }

        $post_data = [];

        if ($this->USE_CONVERSION) {
            $contents = $this->convertImage($file);
            if (is_array($contents) && isset($contents['error'])) {
                return $contents;
            }

            $file_data['file'] = $contents;
        } else {
            $post_data['file'] = new \CurlFile($file, mime_content_type($file), basename($file));
        }

        if (!empty($this->RETURN_BUFFER)) {
            $this->RETURN_BUFFER = '';
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://e621.net/iqdb_queries.json");
        curl_setopt($ch, CURLOPT_USERAGENT, $this->USER_AGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        /** @noinspection CurlSslServerSpoofingInspection */
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->RETURN_TIMEOUT);
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, $this->RETURN_TIMEOUT);

        if (isset($file_data)) {
            $this->buildMultiPartRequest($ch, uniqid('', true), $post_data, $file_data);
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }

        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'cURLProgress']);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, [$this, 'cURLRead']);
        curl_setopt($ch, CURLOPT_USERPWD, $this->E621_LOGIN . ":" . $this->E621_API_KEY);

        $output = curl_exec($ch);

        if (!empty($this->RETURN_BUFFER)) {
            $output = $this->RETURN_BUFFER;
        }

        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($this->DEBUG && $http_status !== 200) {
            print "\nStatus Code: " . $http_status . "\n";
        }

        if ($this->DEBUG) {
            print "\nOUTPUT:\n" . $output . "\n";
        }

        $json_result = json_decode($output, true);

        if (is_array($json_result)) {
            if (isset($json_result['posts'])) {
                $json_result = $json_result['posts'];
            }

            if (count($json_result) === 0) {
                return ['error' => 'NoResults'];
            }

            if (count($json_result) > 0 && isset($json_result[0]['post_id'])) {
                $search_results = [];
                foreach ($json_result as $result) {
                    $search_results[] = 'https://e621.net/posts/' . $result['post_id'];
                }

                return $search_results;
            }
        }

        if ($http_status === 429) {
            return ['error' => 'ShortLimitReached'];
        }

        if (empty($output)) {
            return ['error' => 'EmptyResult'];
        }

        if (isset($json_result['message'])) {
            return ['error' => $json_result['message']];
        }

        return $output;
    }

    /**
     * @param $file
     *
     * @return array|false|resource|string|null
     */
    private function convertImage($file)
    {
        $mime_type = mime_content_type($file);
        $image = $this->readImage($file, $mime_type);

        if (isset($image) && is_resource($image)) {
            ob_start();
            imagejpeg($image, NULL, 90);
            $contents = ob_get_clean();

            return $contents;
        }

        if (is_array($image) && isset($image['error'])) {
            return $image;
        }

        return ['error' => 'NotResource'];
    }

    /**
     * Read the image and create image resource object
     *
     * @param $file
     * @param $type
     *
     * @return array|null|resource
     */
    private function readImage($file, $type)
    {
        try {
            if ($type == 'image/png') {
                if ($this->IS_LINUX) {      // https://stackoverflow.com/q/45936271
                    $output = `php -r "imagecreatefrompng('$file');" 2>&1`;

                    if (!empty($output)) {
                        return null;
                    }
                }

                $image = imagecreatefrompng($file);
            } elseif ($type == 'image/jpeg') {
                $image = imagecreatefromjpeg($file);
            } elseif ($type == 'image/gif') {
                $image = imagecreatefromgif($file);
            } else {
                return null;
            }
        } catch (\Throwable $e) {
            return ['error' => $e];
        }

        return $image;
    }

    /**
     * https://gist.github.com/iansltx/a6ed41d19852adf2e496
     *
     * @param $ch
     * @param $boundary
     * @param $fields
     * @param $files
     *
     * @return mixed
     */
    private function buildMultiPartRequest($ch, $boundary, $fields, $files, $headers = [])
    {
        $delimiter = '-------------' . $boundary;
        $data = '';

        foreach ($fields as $name => $content) {
            $data .= "--" . $delimiter . "\r\n"
                . 'Content-Disposition: form-data; name="' . $name . "\"\r\n\r\n"
                . $content . "\r\n";
        }
        foreach ($files as $name => $content) {
            $data .= "--" . $delimiter . "\r\n"
                . 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $name . '"' . "\r\n\r\n"
                . $content . "\r\n";
        }

        $data .= "--" . $delimiter . "--\r\n";

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array_merge([
                'Content-Type: multipart/form-data; boundary=' . $delimiter,
                'Content-Length: ' . strlen($data),
            ], $headers),
            CURLOPT_POSTFIELDS => $data
        ]);

        return $ch;
    }

    /**
     * Perform reverse search using saucenao.com
     *
     * @param string $file
     *
     * @return array|string|bool
     */
    private function reverseSearchSaucenao($file)
    {
        $post_data = [];

        if ($this->USE_CONVERSION) {
            $contents = $this->convertImage($file);
            if (is_array($contents) && isset($contents['error'])) {
                return $contents;
            }

            $file_data['file'] = $contents;
        } else {
            $post_data['file'] = new \CurlFile($file, mime_content_type($file), basename($file));
        }

        $post_data['url'] = '';
        $post_data['frame'] = '1';
        $post_data['hide'] = '0';
        $post_data['numres'] = '10';
        $post_data['db'] = '999';

        if (!empty($this->SAUCENAO_API_KEY)) {
            $post_data['api_key'] = $this->SAUCENAO_API_KEY;
        }

        $post_data['output_type'] = 2;

        if (!empty($this->RETURN_BUFFER)) {
            $this->RETURN_BUFFER = '';
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://saucenao.com/search.php?db=999");
        curl_setopt($ch, CURLOPT_USERAGENT, $this->USER_AGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        /** @noinspection CurlSslServerSpoofingInspection */
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->RETURN_TIMEOUT);
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, $this->RETURN_TIMEOUT);

        if (isset($file_data)) {
            $this->buildMultiPartRequest($ch, uniqid('', true), $post_data, $file_data);
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }

        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'cURLProgress']);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, [$this, 'cURLRead']);

        $output = curl_exec($ch);

        if (!empty($this->RETURN_BUFFER)) {
            $output = $this->RETURN_BUFFER;
        }

        if ($this->DEBUG) {
            print "\nOUTPUT:\n" . $output . "\n";
        }

        if (empty($output)) {
            return ['error' => 'EmptyResult'];
        }

        $result = json_decode($output, true);

        $matches = [];
        if (isset($result['header']['status'])) {
            if ($result['header']['status'] === 0) {
                foreach ($result['results'] as $this_result) {
                    if (isset($this_result['data']['ext_urls']) && (float)$this_result['header']['similarity'] >= 55) {
                        $matches[] = $this_result['data']['ext_urls'][0];
                    }
                }

                if (count($matches) === 0) {
                    return ['error' => 'NoResults'];
                }

                return $matches;
            }

            if (isset($result['header']['message'])) {
                if (strpos($result['header']['message'], 'Search Rate Too High') !== false) {
                    return ['error' => 'ShortLimitReached'];
                }

                if (strpos($result['header']['message'], 'Daily Search Limit Exceeded') !== false) {
                    return ['error' => 'LimitReached'];
                }

                if (strpos($result['header']['message'], 'Too many failed search attempts') !== false) {
                    return ['error' => 'FailedLimitReached'];
                }
            }
        } elseif (strpos($output, 'You need an Image') !== false) {
            return ['error' => 'NotImage'];
        } elseif (strpos($output, 'Specified file does not seem to be an image') !== false) {
            return ['error' => 'NotImage'];
        } elseif (strpos($output, 'Low similarity results have been hidden.') !== false) {
            return ['error' => 'NoResults'];
        }

        return $output;
    }

    /**
     * Perform reverse search using fuzzysearch.net
     *
     * @param string $file
     *
     * @return array|string|bool
     */
    private function reverseSearchFuzzySearch($file)
    {
        $post_data = [];

        if ($this->USE_CONVERSION) {
            $contents = $this->convertImage($file);
            if (is_array($contents) && isset($contents['error'])) {
                return $contents;
            }

            $file_data['image'] = $contents;
        } else {
            $file_data['image'] = file_get_contents($file);
            $post_data['file'] = new \CurlFile($file, mime_content_type($file), basename($file));
        }

        if (!empty($this->RETURN_BUFFER)) {
            $this->RETURN_BUFFER = '';
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.fuzzysearch.net/image?type=close");
        curl_setopt($ch, CURLOPT_USERAGENT, $this->USER_AGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        /** @noinspection CurlSslServerSpoofingInspection */
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->RETURN_TIMEOUT);
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, $this->RETURN_TIMEOUT);

        $headers = [];
        if (!empty($this->FUZZYSEARCH_API_KEY)) {
            $headers = ['X-API-Key: ' . $this->FUZZYSEARCH_API_KEY];
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . $this->FUZZYSEARCH_API_KEY]);
        }

        if (isset($file_data)) {
            $this->buildMultiPartRequest($ch, uniqid('', true), $post_data, $file_data, $headers);
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }

        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'cURLProgress']);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, [$this, 'cURLRead']);

        $output = curl_exec($ch);

        if (!empty($this->RETURN_BUFFER)) {
            $output = $this->RETURN_BUFFER;
        }

        if ($this->DEBUG) {
            print "\nOUTPUT:\n" . $output . "\n";
        }

        if (empty($output)) {
            return ['error' => 'EmptyResult'];
        }

        $result = json_decode($output, true);

        $matches = [];
        if (isset($result['matches']) && count($result['matches']) > 0) {
            foreach ($result['matches'] as $this_result) {
                switch (strtolower($this_result['site'])) {
                    case 'furaffinity':
                        $match_url = 'https://furaffinity.net/view/' . $this_result['site_id'];
                        break;
                    case 'e621':
                        $match_url = 'https://e621.net/posts/' . $this_result['site_id'];
                        break;
                    case 'twitter':
                        $match_url = 'https://twitter.com/' . $this_result['artists'][0] . '/status/' . $this_result['site_id'];
                        break;
                    case 'weasyl':
                        $match_url = 'https://www.weasyl.com/submission/' . $this_result['site_id'];
                        break;
                    default:
                        $match_url = $this_result['url'] . '(' . $this_result['site'] . ', ID = ' . $this_result['site_id'] . ')';
                }

                $matches[] = $match_url;
            }

            return $matches;
        } elseif (isset($result['message'])) {
            return ['error' => $result['message']];
        }

        if (count($matches) === 0) {
            return ['error' => 'NoResults'];
        }

        return $output;
    }

    /**
     * Interrupt handler (CTRL-C)
     *  (Linux only)
     *
     * @noinspection PhpUnusedParameterInspection
     *
     * @param $signo
     */
    private function interruptHandler($signo = null)
    {
        if ($this->IS_RUNNING) {
            $this->IS_RUNNING = false;
        } else {
            print("\r\n\n");
            exit;
        }
    }

    /**
     * cURL progress callback
     *
     * @param $resource
     * @param $download_size
     * @param $downloaded
     * @param $upload_size
     * @param $uploaded
     */
    private function cURLProgress($resource = null, $download_size = 0, $downloaded = 0, $upload_size = 0, $uploaded = 0)
    {
        $total = 0;
        $progress = 0;

        /* fallback for different cURL version which does not use $resource parameter */
        if (is_numeric($resource)) {
            $uploaded = $upload_size;
            $upload_size = $downloaded;
            $downloaded = $download_size;
            $download_size = $resource;
        }

        if ($download_size > 0) {
            $total = $download_size;
            $progress = $downloaded;
        } elseif ($upload_size > 0) {
            $total = $upload_size;
            $progress = $uploaded;
        }

        if ($total > 0) {
            print(str_repeat(' ', strlen($this->LINE_BUFFER) + 15) . "\r" . $this->LINE_BUFFER . ' ' . round(($progress * 100) / $total, 0)) . "%     \r";
        }

        usleep(100);
    }

    /**
     * Continous reading of the result
     *
     * @noinspection PhpUnusedParameterInspection
     *
     * @param $resource
     * @param $string
     *
     * @return int
     */
    private function cURLRead($resource, $string)
    {
        $length = strlen($string);
        $this->RETURN_BUFFER .= $string;

        if (strpos($string, 'Improbable match') !== false || (strpos($string, 'Probable match:') !== false && strpos($string, 'Other results') !== false)) {
            return 0;
        }

        return $length;
    }
}

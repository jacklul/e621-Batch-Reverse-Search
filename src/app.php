<?php
/**
 * e621 Batch Reverse Search Script
 *
 * Copyright (c) 2016 Jack'lul <https://jacklul.com>
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
class App {
    /**
     * App Version
     *
     * @var string
     */
    private $VERSION = '1.0.1';

    /**
     * App Name
     *
     * @var string
     */
    private $NAME = 'e621 Batch Reverse Search';

    /**
     * Update URL
     *
     * @var string
     */
    private $UPDATE_URL = 'https://api.github.com/repos/jacklul/e621-Batch-Reverse-Search/releases/latest';

    /**
     * Debug mode switch
     *
     * @var bool
     */
    private $DEBUG = false;

    /**
     * Logging switch
     *
     * @var bool
     */
    private $LOGGING = true;

    /**
     * MD5 search is enabled or not
     *
     * @var bool
     */
    private $MD5_SEARCH = true;

    /**
     * Reverse search is enabled or not
     *
     * @var bool
     */
    private $REVERSE_SEARCH = true;

    /**
     * Use php-wfio extension or not
     *  (https://github.com/kenjiuno/php-wfio)
     *
     * @var bool
     */
    private $USE_PHPWFIO = true;

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
     * Path to logs directory
     *
     * @var string
     */
    private $PATH_LOGS = ROOT . '/logs/';

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
     * Is custom path set (via script argument)
     *
     * @var bool
     */
    private $CUSTOM_PATH = false;

    /**
     * Script start time
     *
     * @var int
     */
    private $START_TIME = 0;

    /**
     * Log format
     *
     * @var string
     */
    private $LOG_NAME = '';

    /**
     * Line buffer (for download progress)
     *
     * @var string
     */
    private $OUTPUT_BUFFER = '';

    /**
     * App constructor
     */
    public function __construct()
    {
        if (!function_exists('curl_version')) {
            die("'php-curl' package not found!\n");
        }

        set_time_limit(0);
        error_reporting(E_ERROR);
        date_default_timezone_set(date_default_timezone_get());

        $this->START_TIME = microtime(true);
    }

    /**
     * Log / Output function
     *
     * @param $text
     */
    private function printout($text)
    {
        print $text;

        if (empty($this->LOG_NAME)) {
            $this->LOG_NAME = basename(__FILE__, '.php') . '_' . date("Ymd\_His");
        }

        if ($this->LOGGING) {
            file_put_contents($this->PATH_LOGS . '/' . $this->LOG_NAME . '.log', $text, FILE_APPEND);
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
            print (str_repeat(' ', 10) . "\r" . $this->OUTPUT_BUFFER . ' ' . round(($progress * 100) / $total, 0)) . "%";
        }

        usleep(100);
    }

    /**
     * Set 'images' path
     *
     * @param string $path
     */
    public function setImagesPath($path)
    {
        if (!empty($path)) {
            $this->CUSTOM_PATH = true;
            $this->PATH_IMAGES = $path;

            if (!is_dir($this->PATH_IMAGES)) {
                die("Path is not valid: " . $this->PATH_IMAGES . "\n\n");
            }
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
                $this->PATH_LOGS = realpath($config['PATH_LOGS']);
            }

            if (isset($config['PATH_IMAGES']) && !$this->CUSTOM_PATH) {
                $this->PATH_IMAGES = realpath($config['PATH_IMAGES']);
            }

            if (isset($config['MD5_SEARCH'])) {
                $this->MD5_SEARCH = $config['MD5_SEARCH'];
            }

            if (isset($config['REVERSE_SEARCH'])) {
                $this->REVERSE_SEARCH = $config['REVERSE_SEARCH'];
            }

            if (isset($config['OUTPUT_HTML'])) {
                $this->OUTPUT_HTML = $config['OUTPUT_HTML'];
            }

            if (isset($config['OUTPUT_HTML_FILE'])) {
                $this->OUTPUT_HTML_FILE = realpath(dirname($config['OUTPUT_HTML_FILE'])) . '/' . basename($config['OUTPUT_HTML_FILE']);
            } else {
                $this->OUTPUT_HTML_FILE = $this->PATH_IMAGES_FOUND . '/links.html';
            }

            if (isset($config['USE_PHPWFIO'])) {
                $this->USE_PHPWFIO = $config['USE_PHPWFIO'];
            }

            if (!$this->REVERSE_SEARCH && !$this->MD5_SEARCH) {
                die("No search method set, check config!\n\n");
            }
        }
    }

    private function showASCIISplash()
    {
        print '        __ ___  __   ____        _       _
       / /|__ \/_ | |  _ \      | |     | |    v' . $this->VERSION . (($this->DEBUG) ?  " DEBUG MODE":'') . '
  ___ / /_   ) || | | |_) | __ _| |_ ___| |__
 / _ \ \'_ \ / / | | |  _ < / _` | __/ __| \'_ \    Created by Jack\'lul
|  __/ (_) / /_ | | | |_) | (_| | || (__| | | |       jacklul.com
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
     * Updater
     */
    private function updater()
    {
        if (!empty($this->UPDATE_URL)) {
            $this->printout("Checking for updates...");

            $ch = curl_init($this->UPDATE_URL);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->NAME);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
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

                    $this->printout("Do you wish to update now? [Y]es*/[N]o: ");

                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                        $line = stream_get_line(STDIN, 1024, PHP_EOL);
                    } else {
                        $line = readline('');
                    }

                    if (strtolower($line) != "n" && strtolower($line) != "no") {
                        $this->printout("\n");

                        $this->OUTPUT_BUFFER = "Downloading update package...";
                        $this->printout($this->OUTPUT_BUFFER);

                        $ch = curl_init($REMOTE_DOWNLOAD);
                        curl_setopt($ch, CURLOPT_USERAGENT, $this->NAME);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
                        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'cURLProgress']);

                        $output = curl_exec($ch);
                        curl_close($ch);

                        file_put_contents($update_file, $output);

                        print("\r" . $this->OUTPUT_BUFFER);

                        if ($fh = @fopen($update_file, "r")) {
                            $blob = fgets($fh, 5);
                            fclose($fh);
                        }

                        if (isset($blob) && strpos($blob, 'PK') !== false) {
                            $this->printout(" done!\n");
                            $this->printout("Unpacking...");

                            if (class_exists("ZipArchive")) {
                                $zip = new \ZipArchive;
                            }

                            if (is_object($zip) && $zip->open($update_file) === true) {
                                $zip->extractTo(ROOT);
                                $zip->close();
                                $this->printout(" done!\n\n");
                                unlink($update_file);

                                $this->printout("Restart the script to use the new version!\n\n");
                            } else {
                                $this->printout(" failed\n\n");
                                $this->printout("Extract 'update.zip' manually to update.\n\n");
                            }
                        } else {
                            $this->printout(" failed!\n\n");

                            if (file_exists($update_file) && filesize($update_file) <= 1) {
                                unlink($update_file);
                            }

                            file_put_contents($update_file_html, '<meta http-equiv="refresh" content="0; url=' . $REMOTE_DOWNLOAD . '">Redirecting to <a href="' . $REMOTE_DOWNLOAD . '">' . $REMOTE_DOWNLOAD . '</a>...');

                            $this->printout("Open 'update.html' in a web browser to download the update, then extract it.\n\n");
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

            $this->printout("\n");
        }
    }

    /**
     * Pre-main function
     */
    public function run()
    {
        if (!$this->CUSTOM_PATH && !is_dir($this->PATH_IMAGES)) {
            mkdir($this->PATH_IMAGES);
        }

        $this->PATH_IMAGES_FOUND = $this->PATH_IMAGES . '/found/';

        if (!is_dir($this->PATH_IMAGES_FOUND)) {
            mkdir($this->PATH_IMAGES_FOUND);
        }

        $this->PATH_IMAGES_NOT_FOUND = $this->PATH_IMAGES . '/not found/';

        if (!is_dir($this->PATH_IMAGES_NOT_FOUND)) {
            mkdir($this->PATH_IMAGES_NOT_FOUND);
        }

        if (!is_dir($this->PATH_LOGS) || !is_writable($this->PATH_LOGS)) {
            $this->PATH_LOGS = ROOT . '/' . $this->PATH_LOGS;
        }

        if ($this->LOGGING && !is_dir($this->PATH_LOGS)) {
            mkdir($this->PATH_LOGS);
        }

        if ($this->OUTPUT_HTML && !is_file($this->OUTPUT_HTML_FILE)) {
            touch($this->OUTPUT_HTML_FILE);
        }

        if ($this->DEBUG) {
            error_reporting(E_ALL);
        }

        if ($this->USE_PHPWFIO) {
            $this->PATH_IMAGES = "wfio://" . $this->PATH_IMAGES;
            $this->PATH_IMAGES_FOUND = "wfio://" . $this->PATH_IMAGES_FOUND;
            $this->PATH_IMAGES_NOT_FOUND = "wfio://" . $this->PATH_IMAGES_NOT_FOUND;
        }

        if (file_exists(ROOT . '/tempfile')) {
            unlink(ROOT . '/tempfile');
        }

        $this->PATH_LOGS = realpath($this->PATH_LOGS);

        $this->OUTPUT_HTML_FILE = realpath($this->OUTPUT_HTML_FILE);

        $this->showASCIISplash();

        $this->updater();

        $this->main();
    }

    /**
     * Prevent overwriting files
     *
     * @param string $from
     * @param string $to
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
     * Perform reverse search using iqdb.harry.lu
     *
     * @param string $file
     * @return array|string|bool
     */
    private function reverseSearch($file)
    {
        if ($this->USE_PHPWFIO) {
            copy($file, ROOT . '/tempfile');
        }

        $post_data = [];

        if ($this->USE_PHPWFIO) {
            $post_data['file'] = new \CurlFile(ROOT . '/tempfile', mime_content_type($file), basename($file));
        } else {
            $post_data['file'] = new \CurlFile($file, mime_content_type($file), basename($file));
        }

        $post_data['service[]'] = '0';
        $post_data['MAX_FILE_SIZE'] = '8388608';

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "http://iqdb.harry.lu");
        curl_setopt($ch, CURLOPT_USERAGENT, $this->NAME);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'cURLProgress']);

        $output = curl_exec($ch);

        if ($this->USE_PHPWFIO) {
            unlink(ROOT . '/tempfile');
        }

        if ($this->DEBUG) {
            print "\nOUTPUT:\n" . $output . "\n";
        }

        if (preg_match_all("/Probable match.*?href\=.*?e621\.net.*?\/show\/(\d+)/", $output, $matches)) {
            return $matches[1];
        }

        if (empty($output)) {
            return ['error' => 'EmptyResult'];
        }

        if (strpos($output, 'We didn\'t find any results that were highly-relevant')) {
            return ['error' => 'NoResults'];
        }

        if (strpos($output, 'Not an image')) {
            return ['error' => 'NotImage'];
        }

        if (strpos($output, 'Upload error ')) {
            return ['error' => 'UploadError'];
        }

        return $output;
    }

    /**
     * Make a query to e621 API
     *
     * @param string $tags
     * @param int $page
     * @param int $limit
     * @return string|bool
     */
    private function apiRequest($tags, $page = 1, $limit = 1)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://e621.net/post/index.json?limit=' . $limit . '&page=' . $page . '&tags=' . $tags);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->NAME);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'cURLProgress']);

        $output = curl_exec($ch);

        if ($this->DEBUG) {
            print "\nOUTPUT:\n" . $output . "\n";
        }

        if ($this->DEBUG) {
            $this->printout("\n" . $output . "\n");
        }

        return $output;
    }

    /**
     * Main function
     *
     * @throws \Exception
     */
    private function main()
    {
        if ($this->CUSTOM_PATH) {
            $this->printout("Using path: " . str_replace("wfio://", "", $this->PATH_IMAGES) . "\n\n");
        }

        if (!extension_loaded("wfio") && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->printout("WARNING: 'php-wfio' extension not found, UTF-8 filename support will be disabled!\n\n");
            $this->USE_PHPWFIO = false;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
            $this->USE_PHPWFIO = false;
        }

        $files = [];
        $files_error = [];
        $files_count = 0;
        $files_total = 0;
        $found = 0;

        if (is_dir($this->PATH_IMAGES)) {
            $this->printout("Scanning for images...");

            if ($handle = opendir($this->PATH_IMAGES)) {
                while (false !== ($entry = readdir($handle))) {
                    if ($entry != "." && $entry != ".." && !is_dir($this->PATH_IMAGES . '/' . $entry)) {
                        $file_size = filesize($this->PATH_IMAGES . '/' . $entry);
                        $image_size = getimagesize($this->PATH_IMAGES . '/' . $entry);

                        if (urlencode($entry) != $entry && !$this->USE_PHPWFIO) {
                            $files_error['encoding'] = true;
                        } elseif (!in_array(pathinfo($entry, PATHINFO_EXTENSION), array('jpg', 'jpeg', 'png', 'gif'))) {
                            $files_error['file_type'] = true;
                        } elseif ($file_size > 8388608) {
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
                $this->printout("WARNING: Some files contained non-standard characters in their names and were ignored!\n");
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

            foreach ($files as $entry) {
                $files_count++;
                $this->printout('[' . ($files_count) . "/$files_total] Searching '" . (($this->USE_PHPWFIO) ? utf8_decode($entry):$entry) . "':\n");

                if ($this->MD5_SEARCH) {
                    $this->OUTPUT_BUFFER = " Trying md5 sum...";
                    $this->printout($this->OUTPUT_BUFFER);

                    $raw = $this->apiRequest('md5:' . md5_file($this->PATH_IMAGES . '/' . $entry));
                    $results = json_decode($raw, true);

                    print("\r" . $this->OUTPUT_BUFFER);
                }

                if (isset($results[0]['id'])) {
                    $results[0] = $results[0]['id'];
                } else {
                    if ($this->MD5_SEARCH) {
                        if (is_array($results)) {
                            $this->printout(" no matching posts found!\n");
                        } else {
                            $this->printout(" failed!\n");
                        }
                    }

                    if ($this->REVERSE_SEARCH) {
                        $this->OUTPUT_BUFFER = " Trying reverse search...";
                        $this->printout($this->OUTPUT_BUFFER);

                        $results = $this->reverseSearch($this->PATH_IMAGES . '/' . $entry);

                        print("\r" . $this->OUTPUT_BUFFER);
                    }
                }

                if (is_array($results)) {
                    $results = array_unique($results);
                }

                if (isset($results['error'])) {
                    if ($results['error'] == 'NoResults') {
                        $this->printout(" no matching images found!\n");
                        $this->safeRename($this->PATH_IMAGES . '/' . $entry, $this->PATH_IMAGES_NOT_FOUND . '/' . $entry);
                    } elseif ($results['error'] == 'NotImage') {
                        $this->printout(" not a valid image!\n");
                    } elseif ($results['error'] == 'EmptyResult') {
                        $this->printout(" no reply from the server!\n");
                    } elseif ($results['error'] == 'UploadError') {
                        $this->printout(" upload error!\n");
                    }
                } elseif (is_array($results) && count($results) > 0) {
                    $this->printout(" success!\n");
                    $found++;

                    if ($this->OUTPUT_HTML) {
                        $html_existing_contents = file_get_contents(realpath($this->OUTPUT_HTML_FILE));
                    }

                    $html_output = '';
                    $results_text = '';
                    for ($i = 0; $i < count($results); $i++) {
                        $results_text .= '  https://e621.net/post/show/' . $results[$i] . "\n";

                        if ($this->OUTPUT_HTML) {
                            if (empty($html_existing_contents) || (!empty($html_existing_contents) && !strpos($html_existing_contents, 'e621.net/post/show/' . $results[$i]))) {
                                $html_output .= '&nbsp;<a href="https://e621.net/post/show/' . $results[$i] . '" target="_blank">https://e621.net/post/show/' . $results[$i] . '</a>' . "\n<br>\n";
                            }
                        }
                    }

                    $this->printout($results_text);

                    if ($this->OUTPUT_HTML) {
                        if (!file_exists($this->OUTPUT_HTML_FILE) || filesize($this->OUTPUT_HTML_FILE) == 0) {
                            file_put_contents($this->OUTPUT_HTML_FILE, "<html>\n<head>\n<meta charset=\"UTF-8\">\n<title>Post Links (e621 Batch Reverse Search)</title>\n</head>\n<body>\n<h5>Generated by " . $this->NAME . "</h5></h1>", LOCK_EX);
                        }

                        if (!empty($html_output)) {
                            file_put_contents($this->OUTPUT_HTML_FILE, "<b>" . ($entry) . "</b>:\n<br>\n$html_output<br>\n", FILE_APPEND | LOCK_EX);
                        }
                    } else {
                        file_put_contents($this->PATH_IMAGES_FOUND . '/' . $entry . '.txt', "  " . trim(strip_tags($results_text)) . "\n", FILE_APPEND);
                    }

                    $this->safeRename($this->PATH_IMAGES . '/' . $entry, $this->PATH_IMAGES_FOUND . '/' . $entry);
                } elseif ($this->REVERSE_SEARCH) {
                    $this->printout(" failed!\n");

                    if ($this->DEBUG) {
                        file_put_contents(ROOT . '/debug_error.txt', "iqdb.harry.lu server reply:\n\n" . $results);
                        throw new \Exception("Unhandled error occurred! (check 'error.txt' and contact the developer)");
                    } else {
                        if ($this->LOGGING) {
                            file_put_contents($this->PATH_LOGS . '/error_' . date("Ymd\_His") . '.txt', "iqdb.harry.lu server reply:\n\n" . $results);
                        }

                        $this->printout("WARNING: Unhandled error occurred! Turn on DEBUG mode or LOGGING to see more detail.\n");
                    }
                }

                $this->printout("\n");
            }

            if ($files_total > 0) {
                $this->printout("Found links for " . $found . "/" . $files_total . " images.\n");
            }

            $this->printout("Finished in " . round(microtime(true) - $this->START_TIME, 3) . " seconds.\n\n");

            closedir($handle);
        } else {
            throw new \Exception("Path '$this->PATH_IMAGES' is invalid! This error shouldn't happen!\n\n");
        }
    }
}

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
    private $VERSION = '1.2.0';

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
     * Reverse search is enabled or not
     *
     * @var bool
     */
    private $REVERSE_SEARCH = true;

    /**
     * Use php-wfio extension or not
     *  This is required for UTF-8 filename support on Windows
     *  (see https://github.com/kenjiuno/php-wfio)
     *
     * @var bool
     */
    private $USE_PHPWFIO = true;

    /**
     * Convert image into JPEG with '90' quality before uploading
     *
     * @var string
     */
    private $USE_CONVERSION = true;

    /**
     * Use 'saucenao.com' as additional service for reverse search
     *
     * @var string
     */
    private $USE_DUAL_SEARCH = true;

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
     * App constructor
     *
     * @param string $arg
     */
    public function __construct($arg = null)
    {
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
            declare(ticks = 1);
            pcntl_signal(SIGINT, [$this, 'interruptHandler']);
        }

        $short_opts = "p::c::vu";
        $long_opts  = ["path::", "config::", "version", "update"];

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
     * Interrupt handler (CTRL-C)
     *  (Linux only)
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
            print (str_repeat(' ', 10) . "\r" . $this->LINE_BUFFER . ' ' . round(($progress * 100) / $total, 0)) . "%";
        }

        usleep(100);
    }

    /**
     * Continous reading of the result
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
                $this->OUTPUT_HTML_FILE = $config['OUTPUT_HTML_FILE'];
            }

            if (isset($config['MD5_SEARCH'])) {
                $this->MD5_SEARCH = $config['MD5_SEARCH'];
            }

            if (isset($config['REVERSE_SEARCH'])) {
                $this->REVERSE_SEARCH = $config['REVERSE_SEARCH'];
            }

            if (!$this->REVERSE_SEARCH && !$this->MD5_SEARCH) {
                die("No search method set, check config!\n\n");
            }

            if (isset($config['USE_PHPWFIO'])) {
                $this->USE_PHPWFIO = $config['USE_PHPWFIO'];
            }

            if (isset($config['USE_CONVERSION'])) {
                $this->USE_CONVERSION = $config['USE_CONVERSION'];
            }

            if (isset($config['USE_DUAL_SEARCH'])) {
                $this->USE_DUAL_SEARCH = $config['USE_DUAL_SEARCH'];
            }

            if (isset($config['RETURN_TIMEOUT'])) {
                $this->RETURN_TIMEOUT = $config['RETURN_TIMEOUT'];
            }
        }
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
     * Just show ASCII splash...
     */
    private function showASCIISplash()
    {
        print '        __ ___  __   ____        _       _
       / /|__ \/_ | |  _ \      | |     | |    v' . $this->VERSION . (($this->DEBUG) ?  " DEBUG MODE":'') . '
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

                            $this->LINE_BUFFER = "Downloading update package...";
                            $this->printout($this->LINE_BUFFER);

                            $ch = curl_init($REMOTE_DOWNLOAD);
                            curl_setopt($ch, CURLOPT_USERAGENT, $this->USER_AGENT);
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
     * Pre-main function
     */
    public function run()
    {
        $this->PATH_IMAGES = str_replace("//", "/", $this->PATH_IMAGES);

        if (!$this->CUSTOM_PATH && !is_dir($this->PATH_IMAGES)) {
            mkdir($this->PATH_IMAGES);
        }

        $this->PATH_IMAGES_FOUND = str_replace("//", "/", $this->PATH_IMAGES_FOUND);

        if (!is_dir($this->PATH_IMAGES_FOUND)) {
            mkdir($this->PATH_IMAGES_FOUND);
        }

        $this->PATH_IMAGES_NOT_FOUND = str_replace("//", "/", $this->PATH_IMAGES_NOT_FOUND);

        if (!is_dir($this->PATH_IMAGES_NOT_FOUND)) {
            mkdir($this->PATH_IMAGES_NOT_FOUND);
        }

        $this->PATH_LOGS = str_replace("//", "/", $this->PATH_LOGS);

        if ($this->LOGGING && !is_dir($this->PATH_LOGS)) {
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
            $this->printout("Using path: " . str_replace("wfio://", "", $this->PATH_IMAGES) . "\n\n");
        }

        if (!$this->IS_LINUX) {
            if (!extension_loaded("wfio") && $this->USE_PHPWFIO) {
                $this->printout("WARNING: 'php_wfio.dll' extension not loaded - UTF-8 filename support will be disabled!\n\n");
                $this->USE_PHPWFIO = false;
            }

            if ($this->USE_PHPWFIO) {
                $this->PATH_IMAGES = "wfio://" . $this->PATH_IMAGES;
                $this->PATH_IMAGES_FOUND = "wfio://" . $this->PATH_IMAGES_FOUND;
                $this->PATH_IMAGES_NOT_FOUND = "wfio://" . $this->PATH_IMAGES_NOT_FOUND;
            }
        } else {
            $this->USE_PHPWFIO = false;
        }

        $this->main();

        exit;
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
     * Read the image and create image resource object
     *
     * @param $file
     * @param $type
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
     * Perform reverse search using iqdb.harry.lu
     *
     * @param string $file
     * @return array|string|bool
     */
    private function reverseSearch($file)
    {
        $post_data = [];

        if ($this->USE_PHPWFIO || $this->USE_CONVERSION) {
            $TEMP_FILE = tempnam(sys_get_temp_dir(), "Y69");

            if ($this->USE_CONVERSION) {
                $mime_type = mime_content_type($file);
                $image = $this->readImage($file, $mime_type);

                if (isset($image) && is_resource($image)) {
                    imagejpeg($image, $TEMP_FILE, 90);
                    imagedestroy($image);
                } elseif (is_array($image) && isset($image['error'])) {
                    return $image;
                } else {
                    return ['error' => 'NotResource'];
                }
            } elseif ($this->USE_PHPWFIO) {
                copy($file, $TEMP_FILE);
            }

            $post_data['file'] = new \CurlFile($TEMP_FILE, mime_content_type($TEMP_FILE), basename($TEMP_FILE));
        } else {
            $post_data['file'] = new \CurlFile($file, mime_content_type($file), basename($file));
        }

        $post_data['service[]'] = '0';
        $post_data['MAX_FILE_SIZE'] = '8388608';

        if (!empty($this->RETURN_BUFFER)) {
            $this->RETURN_BUFFER = '';
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "http://iqdb.harry.lu");
        curl_setopt($ch, CURLOPT_USERAGENT, $this->USER_AGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->RETURN_TIMEOUT);
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, $this->RETURN_TIMEOUT);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'cURLProgress']);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, [$this, 'cURLRead']);

        $output = curl_exec($ch);

        if (isset($TEMP_FILE) && file_exists($TEMP_FILE)) {
            unlink($TEMP_FILE);
        }

        if (!empty($this->RETURN_BUFFER)) {
            $output = $this->RETURN_BUFFER;
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
     * Perform reverse search using saucenao.com
     *
     * @param string $file
     * @return array|string|bool
     */
    private function reverseSearchAlt($file)
    {
        $post_data = [];

        if ($this->USE_PHPWFIO || $this->USE_CONVERSION) {
            $TEMP_FILE = tempnam(sys_get_temp_dir(), "Y69");

            if ($this->USE_CONVERSION) {
                $mime_type = mime_content_type($file);
                $image = $this->readImage($file, $mime_type);

                if (isset($image) && is_resource($image)) {
                    imagejpeg($image, $TEMP_FILE, 90);
                    imagedestroy($image);
                } elseif (is_array($image) && isset($image['error'])) {
                    return $image;
                } else {
                    return ['error' => 'NotResource'];
                }
            } elseif ($this->USE_PHPWFIO) {
                copy($file, $TEMP_FILE);
            }

            $post_data['file'] = new \CurlFile($TEMP_FILE, mime_content_type($TEMP_FILE), basename($TEMP_FILE));
        } else {
            $post_data['file'] = new \CurlFile($file, mime_content_type($file), basename($file));
        }

        $post_data['url'] = '';
        $post_data['frame'] = '1';
        $post_data['hide'] = '0';
        $post_data['database'] = '999';

        if (!empty($this->RETURN_BUFFER)) {
            $this->RETURN_BUFFER = '';
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "http://saucenao.com/search.php");
        curl_setopt($ch, CURLOPT_USERAGENT, $this->USER_AGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->RETURN_TIMEOUT);
        curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, $this->RETURN_TIMEOUT);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'cURLProgress']);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, [$this, 'cURLRead']);

        $output = curl_exec($ch);

        if (isset($TEMP_FILE) && file_exists($TEMP_FILE)) {
            unlink($TEMP_FILE);
        }

        if (!empty($this->RETURN_BUFFER)) {
            $output = $this->RETURN_BUFFER;
        }

        if ($this->DEBUG) {
            print "\nOUTPUT:\n" . $output . "\n";
        }

        $matches_all = [];
        if (preg_match_all("/href\=.*?e621\.net.*?\/show\/(\d+)/", $output, $matches)) {
            $matches_all = array_merge($matches_all, $matches[1]);
        }

        if (preg_match_all("/resultcontentcolumn.*?<strong>(.*?): <\/strong><a href=\"(.*?)\".*?result-hidden-notification/", $output, $matches)) {
            $matches_all = array_merge($matches_all, $matches[2]);
        }

        if (!empty($matches_all)) {
            return $matches_all;
        }

        if (empty($output)) {
            return ['error' => 'EmptyResult'];
        }

        if (strpos($output, 'Low similarity results have been hidden. Click here to display them...') !== false) {
            return ['error' => 'NoResults'];
        }

        if (strpos($output, 'If an image was provided, it may not be loading properly, or may not be in an acceptable format...') !== false) {
            return ['error' => 'NotResource'];
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
        curl_setopt($ch, CURLOPT_USERAGENT, $this->USER_AGENT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->RETURN_TIMEOUT);
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
        } elseif (!empty($error)) {
            $this->printout(" error: " . $error . "\n");
        } else {
            $this->printout(" empty response!\n");
        }
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
                    if ($entry != "." && $entry != ".." && !is_dir($this->PATH_IMAGES . '/' . $entry)) {
                        $file_size = filesize($this->PATH_IMAGES . '/' . $entry);
                        $image_size = getimagesize($this->PATH_IMAGES . '/' . $entry);

                        if (urlencode($entry) != $entry && !$this->USE_PHPWFIO && !$this->IS_LINUX) {
                            $files_error['encoding'] = true;
                        } elseif (!in_array(pathinfo($entry, PATHINFO_EXTENSION), array('jpg', 'jpeg', 'png', 'gif'))) {
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

            $this->IS_RUNNING = true;
            foreach ($files as $entry) {
                if ($this->IS_RUNNING) {
                    $files_count++;
                    $this->printout('[' . ($files_count) . "/$files_total] Searching '" . (($this->USE_PHPWFIO) ? utf8_decode($entry):$entry) . "':\n");

                    $results = null;
                    if ($this->MD5_SEARCH) {
                        $this->LINE_BUFFER = " Trying md5 sum...";
                        $this->printout($this->LINE_BUFFER);

                        $raw = $this->apiRequest('md5:' . md5_file($this->PATH_IMAGES . '/' . $entry));
                        $results = json_decode($raw, true);

                        print("\r" . $this->LINE_BUFFER);
                    }

                    if (isset($results[0]['id'])) {
                        $results[0] = $results[0]['id'];
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
                            if ($this->USE_DUAL_SEARCH) {
                                $this->LINE_BUFFER = " Trying reverse search #1 (iqdb.harry.lu)...";
                            } else {
                                $this->LINE_BUFFER = " Trying reverse search...";
                            }

                            $this->printout($this->LINE_BUFFER);

                            $results = $this->reverseSearch($this->PATH_IMAGES . '/' . $entry);

                            print("\r" . $this->LINE_BUFFER);

                            if ($this->USE_DUAL_SEARCH && (!is_array($results) || isset($results['error']))) {
                                $this->parseError($results['error']);

                                $this->LINE_BUFFER = " Trying reverse search #2 (saucenao.com)...";
                                $this->printout($this->LINE_BUFFER);

                                $results = $this->reverseSearchAlt($this->PATH_IMAGES . '/' . $entry);

                                print("\r" . $this->LINE_BUFFER);
                            }
                        }
                    }

                    if (is_array($results)) {
                        $results = array_unique($results);
                    }

                    if (isset($results['error'])) {
                        $this->parseError($results['error']);

                        if ($results['error'] == 'NoResults') {
                            $this->safeRename($this->PATH_IMAGES . '/' . $entry, $this->PATH_IMAGES_NOT_FOUND . '/' . $entry);
                        }
                    } elseif (is_array($results) && count($results) > 0) {
                        $this->printout(" success!\n");
                        $files_found++;

                        if ($this->OUTPUT_HTML) {
                            $html_existing_contents = file_get_contents($this->OUTPUT_HTML_FILE);
                        }

                        $html_output = '';
                        $results_text = '';
                        for ($i = 0; $i < count($results); $i++) {

                            if (is_numeric($results[$i])) {
                                $results_text .= '  https://e621.net/post/show/' . $results[$i] . "\n";
                            } else {
                                $results_text .= '  ' . $results[$i] . "\n";
                            }

                            if ($this->OUTPUT_HTML) {
                                if (empty($html_existing_contents) || (!empty($html_existing_contents) && !strpos($html_existing_contents, 'e621.net/post/show/' . $results[$i]))) {

                                    if (is_numeric($results[$i])) {
                                        $html_output .= '&nbsp;<a href="https://e621.net/post/show/' . $results[$i] . '" target="_blank">https://e621.net/post/show/' . $results[$i] . '</a>' . "\n<br>\n";
                                    } else {
                                        $html_output .= '&nbsp;<a href="' . $results[$i] . '" target="_blank">' . $results[$i] . '</a>' . "\n<br>\n";
                                    }
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
                            file_put_contents(ROOT . '/debug_error.txt', "server reply:\n\n" . $results);
                            die("Unhandled error occurred! (check 'debug_error.txt' and contact the developer)");
                        } elseif ($this->LOGGING) {
                            $date = date("Ymd\_His");
                            file_put_contents($this->PATH_LOGS . '/error_' . $date . '.txt', "server reply:\n\n" . $results);
                            $this->printout("\nWARNING: Unhandled error occurred! (check 'error.txt' and contact the developer)\n");
                        } else {
                            $this->printout("\nWARNING: Unhandled error occurred! Turn on DEBUG mode or LOGGING to see more details.\n");
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
}

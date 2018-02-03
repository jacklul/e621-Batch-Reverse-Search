<?php

echo "Preparing..." . PHP_EOL;

$files = ['build/images/test-md5.jpg', 'build/images/test-md5.jpeg', 'build/images/test-md5.png', 'build/images/test-md5.gif', 'build/images/test-reverse_search.jpg', 'build/images/test-reverse_search.jpeg', 'build/images/test-reverse_search.png', 'build/images/test-reverse_search.gif', 'build/images/found/test-md5.jpg', 'build/images/found/test-md5.jpeg', 'build/images/found/test-md5.png', 'build/images/found/test-md5.gif', 'build/images/found/test-reverse_search.jpg', 'build/images/found/test-reverse_search.jpeg', 'build/images/found/test-reverse_search.png', 'build/images/found/test-reverse_search.gif', 'build/images/found/links.html'];

foreach ($files as $file) {
    @unlink($file);
}

if (!is_dir("build/images/")) {
    mkdir("build/images/", 0755, true);
}

echo "Downloading test image using tags 'falvie order:favcount'..." . PHP_EOL;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://e621.net/post/index.json?tags=" . urlencode("falvie order:favcount") . "&limit=1&page=1");
curl_setopt($ch, CURLOPT_USERAGENT, "e621 Batch Reverse Search - Test Script");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$result = curl_exec($ch);

if (!empty($result)) {
    $result = json_decode($result, true);
}

if (is_array($result) && !empty($result[0]['file_url']) && !empty($result[0]['sample_url'])) {
    $image_full = file_get_contents($result[0]['file_url']);
    $image_small = file_get_contents($result[0]['sample_url']);

    if (!$image_full || !$image_small) {
        echo "Download failed!" . PHP_EOL;
        exit(1);
    }

    file_put_contents('build/images/test-md5.' . pathinfo($result[0]['file_url'], PATHINFO_EXTENSION), $image_full);
    file_put_contents('build/images/test-reverse_search.' . pathinfo($result[0]['sample_url'], PATHINFO_EXTENSION), $image_small);

    require_once("build/e621BRS.phar");
} else {
    echo "API request failed!" . PHP_EOL;
    exit(1);
}

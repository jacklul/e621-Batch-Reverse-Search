<?php

/* need a better way to do this... */
@unlink('build/images/test-md5.jpg');
@unlink('build/images/test-md5.jpeg');
@unlink('build/images/test-md5.png');
@unlink('build/images/test-md5.gif');
@unlink('build/images/test-reverse_search.jpg');
@unlink('build/images/test-reverse_search.jpeg');
@unlink('build/images/test-reverse_search.png');
@unlink('build/images/test-reverse_search.gif');
@unlink('build/images/found/test-md5.jpg');
@unlink('build/images/found/test-md5.jpeg');
@unlink('build/images/found/test-md5.png');
@unlink('build/images/found/test-md5.gif');
@unlink('build/images/found/test-reverse_search.jpg');
@unlink('build/images/found/test-reverse_search.jpeg');
@unlink('build/images/found/test-reverse_search.png');
@unlink('build/images/found/test-reverse_search.gif');

echo "Downloading test images...";

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
    file_put_contents('build/images/test-md5.' . pathinfo($result[0]['file_url'], PATHINFO_EXTENSION), file_get_contents($result[0]['file_url']));
    file_put_contents('build/images/test-reverse_search.' . pathinfo($result[0]['sample_url'], PATHINFO_EXTENSION), file_get_contents($result[0]['sample_url']));

    echo " done!\nTesting...\n\n";

    require_once("build/e621BRS.phar");
} else {
    echo " fail!\nTest cannot be performed!\n";
}

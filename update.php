<?php

require_once 'VDir.php';

define('ALLOW_LOW_VERSION', false);

function respondMsg($error, $ret)
{
    header('Content-Type: text/plain; charset=utf-8');
    echo "return=$error";
    if (is_array($ret)) {
        foreach ($ret as $k => $v) {
            echo "\n$k=$v";
        }
    } else if (is_string($ret)) {
        echo "\nerror=$ret";
    }
    return $error !== 'ERROR';
}

foreach (['branch', 'ver', 'uuid'] as $it) {
    if (!isset($_GET[$it]) || !is_string($_GET[$it])) {
        return respondMsg('ERROR', "no $it");
    }
}

$branch = $_GET['branch'];
$version = $_GET['ver'];
$uuid = $_GET['uuid'];

$dir_obj = new VDir($branch);
$version_item_list = $dir_obj->getVersionList();
if (empty($version_item_list)) {
    return respondMsg('ERROR', 'no version');
}

$version_item = $version_item_list[0];
$version_string = $version_item->getConfig('version');
if (empty($version_string)) {
    return respondMsg('ERROR', 'stock version error');
}

$v = version_compare($version, $version_string);
if ($v == 0) {
    return respondMsg('NONE', false);
}

if ($v > 0 && ALLOW_LOW_VERSION) {
    return respondMsg('NONE', false);
}

$bin_name = $version_item->getConfig('bin');
$url_path = $version_item->getUrlPath($bin_name);
$md5 = $version_item->getConfig('md5');
$bin_size = $version_item->getConfig('bin-size');
$release = $version_item->getConfig('release');

$host = $_SERVER['SERVER_NAME'];
$port = $_SERVER['SERVER_PORT'];
if ($port = '80') {
    $host = "$host:$port";
}

return respondMsg('OK', [
    'url' => "http://{$host}{$url_path}",
    'version' => $version_string,
    'md5' => $md5,
    'size' => $bin_size,
    "update" => $release
]);

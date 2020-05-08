<?php

if (PHP_OS == 'Linux') {
    define('LOG_PATH', '/var/log/version');
} else {
    define('LOG_PATH', 'c:\\temp\\version');
}
define('LOG_MAX_SIZE', 4096);
define('VERSION', '0.1.2');

class VIo
{
    const CONF_FILE_EXT = '.__version';
    const CONF_DOC_EXT = '.__doc_';

    /**
     * @param $msg
     * @return string
     */
    public function Logger($msg)
    {
        $dt = new DateTime();

        $tu = $dt->format('H:i:s');
        $file = LOG_PATH . DIRECTORY_SEPARATOR . $dt->format("Y-m-d") . ".log";

        $timestamp_ori = microtime(true);

        $timestamp = floor($timestamp_ori);
        $milliseconds = round(($timestamp_ori - $timestamp) * 1000000);
        $ms = sprintf("%06d", $milliseconds);

        if (is_array($msg)) {
            $msg_string = implode(",", $msg);
        } else if (is_object($msg)) {
            $msg_string = json_encode($msg, JSON_UNESCAPED_SLASHES);
        } else {
            $msg_string = strval($msg);
        }

        $len = strlen($msg_string);
        if ($len > LOG_MAX_SIZE) {
            $s = substr($msg_string, 0, LOG_MAX_SIZE) . '<' . ($len - LOG_MAX_SIZE) . ' more>';
        } else {
            $s = $msg_string;
        }

        @error_log("{$tu}.{$ms} : {$s}" . PHP_EOL, 3, $file);
        return $msg_string;
    }

    public function readConfigure($file)
    {
        $lines = @parse_ini_file($file);
        if (empty($lines)) {
            $this->Logger("$file read failed,return false");
            return false;
        }

        return $lines;
    }

    public function getFileCTime($filename)
    {
        $stamp = @filectime($filename);
        if (empty($stamp)) {
            return false;
        }

        return date("Y-m-d H:i:s", $stamp);
    }

    public function saveConfigure($file, $data)
    {
        $s = ";auto save";
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $v2) {
                    $s .=
                        ("{$k}[] = \"" . addslashes($v2) . "\"" . PHP_EOL);
                }
            } else {
                $s .=
                    ("{$k} = \"" . addslashes($v) . "\"" . PHP_EOL);
            }
        }

        @file_put_contents($file, $s);
    }

    public function responds($error, $value)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('error' => $error, 'value' => $value));
        return $error;
    }

    public function userAuth($user, $password)
    {
        return in_array($user, array('liuhuisong', 'wuchanglin', 'huangzhixiong', 'hongfei')) &&
            $user === $password;
    }

    public function version2Values($ver)
    {
        $a = preg_split("/\./", $ver);
        $n = count($a);
        $val = 0;
        for ($i = 0; $i < 4; $i++) {
            $val <<= 8;
            if ($i < $n) {
                $val += (intval($a[$i]) & 0x7f);
            }
        }
        $this->Logger("ver=$ver,val=$val");
        return $val;
    }

    public function parseVersion($bin)
    {
        preg_match('/(\d+\.){1,3}\d+/', $bin, $match_array);
        if (is_array($match_array) && count($match_array) > 0) {
            return $match_array[0];
        }

        return false;
    }

    /***
     * @param $file
     * @param $path
     * @param $basename
     * @return true|string
     */
    public function moveUploadFileWithExt($file, $path, &$basename)
    {
        if (!is_array($file)) {
            return $this->Logger('file not array');
        }
        if (!isset($file['tmp_name'])) {
            return $this->Logger('tmp_name not present');
        }
        if (empty($file['tmp_name'])) {
            return $this->Logger('tmp_name empty, maybe upload_max_size too small in php.ini');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return $this->Logger("{$file['tmp_name']} is not upload file");
        }

        //get ext
        if (isset($file['name'])) {
            $array = explode(".", $file['name']);
            $ext = strtolower(end($array));
        }
        if (empty($ext)) {
            return $this->Logger('no file extension');
        }

        $dest = $path . DIRECTORY_SEPARATOR . "$basename.$ext";
        if (file_exists($dest)) {
            return $this->Logger("$dest exists already");
        }

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return $this->Logger("move file $dest failed");
        }

        $basename .= ".$ext";
        return true;
    }
}

class VItem extends VIo
{
    private $os_path;
    private $bin;
    private $configure;
    private $dirty = false;

    public function __construct($path, $bin)
    {
        $this->os_path = $path;
        $this->bin = $bin;

        if (file_exists($this->os_path . DIRECTORY_SEPARATOR . $this->bin)) {
            $this->configure = $this->readConfigure($this->os_path . DIRECTORY_SEPARATOR . $this->bin . self::CONF_FILE_EXT);
            $ver = $this->parseVersion($bin);
            $ver2 = $this->getConfig('version');
            if ($ver != $ver2) {
                $this->setConfig('version', $ver);
            }

            if (empty($this->getConfig('release'))) {
                $release = $this->getFileCTime($path . DIRECTORY_SEPARATOR . $bin);
                $this->setConfig('release', $release);
            }
        }
    }

    public function __destruct()
    {
        if (!empty($this->bin) && $this->dirty) {
            $this->saveConfigure($this->os_path . DIRECTORY_SEPARATOR . $this->bin . self::CONF_FILE_EXT, $this->configure);
        }
    }


    public function getConfig($item)
    {
        switch ($item) {
            case  'bin':
            {
                return $this->bin;
            }

            case  'bin-size':
            {
                return filesize($this->os_path . DIRECTORY_SEPARATOR . $this->bin);
            }

            default:
                if (is_array($this->configure) && isset($this->configure[$item])) {
                    return $this->configure[$item];
                }
                return false;
        }
    }

    public function setConfig($item, $value)
    {
        $this->Logger("set version {$this->os_path}/{$this->bin}:$item");
        $this->configure[$item] = $value;
        $this->configure['last-modified'] = date('Y-m-d H:i:s');
        $this->dirty = true;
    }

    public function getUrlPath($name)
    {
        $path1 = $_SERVER['PHP_SELF'];
        $pi1 = pathinfo($path1);

        $pi2 = pathinfo($this->os_path);
        return $pi1['dirname'] . DIRECTORY_SEPARATOR . $pi2['basename'] . DIRECTORY_SEPARATOR . $name;
    }

    public function getBinUrl()
    {
        return $this->getUrlPath($this->bin);
    }

    public function getAttachArray()
    {
        $attach = $this->getConfig('attach');
        if (is_string($attach)) {
            return [$attach];
        } else if (is_array($attach)) {
            return $attach;
        }
        return false;
    }

    public function getAttachSize($attach_name)
    {
        $os_file = $this->os_path . DIRECTORY_SEPARATOR . $attach_name;
        if (!file_exists($os_file)) {
            return false;
        }
        return filesize($os_file);
    }

    public function dump()
    {
        $dump['os_path'] = $this->os_path;
        $dump['bin'] = $this->bin;
        $dump['configure'] = $this->configure;
        $dump['dirty'] = $this->dirty;
        return $dump;
    }
}

class VDir extends VIo
{
    private $os_path;
    private $description;
    private $val_2_version_array = array();//val=>ver

    private $map_item = array();

    public function __construct($path)
    {
        $this->os_path = $path;

        $it = $this->readConfigure($this->os_path . DIRECTORY_SEPARATOR . self::CONF_FILE_EXT);
        if (isset($it['description'])) {
            $this->description = $it['description'];
        } else {
            $pi = pathinfo($path);
            $this->description[] = $pi['basename'];
        }

        $handle = opendir($this->os_path);
        if ($handle !== false) {
            $n = 0;
            while (($bin = readdir($handle)) !== false) {
                if (substr($bin, 0, 1) == '.') {
                    continue;
                }

                if (is_file($this->os_path . '/' . $bin) &&
                    strstr($bin, self::CONF_FILE_EXT) === false &&
                    strstr($bin, self::CONF_DOC_EXT) === false) {
                    $ver = $this->parseVersion($bin);
                    if (!empty($ver) && ($val = $this->version2Values($ver)) > 0) {
                        $it = new VItem($this->os_path, $bin);
                        if (isset($this->val_2_version_array[$val])) {
                            $this->Logger("ERROR $ver identical");
                        }
                        $this->val_2_version_array[$val] = $it;
                        $this->map_item[$bin] = $it;
                        $n++;
                    }
                }
            }
            closedir($handle);

            if ($n > 0) {
                krsort($this->val_2_version_array, SORT_NUMERIC);
            }

            $this->Logger("{$this->os_path} found $n");
        } else {
            $this->Logger("VItem open dir failed");
        }
    }

    public function getDescription($index)
    {
        if (is_array($this->description)) {
            if (!!is_numeric($index) || $index < 0 || $index > count($this->description) - 1) {
                return $this->description[0];
            }
            return $this->description[$index];
        } else {
            return $this->description;
        }
    }

    /**
     * @return VItem[]
     */
    public function getVersionList()
    {
        return array_values($this->val_2_version_array);
    }

    /***
     * @param $version
     * @param $name_type
     * @param $file_bin
     * @param $file_attach
     * @param $config
     * @return string|VItem error if string, else successful
     */
    public function addItem($version, $name_type, $file_bin, $file_attach, $config)
    {
        $val = $this->version2Values($version);
        if (isset($this->val_2_version_array[$val])) {
            return "$version exists";
        }

        $base_name = "$name_type-$version";
        if (isset($config['release-ext'])) {
            $base_name .= ("-" . $config['release-ext']);
        }

        $ret = $this->moveUploadFileWithExt($file_bin, $this->os_path, $base_name);
        if (is_string($ret)) {
            return $ret;
        }
        $item = new VItem($this->os_path, $base_name);

        $basename2 = $base_name . self::CONF_DOC_EXT;
        if ($this->moveUploadFileWithExt($file_attach, $this->os_path, $basename2) === true) {
            $item->setConfig('attach', $basename2);
        }
        if (is_array($config)) {
            foreach ($config as $k => $v) {
                $item->setConfig($k, $v);
            }
        }
        $item->setConfig('release', date('Y-m-d H:i:s'));

        $this->val_2_version_array[$val] = $item;
        krsort($this->val_2_version_array);

        $this->map_item[$base_name] = $item;

        return $item;
    }

    /**
     * @param $bin_name
     * @return bool|VItem
     */
    public function findItemByBinName($bin_name)
    {
        if (isset($this->map_item[$bin_name])) {
            return $this->map_item[$bin_name];
        }

        return false;
    }

    public function dump()
    {
        $dump['os_path'] = $this->os_path;
        $dump['description'] = $this->description;
        foreach ($this->val_2_version_array as $val => $it) {
            if ($it instanceof VItem) {
                $dump['item-list'][$val] = $it->dump();
            }
        }
        return $dump;
    }
}

class VRoot extends VIO
{
    private $os_path;
    private $dir_list = array();

    public function __construct()
    {
        $pi = pathinfo($_SERVER['SCRIPT_FILENAME']);
        $this->os_path = $pi['dirname'];

        $handle = opendir($this->os_path);
        if ($handle !== false) {
            while (($dir = readdir($handle)) !== false) {
                if (substr($dir, 0, 1) == '.') {
                    continue;
                }
                if (is_dir($dir)) {
                    $this->dir_list[$dir] = new VDir($this->os_path . DIRECTORY_SEPARATOR . $dir);
                }
            }
            closedir($handle);

            ksort($this->dir_list);
        }
    }

    public function getDirArray()
    {
        return $this->dir_list;
    }

    /**
     * @param $name_type
     * @param $file_bin
     * @param $file_attach
     * @param $config
     * @return bool|string
     */
    public function addItemByName($name_type, $file_bin, $file_attach, $config)
    {
        if (!isset($this->dir_list[$name_type])) {
            return "dir not exists";
        }

        $v_dir = $this->dir_list[$name_type];
        if ($v_dir instanceof VDir) {
            if (!isset($config['version'])) {
                return "no release";
            }

            return $v_dir->addItem($config['version'], $name_type, $file_bin, $file_attach, $config);
        }

        return 'no dir, or something is wrong';
    }

    public function findItemByBinName($bin_name)
    {
        foreach ($this->dir_list as $v_dir) {
            if ($v_dir instanceof VDir) {
                $item = $v_dir->findItemByBinName($bin_name);
                if ($item !== false) {
                    return $item;
                }
            }
        }

        return false;
    }

    public function dump()
    {
        $dump['os_path'] = $this->os_path;
        foreach ($this->dir_list as $dir => $it) {
            if ($it instanceof VDir) {
                $dump['dir-list'][$dir] = $it->dump();
            }
        }
        return $dump;
    }
}

$root = new VRoot();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vio = new VIo();

    //pkg-name-type
    if (!isset($_POST['pkg-name-type'])) {
        return $vio->responds('ERROR', 'no pkg-name-type');
    }
    $name_type = $_POST['pkg-name-type'];

    //pkg-version
    if (!isset($_POST['pkg-version'])) {
        return $vio->responds('ERROR', 'no pkg-version');
    }
    $version = $_POST['pkg-version'];
    if (!preg_match("/[0-9]+(\.[0-9]+){1,3}/", $version)) {
        return $vio->responds('ERROR', 'version error');
    }

    //pkg-file-bin
    if (!isset($_FILES['pkg-file-bin'])) {
        return $vio->responds('ERROR', 'no pkg-file');
    }

    //pkg-description
    if (!isset($_POST['pkg-description'])) {
        return $vio->responds('ERROR', 'no pkg-description');
    }
    $description = $_POST['pkg-description'];
    $a = preg_split('/[\r\n]/', $description);
    $des_array = array_filter($a);

    $document = isset($_POST['pkg-doc-url']) ? $_POST['pkg-doc-url'] : false;

    $compatible = isset($_POST['pkg-compatible']) ? $_POST['pkg-compatible'] : false;

    if (!isset($_POST['pkg-user']) || !isset($_POST['pkg-password'])) {
        return $vio->responds('ERROR', 'no user/password');
    }

    if (!$root->userAuth($_POST['pkg-user'], $_POST['pkg-password'])) {
        return $vio->responds('ERROR', 'user/password error');
    }


    $ret = $root->addItemByName($name_type, $_FILES['pkg-file-bin'],
        (isset($_FILES['pkg-file-attach']) ? $_FILES['pkg-file-attach'] : false),
        array(
            'user' => $_POST['pkg-user'],
            'version' => $version,
            'description' => $des_array,
            'document' => $document,
            'compatible' => $compatible));

    $vio->responds((is_string($ret) ? 'ERROR' : 'OK'), $ret);
    return;
} else if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (isset($_GET['test-bin-name'])) {
        $bin_name_array = preg_split("/[,\s]+/", $_GET['test-bin-name']);
        foreach ($bin_name_array as $it) {
            $v_it = $root->findItemByBinName($it);
            if (empty($v_it)) {
                return $root->responds('ERROR', $it);
            }
        }
        return $root->responds('OK', $bin_name_array);
    }

    if (isset($_GET['dump'])) {
        return $root->responds('DUMP', $root->dump());
    }
}

?>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css">
    <title>版本</title>
    <style>
        body {
            background-color: #f8f8f8;
        }

        dl {
            margin-bottom: 12px;
        }

        ol {
            line-height: 1.7;
        }

        .container {
            background-color: white;
        }

        .version-title {
            font-size: 160%;
            margin: 40px 20px -15px 4px;
        }

        .row {
            margin-bottom: 10px;
        }

        .version-dt {
            font-size: 12pt;
            font-weight: 200;
            font-style: italic;
            color: gray;
            text-align: right;
        }

        .version-hr {
            margin-top: 0;
        }

        .version-dd {
            /*margin-left: 60px;*/
        }

        .version-dd > ol {
            margin-left: -25px;
        }

        .version-compatible {
            padding: 8px 16px 8px 0;
        }

        #form-add-version {
            padding: 40px 15px 20px 15px;
            width: 900px;
            margin-left: 15px;
        }

        .qr-code {
            height: 96px;
            width: 96px;
        }
    </style>
</head>
<body>
<div class="container">
    <div>
        <nav class="navbar navbar-fixed-top">
            <div class="container">
                <ul class="nav nav-tabs" id="menu-branch">
                    <?php
                    session_start();
                    $href = $_SERVER['PHP_SELF'];
                    if (isset($_GET['menu'])) {
                        $active_menu = $_GET['menu'];
                    } else if (isset($_SESSION['bin-menu'])) {
                        $active_menu = $_SESSION['bin-menu'];
                    } else {
                        $active_menu = false;
                    }
                    $dir_array = $root->getDirArray();
                    $active_version_list = false;

                    foreach ($dir_array as $dir => $item) {
                        if ($active_menu == false) {
                            $active_menu = $dir;
                        }

                        if ($item instanceof VDir) {
                            $label = $item->getDescription(0);
                            $version_list = $item->getVersionList();
                            if ($active_menu == $dir) {
                                $active_version_list = $version_list;
                            }
                        } else {
                            $label = $dir;
                        }

                        if ($active_menu == $dir) {
                            echo "<li role='presentation' class='active'><a>{$label}</a></li>";
                        } else {
                            echo "<li role='presentation'> <a href='$href?menu={$dir}'>{$label}</a></li>";
                        }
                    }

                    $_SESSION['bin-menu'] = $active_menu;
                    ?>
                </ul>
            </div>
        </nav>
    </div>
    <div class="row">
        <br><br><br><br><br>
        <div>
            <button class="btn btn-link" id="btn-view-version" style="float: right">增加</button>
        </div>
        <div class="well" id="form-add-version" style="display: none;">

            <form class="form-horizontal" role="form">
                <div class="form-group">
                    <label class="col-sm-2 control-label" for="pkg-file-bin">软件包</label>
                    <div class="col-sm-8">
                        <input type="file" class="form-control" id="pkg-file-bin"><span>(必填)</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="pkg-version">版本号</label>
                    <div class="col-sm-2">
                        <input type="text" class="form-control" id="pkg-version" placeholder="a.b.c.d">
                        <input type="hidden" id="pkg-name-type"
                            <?php
                            echo "value=\"$active_menu\"";
                            ?>
                        />
                        <span>(必填)</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="pkg-description">版本说明</label>
                    <div class="col-sm-8">
                        <textarea class="form-control" id="pkg-description"
                                  aria-multiline="true" placeholder="只支持单层次列表，每条目换行即可，无需编号"
                                  rows="8"
                        ></textarea>
                        <span>(必填)</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="pkg-doc-url">文档链接</label>
                    <div class="col-sm-8">
                        <input type="url" class="form-control" id="pkg-doc-url" placeholder="文档的URL">
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="pkg-file-attach">附件</label>
                    <div class="col-sm-8">
                        <input type="file" class="form-control" id="pkg-file-attach">
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="pkg-compatible">依赖</label>
                    <div class="col-sm-8">
                        <input type="text" class="form-control" id="pkg-compatible"
                               placeholder="软件包完整名称，多个名称可用空格或者逗号分隔">
                        <span class="text-danger" id="pkg-compatible-info"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-2 control-label" for="pkg-user">登录验证</label>
                    <div class="col-sm-2">
                        <input type="text" class="form-control" id="pkg-user" placeholder="用户名">
                    </div>
                    <div class="col-sm-2">
                        <input type="password" class="form-control" id="pkg-password" placeholder="密码">
                    </div>
                </div>
                <hr>
                <div class="col-sm-offset-2 col-sm-6">
                    <div class="btn btn-default" id="pkg-upload"><span class="glyphicon glyphicon-save"></span>
                        提交
                    </div>
                    <div id="upload-info"></div>
                </div>
                <br><br>
                <?php
                function formatNum($bytes)
                {
                    $si_prefix = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
                    $base = 1024;
                    $class = min((int)log($bytes, $base), count($si_prefix) - 1);

                    return sprintf('%1.2f', $bytes / pow($base, $class)) . ' ' . $si_prefix[$class];
                }

                $dt = disk_total_space("/");
                $dts = formatNum($dt);
                $df = disk_free_space("/");
                $dfs = formatNum($df);
                $dfp = sprintf("%.1f", ($dt - $df) * 100 / $dt);
                echo "<div>Total: $dts , Free: $dfs ,  used: $dfp% </div>";
                ?>
            </form>
        </div>
    </div>
    <div class="row">
        <div class="col-md-10">
            <?php
            if (!empty($active_version_list)) {
                foreach ($active_version_list as $item) {
                    if ($item instanceof VItem) {
                        $version = $item->getConfig('version');
                        $bin = $item->getConfig("bin");


                        $release = $item->getConfig('release');
                        echo "<div><div class='version-title'>$version</div><div class='text-right'>$release</div></div>";
                        echo "<hr class='version-hr'>";

                        $url = $item->getBinUrl();
                        $bin_size = $item->getConfig('bin-size');
                        if (!empty($url) && $bin_size > 0) {
                            $n = number_format($bin_size);
                            echo "<div class='row'><div class='col-md-1'><div class='version-dt'>下载</div></div>" .
                                "<div class='col-md-9'><div class='version-dd'><a href='$url'>$bin</a>, " .
                                "<div class='text-muted small'> $n bytes</div></div></div>";

                            echo "<div class='col-md-2'>";
                            if (substr($url, -4) == '.apk') {
                                $s = "http://" . $_SERVER['HTTP_HOST'] . $url;
                                if (PHP_OS == 'WINNT') {
                                    $s = str_replace("\\", "/", $s);
                                }
                                echo "<img class='qr-code' src='http://qr.topscan.com/api.php?text=$s' alt='qr code' title='$s'/>";
                            }
                            echo "</div>";

                            echo "</div>";
                        }

                        $description = $item->getConfig('description');
                        if (is_string($description)) {
                            $a[] = $description;
                        } else if (is_array($description)) {
                            $a = $description;
                        } else {
                            $a = false;
                        }
                        if (is_array($a)) {
                            $s = "<ol class='text-muted'><li>" . implode('</li><li>', $a) . '</li></ol>';

                            echo "<div class='row'><div class='col-md-1'><div class='version-dt'>说明</div></div>" .
                                "<div class='col-md-10'><div class='version-dd'>$s</div></div></div>";
                        }

                        $doc_url = $item->getConfig('document');
                        if (!empty($doc_url)) {
                            $s = "<a href='$doc_url'>$doc_url</a>";

                            echo "<div class='row'><div class='col-md-1'><div class='version-dt'>文档</div></div>" .
                                "<div class='col-md-10'><div class='version-dd'>$s</div></div></div>";
                        }

                        $attach_array = $item->getAttachArray();
                        if (!empty($attach_array)) {
                            echo "<div class='row'><div class='col-md-1'><div class='version-dt'>附件</div></div>" .
                                "<div class='col-md-10'><div class='version-dd'>";

                            foreach ($attach_array as $attach_name) {
                                $url = $item->getUrlPath($attach_name);
                                $n = $item->getAttachSize($attach_name);
                                if ($n === false) {
                                    echo "<span>$attach_name</span> (" .
                                        "<span class='text-muted small'> not found</span>),";
                                } else {
                                    echo "<a href='{$url}'>$attach_name</a> (" .
                                        "<span class='text-muted small'>$n bytes</span>),";
                                }

                            }

                            echo "</div></div></div>";
                        }

                        $compatible = $item->getConfig('compatible');
                        if (!empty($compatible)) {
                            echo "<div class='row'><div class='col-md-1'><div class='version-dt'>依赖</div></div>" .
                                "<div class='col-md-10'><div class='version-dd'>";
                            $com_array = preg_split("/[,\s]+/", $compatible);
                            foreach ($com_array as $it) {
                                $v_it = $root->findItemByBinName($it);
                                if ($v_it instanceof VItem) {
                                    $url = $v_it->getBinUrl();
                                    echo "<a href='$url' class='version-compatible'>$it</a>";
                                } else {
                                    echo "<span class='version-compatible'>$it</span>";
                                }
                            }
                            echo "</div></div></div>";
                        }

                        $user = $item->getConfig('user');
                        if (!empty($user)) {
                            echo "<div class='row'><div class='col-md-1'><div class='version-dt'>上传</div></div>" .
                                "<div class='col-md-10'>" .
                                "<span class='version-dd text-info'>" .
                                "<span class='glyphicon glyphicon-user'></span>&nbsp;{$user}" .
                                "</span></div></div>";
                        }

                        echo "<div class='row'><div class='col-md-1'><div class='version-dt'>bugs</div></div>" .
                            "<div class='col-md-10'><div class='version-dd text-muted'></div></div></div>";


                        echo "<br>";
                    }
                }
            } else {
                echo "<div>no any version here</div>";
            }
            ?>
        </div>
    </div>
    <hr>
    <div class="small text-right text-muted">
        <em>Version Release <?php echo VERSION; ?>, Apache License 2.0</em><br>
        <em>by liuhuisong@hotmail.com</em><br>
    </div>
</div>
<script src="https://apps.bdimg.com/libs/jquery/2.1.4/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/js/bootstrap.min.js"></script>
<script>
    $('#btn-view-version').click(function () {
        let it = $('#form-add-version');
        if (it.css('display') === 'none') {
            it.show();
            $(this).text('隐藏');
        } else {
            it.hide();
            $(this).text('增加');
        }
    });

    $('#pkg-compatible').change(function () {
        let names = $(this).val();
        let info = $('#pkg-compatible-info').empty();
        if (names) {
            $.get(<?php echo "\"{$_SERVER['SCRIPT_NAME']}\"";?>, {
                'test-bin-name': names
            }, function (r) {
                if (r.error === 'ERROR') {
                    info.text(r.value + " not exists");
                }
            });
        }
    });

    $('#pkg-upload').click(function () {

        function info_view(color, s) {
            $('#upload-info').empty().append('<span style="color: ' + color + ';">' + s + '</span>');
        }

        let fc = $('#pkg-file-bin');
        let val = fc.val();
        if (!val) {
            info_view('darkred', '没有选择文件');
            return;
        }

        let data = fc.get(0).files[0];

        let form_data = new FormData();
        form_data.append("pkg-file-bin", data);

        form_data.append('pkg-name-type', $('#pkg-name-type').val());

        let version = $('#pkg-version').val();
        if (!version.match(/[0-9]+(\.[0-9]+){1,3}/)) {
            info_view('darkred', '版本号错误');
            return;
        }
        form_data.append('pkg-version', version);

        let description = $('#pkg-description').val();
        if (!description) {
            info_view('darkred', '版本描述不能为空');
            return;
        }
        form_data.append('pkg-description', description);


        form_data.append('pkg-doc-url', $('#pkg-doc-url').val());

        form_data.append('pkg-compatible', $('#pkg-compatible').val());

        let fc2 = $('#pkg-file-attach');
        let val2 = fc2.val();
        if (val2) {
            let data2 = fc2.get(0).files[0];
            form_data.append("pkg-file-attach", data2);
        }

        let user = $('#pkg-user').val();
        if (!user) {
            info_view('darkred', '用户不能为空');
            return;
        }
        form_data.append('pkg-user', user);

        let password = $('#pkg-password').val();
        if (!password) {
            info_view('darkred', '密码不能为空');
            return;
        }
        form_data.append('pkg-password', password);

        $.ajax({
            type: "POST",
            url: <?php
            echo "\"{$_SERVER['SCRIPT_NAME']}\"";
            ?>,
            xhr: function () {  // Custom XMLHttpRequest
                let myXhr = $.ajaxSettings.xhr();
                if (myXhr.upload) { // Check if upload property exists
                    myXhr.upload.addEventListener('progress', function (e) {
                        if (e.lengthComputable) {
                            if (e.total > 0) {
                                let percent = Math.floor(e.loaded * 100 / e.total);
                                info_view('green', percent + "%");
                            }
                        }
                    }, false); // For handling the progress of the upload
                }
                return myXhr;
            },
            mimeTypes: "multipart/form-data",
            contentType: false,
            cache: false,
            processData: false,
            data: form_data,
            success: function (r2) {
                if (r2.error === 'OK') {
                    info_view('darkgreen', '成功...');
                    location.reload();
                } else {
                    info_view('darkred', r2.value);
                }
            },
            error: function () {
                info_view('darkred', '错误');
            }
        });
    });
</script>
</body>
</html>

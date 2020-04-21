<?php

define('LOG_PATH', '/var/log/version');
define('LOG_MAX_SIZE', 4096);

class VIo
{
    const CONF_FILE_EXT = '.__version';

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
            $s = implode(",", $msg);
        } else if (is_object($msg)) {
            $s = json_encode($msg, JSON_UNESCAPED_SLASHES);
        } else {
            $s = strval($msg);
        }

        $len = strlen($s);
        if ($len > LOG_MAX_SIZE) {
            $s = substr($s, 0, LOG_MAX_SIZE) . '<' . ($len - LOG_MAX_SIZE) . ' more>';
        }

        @error_log("{$tu}.{$ms} : {$s}" . PHP_EOL, 3, $file);
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
        $s = ";auto save at " . date(DATE_ATOM) . PHP_EOL;
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
}

class VItem extends VIo
{
    private $path;
    private $bin;
    private $configure;
    private $dirty = false;


    public function __construct($path, $bin)
    {
        $this->path = $path;
        $this->bin = $bin;

        $this->configure = $this->readConfigure($this->path . DIRECTORY_SEPARATOR . $this->bin . self::CONF_FILE_EXT);
        if (empty($this->getConfig('version'))) {
            preg_match('/(\d+\.){1,3}\d+/', $bin, $match_array);
            if (is_array($match_array) && count($match_array) > 0) {
                $this->setConfig('version', $match_array[0]);
                $this->Logger("get version {$match_array[0]} from $bin");
            } else {
                $this->Logger("can't get version from $bin");
            }
        }

        if (empty($this->getConfig('release'))) {
            $release = $this->getFileCTime($path . DIRECTORY_SEPARATOR . $bin);
            $this->setConfig('release', $release);
        }
    }

    public function __destruct()
    {
        if ($this->dirty) {
            $this->saveConfigure($this->path . DIRECTORY_SEPARATOR . $this->bin . self::CONF_FILE_EXT, $this->configure);
        }
    }

    public function getConfig($item)
    {
        if (is_array($this->configure) && isset($this->configure[$item])) {
            return $this->configure[$item];
        }
        return false;
    }

    public function getBin()
    {
        return $this->bin;
    }

    public function setConfig($item, $value)
    {
        $this->Logger("set version {$this->path}/{$this->bin}:$item=$value");
        $this->configure[$item] = $value;
        $this->dirty = true;
    }

    public function dump()
    {
        echo "<dt><dl>{$this->bin}</dl>";
        print_r(json_encode($this->configure, JSON_UNESCAPED_SLASHES));
        echo "</dt>";
    }

    public function getVersionValue()
    {
        $ver = $this->getConfig("version");
        $a = preg_split("/\./", $ver);
        $n = count($a);
        $val = 0;
        for ($i = 0; $i < 4; $i++) {
            $val <<= 8;
            if ($i < $n) {
                $val |= intval($a[$i]);
            }
        }
        return $val;
    }

    public function getWebPath()
    {
        $path1 = $_SERVER['PHP_SELF'];
        $pi1 = pathinfo($path1);

        $pi2 = pathinfo($this->path);

        return $pi1['dirname'] . DIRECTORY_SEPARATOR . $pi2['basename'] . DIRECTORY_SEPARATOR . $this->bin;
    }
}

class VDir extends VIo
{
    private $path;
    private $description;
    private $list = array();

    public function __construct($path)
    {
        $this->path = $path;
        $ext_len = strlen(self::CONF_FILE_EXT);

        $it = $this->readConfigure($this->path . DIRECTORY_SEPARATOR . self::CONF_FILE_EXT);
        if (isset($it['description'])) {
            $this->description = $it['description'];
        } else {
            $pi = pathinfo($path);
            $this->description[] = $pi['basename'];
        }

        $handle = opendir($this->path);
        if ($handle !== false) {
            $temp_array = [];
            while (($bin = readdir($handle)) !== false) {
                if (substr($bin, 0, 1) == '.') {
                    continue;
                }

                if (is_file($this->path . '/' . $bin) &&
                    substr($bin, -$ext_len) != self::CONF_FILE_EXT
                ) {
                    $this->Logger("$bin found");
                    $it = new VItem($this->path, $bin);
                    $val = $it->getVersionValue();

                    if (empty($val)) {
                        $temp_array[] = $it;
                        $this->Logger("version val=EMPTY for " . $it->getBin());
                    } else {
                        if (isset($temp_array[$val])) {
                            $this->Logger("ERROR $val:");
                        }
                        $temp_array[$val] = $it;
                        $this->Logger("version val=$val for " . $it->getBin());
                    }
                }
            }
            closedir($handle);

            $this->list = [];
            $key_array = array_keys($temp_array);
            rsort($key_array);
            foreach ($key_array as $index) {
                $version = $temp_array[$index]->getConfig('version');
                $this->list[$version] = $temp_array[$index];
            }
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
        return $this->list;
    }

    public function dump()
    {
        echo "<hr><div><p>{$this->path} : {$this->description}</p>";
        foreach ($this->list as $it) {
            $it->dump();
        }
        echo "</div>";
    }
}

class VRoot
{
    private $path;
    private $dir_list = array();

    public function __construct()
    {
        $pi = pathinfo($_SERVER['SCRIPT_FILENAME']);
        $this->path = $pi['dirname'];

        $handle = opendir($this->path);
        if ($handle !== false) {
            while (($dir = readdir($handle)) !== false) {
                if (substr($dir, 0, 1) == '.') {
                    continue;
                }
                if (is_dir($dir)) {
                    $this->dir_list[$dir] = new VDir($this->path . DIRECTORY_SEPARATOR . $dir);
                }
            }
            closedir($handle);
        }
    }

    public function getDirArray()
    {
        return $this->dir_list;
    }

    public function dump()
    {
        foreach ($this->dir_list as $it) {
            $it->dump();
        }
    }
}

session_start();
$root = new VRoot();
?>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css">
    <title>版本发布测试</title>
    <style>
        body {
            background-color: #f8f8f8;
        }

        .container {
            background-color: white;
        }

        #version-list {
            padding: 20px;
            top: 70px;
            left: 20px;
            width: 200px;
            height: 800px;
            overflow-x: hidden;
            overflow-y: auto;
            position: absolute;
        }

        .version-title {
            font-size: 160%;
            margin: 40px 20px -15px 4px;
        }

        .version-dl {

        }

        .version-dt {
            font-size: 14pt;
            font-weight: 200;
            font-style: italic;
            color: gray;
        }

        .version-release {
            font-size: 10pt;
            font-style: italic;
            color: gray;
            align-content: baseline;
            float: right;
        }

        .version-dd {
            margin-left: 90px;
        }

        .version-dd > ul {
            margin-left: -40px;
        }

        #form-add-version {
            padding: 15px;
            width: 900px;
        }
    </style>
</head>
<body>
<div class="container">
    <div>
        <nav class="navbar navbar-fixed-top">
            <div class="container">
                <ul class="nav nav-tabs">
                    <?php
                    $href = $_SERVER['PHP_SELF'];
                    $active_menu = $_GET['menu'] ?? false;

                    $dir_array = $root->getDirArray();
                    foreach ($dir_array as $dir => $item) {
                        if ($active_menu == false) {
                            $active_menu = $dir;
                        }

                        if ($item instanceof VDir) {
                            $label = $item->getDescription(0);
                            $version_list = $item->getVersionList();
                        } else {
                            $label = $dir;
                        }

                        if ($active_menu == $dir) {
                            echo "<li role='presentation' class='active'><a>{$label}</a></li>";
                        } else {
                            echo "<li role='presentation'> <a href='$href?menu={$dir}'>{$label}</a></li>";
                        }
                    }
                    ?>
                </ul>
            </div>
        </nav>
    </div>
    <div>
        <br><br><br>
        <div>
            <button class="btn btn-link" id="btn-view-version" style="float: right">增加</button>
            <button class="btn btn-link" id="btn-save-version" style="float: right">提交</button>
        </div>
        <div class="well" id="form-add-version">
            <div>
                <p>
                    敬请期待
                </p>
                <p>
                    敬请期待
                </p>
            </div>
        </div>
    </div>
    <div class="raw">
        <div class="col-md-10">
            <?php
            if (isset($version_list)) {
                foreach ($version_list as $item) {
                    if ($item instanceof VItem) {
                        $version = $item->getConfig('version');
                        $release = $item->getConfig('release');
                        $description = $item->getConfig('description');
                        if (is_string($description)) {
                            $a[] = $description;
                        } else if (is_array($a = $description)) {
                            $a = $description;
                        } else {
                            $a = ['(EMPTY)'];
                        }
                        $s = "<li>" . implode('</li><li>', $a) . '</li>';

                        echo "<div class='version-title'><a name='$version'>$version</a><div class='version-release'>$release</div></div><hr>";

                        $url = $item->getWebPath();
                        echo "<dl class='version-dl'><dt class='version-dt'>Download</dt>" .
                            "<dd class='version-dd'><a href='$url'>下载</a></dd></dl>";

                        echo "<dl class='version-dl'><dt class='version-dt'>Description</dt>" .
                            "<dd class='version-dd'><ul>$s</ul></dd></dl>";


                        echo "<dl class='version-dl'><dt class='version-dt'>Document</dt>" .
                            "<dd class='version-dd'>document</dd></dl>";

                        echo "<br>";
                    }
                }
            }
            ?>
        </div>
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
            $('#btn-save-version').show();
        } else {
            it.hide();
            $(this).text('增加');
            $('#btn-save-version').hide();
        }
    }).click();
</script>
</body>
</html>

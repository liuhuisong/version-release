<?php

require_once 'VIo.php';
require_once 'VItem.php';

class VDir extends VIo
{
    private $os_path;
    private $description;
    private $val_2_version_array = array();//val=>ver

    //for find bin with version, prevent from conflict
    private $map_item = array();

    //for user can post bin
    private $user_list;

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

        $this->user_list = false;
        if (isset($it['user'])) {
            if (is_array($it['user'])) {
                $this->user_list = $it['user'];
            } else if (is_string($it['user'])) {
                $this->user_list = preg_split("/[\s,;:]/", $it['user']);
            }
        }

        if (isset($it['ext'])) {
            if (is_string($it['ext'])) {
                $ext_list = [];
                $a = explode(",", $it['ext']);
                foreach ($a as $it) {
                    if (is_string($it) && !empty(($it2 = trim($it)))) {
                        $ext_list[] = $it2;
                    }
                }
            } else if (is_array($it['ext'])) {
                $ext_list = [];
                foreach ($it['ext'] as $it) {
                    if (is_string($it) && !empty(($it2 = trim($it)))) {
                        $ext_list[] = $it2;
                    }
                }
            }
        }

        if (empty($ext_list)) {
            $ext_list = CONF_PKG_DEF_EXT;
        }

        $handle = opendir($this->os_path);
        if ($handle !== false) {
            $n = 0;
            while (($bin = readdir($handle)) !== false) {
                if (substr($bin, 0, 1) == '.') {
                    continue;
                }

                if (strstr($bin, self::CONF_DOC_EXT . '.') != false) {
                    continue;
                }

                //filter by ext
                $a = explode(".", $bin);
                if (empty($a)) {
                    continue;
                }

                $ext_name = end($a);
                if (!in_array($ext_name, $ext_list)) {
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

    public
    function getDescription($index)
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
    public
    function getVersionList()
    {
        return array_values($this->val_2_version_array);
    }

    public
    function hasUser($user)
    {
        return is_array($this->user_list) && in_array($user, $this->user_list);
    }

    /***
     * @param $version
     * @param $name_type
     * @param $file_bin
     * @param $file_attach
     * @param $config
     * @return string|VItem error if string, else successful
     */
    public
    function addItem($version, $name_type, $file_bin, $file_attach, $config)
    {
        if (!isset($config['user']) || $this->hasUser($config['user'])) {
            return "user denied for $name_type";
        }

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
    public
    function findItemByBinName($bin_name)
    {
        if (isset($this->map_item[$bin_name])) {
            return $this->map_item[$bin_name];
        }

        return false;
    }

    public
    function dump()
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

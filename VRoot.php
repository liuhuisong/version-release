<?php

require_once 'VIo.php';
require_once 'VItem.php';
require_once 'VDir.php';

/**
 * Class VRoot
 * the version ROOT object
 */
class VRoot extends VIO
{
    private $os_path;
    private $dir_list = array();

    /**
     * VRoot constructor.
     */
    public function __construct()
    {
        $pi = pathinfo($_SERVER['SCRIPT_FILENAME']);
        $this->os_path = $pi['dirname'];

        $this->loadDirectory();
    }

    public function loadDirectory()
    {
        $this->dir_list=array();//init

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

    /**
     * @return array
     * project dir array
     */
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
     * used by add version item
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

    /**
     * @param $bin_name
     * @return bool|VItem
     * location version item by bin name, it may be high cost
     */
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

    /**
     * @return mixed
     * for test
     */
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
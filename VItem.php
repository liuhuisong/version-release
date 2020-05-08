<?php

require_once 'VIo.php';

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
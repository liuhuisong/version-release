<?php

require_once 'v_def.php';

/***
 * Class VIo
 * IO, and Logger
 */
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

    /**
     * @param $file
     * @return array|bool|false
     *
     */
    public function readConfigure($file)
    {
        $lines = @parse_ini_file($file);
        if (empty($lines)) {
            $this->Logger("$file read failed,return false");
            return false;
        }

        return $lines;
    }

    /**
     * @param $filename
     * @return bool|false|string
     * get created time of the file
     */
    public function getFileCTime($filename)
    {
        $stamp = @filectime($filename);
        if (empty($stamp)) {
            return false;
        }

        return date("Y-m-d H:i:s", $stamp);
    }

    /**
     * @param $file
     * @param $data
     * save configure to file
     */
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

    /**
     * @param $error
     * @param $value
     * @return string
     * return OK,ERROR, or else
     */
    public function responds($error, $value)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('error' => $error, 'value' => $value));
        return $error;
    }

    /**
     * @param $ver
     * @return int
     * for version sort
     */
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

    /**
     * @param $bin
     * @return bool| string
     *
     * version a.b[.c[.d]], a,b,c,d <255
     */
    public function parseVersion($bin)
    {
        $regex = '/((?:\d+\.){1,3}\d+)(-[a-z]*)?/';
        preg_match($regex, $bin, $match_array);
        if (is_array($match_array)) {
            $n = count($match_array);
            if ($n == 2) {
                return $match_array[1];
            } else if ($n == 3) {
                return $match_array[1];
            }
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
            if (($n = count($array)) > 2) {
                $ext2 = strtolower($array[$n - 2]);
                if (in_array($ext2, CONF_PKG_DEF_EXT)) {
                    $ext = $ext2 . '.' . $ext;
                }
            }
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
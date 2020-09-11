<?php

require_once 'VIo.php';
require_once 'VItem.php';
require_once 'VDir.php';
require_once 'VRoot.php';

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
    $suffix = $_POST['version-suffix'];

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

    $user = $_POST['pkg-user'];
    $password = $_POST['pkg-password'];

    if (!$root->onUserAuth($user, $password)) {
        return $vio->responds('ERROR', 'user/password error');
    }

    $ret = $root->addItemByName($name_type, $_FILES['pkg-file-bin'],
        (isset($_FILES['pkg-file-attach']) ? $_FILES['pkg-file-attach'] : false),
        array(
            'user' => $_POST['pkg-user'],
            'version' => $version,
            'description' => $des_array,
            'document' => $document,
            'compatible' => $compatible,
            'version-suffix' => $suffix));

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
    <div id="menu-bar">
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

                        $new_string = '&nbsp;';

                        if ($item instanceof VDir) {
                            $label = $item->getDescription(0);
                            $version_list = $item->getVersionList();
                            if ($active_menu == $dir) {
                                $active_version_list = $version_list;
                            }

                            if (!empty($version_list)) {
                                $last_it = $version_list[0];
                                $time_string = $last_it->getConfig('release');
                                try {
                                    $date = new DateTime($time_string);
                                    $now = new DateTime();
                                    $a = $now->diff($date);
                                    $day = $a->days;
                                    if ($day < 10) {
                                        $new_string = '<span class="glyphicon glyphicon-star" style="color:#ff0000"></span>';
                                    } else if ($day < 20) {
                                        $new_string = '<span class="glyphicon glyphicon-star" style="color:#761d14"></span>';
                                    } else if ($day < 45) {
                                        $new_string = '<span class="glyphicon glyphicon-star" style="color:#936161"></span>';
                                    }
                                } catch (Exception $e) {
                                }
                            }
                        } else {
                            $label = $dir;
                        }

                        if ($active_menu == $dir) {
                            echo "<li role='presentation' class='active'><a>{$label} {$new_string}</a></li>";
                        } else {
                            echo "<li role='presentation'> <a href='$href?menu={$dir}'>{$label} {$new_string}</a></li>";
                        }
                    }

                    $_SESSION['bin-menu'] = $active_menu;
                    ?>
                </ul>
            </div>
        </nav>
    </div>
    <div class="row" id="menu-container">
        <div>
            <button class="btn btn-link" id="btn-view-version" style="float: right;margin-right: 8px">增加</button>
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
                        <select class="form-control" id="version-suffix">
                            <option value="dev" selected="selected">dev</option>
                            <option value="alpha">alpha,a</option>
                            <option value="beta">beta,b</option>
                            <option value="rc">RC,rc</option>
                            <option value="pl">pl,p</option>
                        </select>
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
                    <label class="col-sm-2 control-label" for="pkg-doc-url">文档</label>
                    <div class="col-sm-8">
                        <input type="url" class="form-control" id="pkg-doc-url" placeholder="document url">
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
                        $md5 = $item->getConfig('md5');

                        if (!empty($url) && $bin_size > 0) {
                            $n = number_format($bin_size);
                            echo "<div class='row'><div class='col-md-1'><div class='version-dt'>下载</div></div>" .
                                "<div class='col-md-9'><div class='version-dd'><a href='$url'>$bin</a>" .
                                "<div class='text-muted small'>Size : $n bytes</div>" .
                                "<div class='text-muted small'>MD5  : " . $md5 . "</div></div></div>";

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
    let h = $('#menu-bar>nav').outerHeight();
    $('#menu-container').css('margin-top', h);

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

        let suffix = $('#version-suffix').val();
        form_data.append('version-suffix', suffix);

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

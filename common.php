<?php

global $slash;
global $drive;

global $EnvConfigs;
$EnvConfigs = [
    // 1 inner, 0 common
    // 1 showed/enableEdit, 0 hidden/disableEdit
    // 1 base64 to save, 0 not base64
    'APIKey'            => 0b000, // used in heroku.
    'SecretId'          => 0b000, // used in SCF/CFC.
    'SecretKey'         => 0b000, // used in SCF/CFC.
    'AccessKeyID'       => 0b000, // used in FC.
    'AccessKeySecret'   => 0b000, // used in FC.
    'HW_urn'            => 0b000, // used in FG.
    'HW_key'            => 0b000, // used in FG.
    'HW_secret'         => 0b000, // used in FG.
    'HerokuappId'       => 0b000, // used in heroku.

    'admin'             => 0b000,
    'adminloginpage'    => 0b010,
    'disktag'           => 0b000,
    'timezone'          => 0b010,
    'sitename'          => 0b011,

    'Driver'            => 0b100,
    'refresh_token'     => 0b100,
    'token_expires'     => 0b100,
    'tenant_id'         => 0b100,
    'client_id'         => 0b100,
    'client_resid'      => 0b100,
    'client_secret'     => 0b101,
    'defaultCountry'    => 0b100,
    'diskname'          => 0b111,
];

function isCommonEnv($str)
{
    global $EnvConfigs;
    if (isset($EnvConfigs[$str])) return ( $EnvConfigs[$str] & 0b100 ) ? false : true;
    else return null;
}

function isInnerEnv($str)
{
    global $EnvConfigs;
    if (isset($EnvConfigs[$str])) return ( $EnvConfigs[$str] & 0b100 ) ? true : false;
    else return null;
}

function isShowedEnv($str)
{
    global $EnvConfigs;
    if (isset($EnvConfigs[$str])) return ( $EnvConfigs[$str] & 0b010 ) ? true : false;
    else return null;
}

function isBase64Env($str)
{
    global $EnvConfigs;
    if (isset($EnvConfigs[$str])) return ( $EnvConfigs[$str] & 0b001 ) ? true : false;
    else return null;
}

function main($path)
{
    global $constStr;
    global $slash;
    global $drive;

    $drive = null;
    $slash = '/';
    if (strpos(__DIR__, ':')) $slash = '\\';
    $_SERVER['php_starttime'] = microtime(true);
    $path = path_format($path);
    $_SERVER['PHP_SELF'] = path_format($_SERVER['base_path'] . $path);
    if (getConfig('forceHttps')&&$_SERVER['REQUEST_SCHEME']=='http') {
        if ($_GET) {
            $tmp = '';
            foreach ($_GET as $k => $v) {
                if ($v===true) $tmp .= '&' . $k;
                else $tmp .= '&' . $k . '=' . $v;
            }
            $tmp = substr($tmp, 1);
            if ($tmp!='') $param = '?' . $tmp;
        }
        return output('visit via https.', 302, [ 'Location' => 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . $param ]);
    }
    if (in_array($_SERVER['firstacceptlanguage'], array_keys($constStr['languages']))) {
        $constStr['language'] = $_SERVER['firstacceptlanguage'];
    } else {
        $prelang = splitfirst($_SERVER['firstacceptlanguage'], '-')[0];
        foreach ( array_keys($constStr['languages']) as $lang) {
            if ($prelang == splitfirst($lang, '-')[0]) {
                $constStr['language'] = $lang;
                break;
            }
        }
    }
    if (isset($_COOKIE['language'])&&$_COOKIE['language']!='') $constStr['language'] = $_COOKIE['language'];
    if ($constStr['language']=='') $constStr['language'] = 'en-us';
    $_SERVER['language'] = $constStr['language'];
    $_SERVER['timezone'] = getConfig('timezone');
    if (isset($_COOKIE['timezone'])&&$_COOKIE['timezone']!='') $_SERVER['timezone'] = $_COOKIE['timezone'];
    if ($_SERVER['timezone']=='') $_SERVER['timezone'] = 0;

    if (substr($path, 0, 7)=='/layui/' || substr($path, 0, 7)=='/files/') {
        $filename = '.' . str_replace('/', $slash, $path);
        $mimetype = 'text/plain; charset=utf-8';
        if (substr($path, -3)=='.js') $mimetype = 'application/javascript';
        if (substr($path, -4)=='.css') $mimetype = 'text/css';
        if (substr($path, -6)=='.woff2') $mimetype = 'font/woff2';
        if (substr($path, -4)=='.png') $mimetype = 'image/png';
        return output(
            file_get_contents($filename),
            200,
            [ 'Content-Type' => $mimetype]
        );
    }

    if (getConfig('admin')=='') return install();
    if (getConfig('adminloginpage')=='') {
        $adminloginpage = 'admin';
    } else {
        $adminloginpage = getConfig('adminloginpage');
    }
    //if (isset($_GET[$adminloginpage])) {
        //if (isset($_GET['preview'])) {
        //    $url = $_SERVER['PHP_SELF'] . '?preview';
        //} else {
            $url = path_format($_SERVER['PHP_SELF'] . '/');
        //}
        if (isset($_POST['password1'])) {
            $compareresult = compareadminsha1($_POST['password1'], $_POST['timestamp'], getConfig('admin'));
            if ($compareresult=='') {
                return adminform('admin', adminpass2cookie('admin', getConfig('admin')), $url);
            } else return adminform($compareresult);
        }// else return adminform();
    //}
    if ( isset($_COOKIE['admin'])&&compareadminmd5($_COOKIE['admin'], 'admin', getConfig('admin')) ) {
        $_SERVER['admin']=1;
        //$_SERVER['needUpdate'] = needUpdate();
    } else {
        $_SERVER['admin']=0;
    }

    // login
    if (!$_SERVER['admin']) return adminform();

    if (isset($_GET['setup']))
        if ($_SERVER['admin']) {
            // setup Environments. 设置，对环境变量操作
            return EnvOpt($_SERVER['needUpdate']);
        } else {
            $url = path_format($_SERVER['PHP_SELF'] . '/');
            return output('<script>alert(\''.getconstStr('SetSecretsFirst').'\');</script>', 302, [ 'Location' => $url ]);
        }

    $_SERVER['sitename'] = getConfig('sitename');
    if (empty($_SERVER['sitename'])) $_SERVER['sitename'] = getconstStr('defaultSitename');
    $_SERVER['base_disk_path'] = $_SERVER['base_path'];

    // Add disk
    if (isset($_GET['AddDisk'])) {
        if ($_SERVER['admin']) {
            if (!class_exists($_GET['AddDisk'])) require 'disk' . $slash . $_GET['AddDisk'] . '.php';
                $drive = new $_GET['AddDisk']($_GET['disktag']);
                return $drive->AddDisk();
        } else {
            $url = $_SERVER['PHP_SELF'];
            if ($_GET) {
                $tmp = null;
                $tmp = '';
                foreach ($_GET as $k => $v) {
                    if ($k!='setup') {
                        if ($v===true) $tmp .= '&' . $k;
                        else $tmp .= '&' . $k . '=' . $v;
                    }
                }
                $tmp = substr($tmp, 1);
                if ($tmp!='') $url .= '?' . $tmp;
            }
            return output('<script>alert(\'' . getconstStr('SetSecretsFirst') . '\');</script>', 302, [ 'Location' => $url ]);
        }
    }

    $_SERVER['disktag'] = $_GET['account'];
    if (!$_SERVER['disktag']) $_SERVER['disktag'] = '';
    $disktags = explode("|", getConfig('disktag'));
    //if ($_SERVER['disktag']==''||!in_array($_SERVER['disktag'], $disktags)) {
    //    return output('<script>alert(\'to fisrt disk\');</script>', 302, [ 'Location' => '?account=' . $disktags[0] ]);
    //}

    //echo "1" . $_SERVER['disktag'] . PHP_EOL;
    if (driveisfine($drive, $_SERVER['disktag'])) {
        //echo "2" . $drive->disktag . PHP_EOL;
        // Operate
        if ($_SERVER['admin']) {
            $tmp = adminoperate();
            if ($tmp['statusCode'] > 0) {
                return $tmp;
            }
        }
    }
    //echo "3" . $drive->disktag . PHP_EOL;
    return render_list($drive);
}

function driveisfine(&$drive, $tag = '')
{
    global $slash;
    //$disktype = getConfig('Driver', $tag);
    //if (!$disktype) return false;
    if (!$drive) {
        $disktype = 'Onedrive';
        if (!class_exists($disktype)) require 'disk' . $slash . $disktype . '.php';
        $drive = new $disktype($tag);
    }// else return false;
    if ($drive->isfine()) return true;
    else return false;
}

function baseclassofdrive($d = null)
{
    global $drive;
    if (!$d) $dr = $drive;
    else $dr = $d;
    if (!$dr) return false;
    return $dr->show_base_class();
}

function extendShow_diskenv($drive)
{
    if (!$drive) return [];
    return $drive->ext_show_innerenv();
}

function adminpass2cookie($name, $pass)
{
    $timestamp = time() + 30*60;
    return md5($name . ':' . md5($pass) . '@' . $timestamp) . "(" . $timestamp . ")";
}
function compareadminmd5($admincookie, $name, $pass)
{
    $c = splitfirst($admincookie, '(');
    $c_md5 = $c[0];
    $c_time = substr($c[1], 0, -1);
    if (!is_numeric($c_time)) return false;
    if (time() > $c_time) return false;
    if (md5($name . ':' . md5($pass) . '@' . $c_time) == $c_md5) return true;
    else return false;
}

function compareadminsha1($adminsha1, $timestamp, $pass)
{
    if (!is_numeric($timestamp)) return 'Timestamp not Number';
    if (abs(time()-$timestamp) > 5*60) {
        date_default_timezone_set('UTC');
        return 'The timestamp in server is ' . time() . ' (' . date("Y-m-d H:i:s") . ' UTC),<br>and your posted timestamp is ' . $timestamp . ' (' . date("Y-m-d H:i:s", $timestamp) . ' UTC)';
    }
    if ($adminsha1 == sha1($timestamp . $pass)) return '';
    else return 'Error password';
}

function getcache($str, $disktag = '')
{
    $cache = filecache($disktag);
    return $cache->fetch($str);
}

function savecache($key, $value, $disktag = '', $exp = 1800)
{
    $cache = filecache($disktag);
    return $cache->save($key, $value, $exp);
}

function filecache($disktag)
{
    $dir = sys_get_temp_dir();
    if (!is_writable($dir)) {
        $tmp = __DIR__ . '/tmp/';
        if (file_exists($tmp)) {
            if ( is_writable($tmp) ) $dir = $tmp;
        } elseif ( mkdir($tmp) ) $dir = $tmp;
    }
    $tag = __DIR__ . '/OneManager/' . $disktag;
    while (strpos($tag, '/')>-1) $tag = str_replace('/', '_', $tag);
    if (strpos($tag, ':')>-1) {
        $tag = str_replace(':', '_', $tag);
        $tag = str_replace('\\', '_', $tag);
    }
    // error_log1('DIR:' . $dir . ' TAG: ' . $tag);
    $cache = new \Doctrine\Common\Cache\FilesystemCache($dir, $tag);
    return $cache;
}

function sortConfig(&$arr)
{
    ksort($arr);

    $tags = explode('|', $arr['disktag']);
    unset($arr['disktag']);
    if ($tags[0]!='') {
        foreach($tags as $tag) {
            $disks[$tag] = $arr[$tag];
            unset($arr[$tag]);
        }
        $arr['disktag'] = implode('|', $tags);
        foreach($disks as $k => $v) {
            $arr[$k] = $v;
        }
    }

    return $arr;
}

function getconstStr($str)
{
    global $constStr;
    if ($constStr[$str][$constStr['language']]!='') return $constStr[$str][$constStr['language']];
    return $constStr[$str]['en-us'];
}

function path_format($path)
{
    $path = '/' . $path;
    while (strpos($path, '//') !== FALSE) {
        $path = str_replace('//', '/', $path);
    }
    return $path;
}

function spurlencode($str, $split='')
{
    $str = str_replace(' ', '%20', $str);
    $tmp='';
    if ($split!='') {
        $tmparr=explode($split, $str);
        foreach ($tmparr as $str1) {
            $tmp .= urlencode($str1) . $split;
        }
        $tmp = substr($tmp, 0, strlen($tmp)-strlen($split));
    } else {
        $tmp = urlencode($str);
    }
    $tmp = str_replace('%2520', '%20', $tmp);
    $tmp = str_replace('%26amp%3B', '&', $tmp);
    return $tmp;
}

function base64y_encode($str)
{
    $str = base64_encode($str);
    while (substr($str,-1)=='=') $str=substr($str,0,-1);
    while (strpos($str, '+')!==false) $str = str_replace('+', '-', $str);
    while (strpos($str, '/')!==false) $str = str_replace('/', '_', $str);
    return $str;
}

function base64y_decode($str)
{
    while (strpos($str, '_')!==false) $str = str_replace('_', '/', $str);
    while (strpos($str, '-')!==false) $str = str_replace('-', '+', $str);
    while (strlen($str)%4) $str .= '=';
    $str = base64_decode($str);
    //if (strpos($str, '%')!==false) $str = urldecode($str);
    return $str;
}

function error_log1($str)
{
    error_log($str);
}

function array_value_isnot_null($arr)
{
    return $arr!=='';
}

function curl($method, $url, $data = '', $headers = [], $returnheader = 0, $location = 0)
{
    //if (!isset($headers['Accept'])) $headers['Accept'] = '*/*';
    //if (!isset($headers['Referer'])) $headers['Referer'] = $url;
    //if (!isset($headers['Content-Type'])) $headers['Content-Type'] = 'application/x-www-form-urlencoded';
    if (!isset($headers['Content-Type'])&&!isset($headers['content-type'])) $headers['Content-Type'] = '';
    $sendHeaders = array();
    foreach ($headers as $headerName => $headerVal) {
        $sendHeaders[] = $headerName . ': ' . $headerVal;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,$method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, $returnheader);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $sendHeaders);
    if ($location) curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    //$response['body'] = curl_exec($ch);
    if ($returnheader) {
        list($returnhead, $response['body']) = explode("\r\n\r\n", curl_exec($ch));
        foreach (explode("\r\n", $returnhead) as $head) {
            $tmp = explode(': ', $head);
            $heads[$tmp[0]] = $tmp[1];
        }
        $response['returnhead'] = $heads;
    } else {
        $response['body'] = curl_exec($ch);
    }
    $response['stat'] = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $response;
}

function get_timezone($timezone = '8')
{
    global $timezones;
    if ($timezone=='') $timezone = '8';
    return $timezones[$timezone];
}

function message($message, $title = 'Message', $statusCode = 200)
{
    return output('
<html lang="' . $_SERVER['language'] . '">
<html>
    <meta charset=utf-8>
    <meta name=viewport content="width=device-width,initial-scale=1">
    <body>
        <a href="' . $_SERVER['base_path'] . '">' . getconstStr('Back') . getconstStr('Home') . '</a>
        <h1>' . $title . '</h1>
        <p>

' . $message . '

        </p>
    </body>
</html>
', $statusCode);
}

function needUpdate()
{
    global $slash;
    $current_version = file_get_contents(__DIR__ . $slash . 'version');
    $current_ver = substr($current_version, strpos($current_version, '.')+1);
    $current_ver = explode(urldecode('%0A'),$current_ver)[0];
    $current_ver = explode(urldecode('%0D'),$current_ver)[0];
    $split = splitfirst($current_version, '.' . $current_ver)[0] . '.' . $current_ver;
    if (!($github_version = getcache('github_version'))) {
        $tmp = curl('GET', 'https://raw.githubusercontent.com/qkqpttgf/OneManager-php/master/version');
        if ($tmp['stat']==0) return 0;
        $github_version = $tmp['body'];
        savecache('github_version', $github_version);
    }
    $github_ver = substr($github_version, strpos($github_version, '.')+1);
    $github_ver = explode(urldecode('%0A'),$github_ver)[0];
    $github_ver = explode(urldecode('%0D'),$github_ver)[0];
    if ($current_ver != $github_ver) {
        //$_SERVER['github_version'] = $github_version;
        $_SERVER['github_ver_new'] = splitfirst($github_version, $split)[0];
        $_SERVER['github_ver_old'] = splitfirst($github_version, $_SERVER['github_ver_new'])[1];
        return 1;
    }
    return 0;
}

function response($code,$msg,$data = [],$count = false) {
    $json = [
        'code'=>$code,
        'msg'=>$msg,
    ];
    if($count !== false){
        $json['count'] = $count;
    }
    if(!empty($data)){
        $json['data'] = $data;
    }

    return json_encode($json);
}

function output($body, $statusCode = 200, $headers = ['Content-Type' => 'text/html'], $isBase64Encoded = false)
{
    if (isset($_SERVER['Set-Cookie'])) $headers['Set-Cookie'] = $_SERVER['Set-Cookie'];
    if (baseclassofdrive()=='Aliyundrive') $headers['Referrer-Policy'] = 'no-referrer';
    //$headers['Referrer-Policy'] = 'same-origin';
    //$headers['X-Frame-Options'] = 'sameorigin';
    return [
        'isBase64Encoded' => $isBase64Encoded,
        'statusCode' => $statusCode,
        'headers' => $headers,
        'body' => $body
    ];
}

function size_format($byte)
{
    $i = 0;
    while (abs($byte) >= 1024) {
        $byte = $byte / 1024;
        $i++;
        if ($i == 4) break;
    }
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $ret = round($byte, 2);
    return ($ret . ' ' . $units[$i]);
}

function time_format($ISO)
{
    if ($ISO=='') return date('Y-m-d H:i:s');
    $ISO = str_replace('T', ' ', $ISO);
    $ISO = str_replace('Z', ' ', $ISO);
    return date('Y-m-d H:i:s',strtotime($ISO . " UTC"));
}

function adminform($name = '', $pass = '', $path = '')
{
    $html = '<html><head><title>' . getconstStr('AdminLogin') . '</title><meta charset=utf-8><meta name=viewport content="width=device-width,initial-scale=1"></head>';
    if ($name=='admin'&&$pass!='') {
        $html .= '<meta http-equiv="refresh" content="3;URL=' . $path . '">
        <body>' . getconstStr('LoginSuccess') . '</body></html>';
        $statusCode = 201;
        date_default_timezone_set('UTC');
        $_SERVER['Set-Cookie'] = $name . '=' . $pass . '; path=/; expires=' . date(DATE_COOKIE, strtotime('+30min'));
        return output($html, $statusCode);
    }
    $statusCode = 401;
    $html .= '
<body>
    <div>
    <center><h4>' . getconstStr('InputPassword') . '</h4>
    ' . $name . '
    <form action="" method="post" onsubmit="return sha1loginpass(this);">
        <div>
            <input id="password1" name="password1" type="password"/>
            <input name="timestamp" type="hidden"/>
            <input type="submit" value="' . getconstStr('Login') . '">
        </div>
    </form>
    </center>
    </div>
</body>';
    $html .= '
<script>
    document.getElementById("password1").focus();
    function sha1loginpass(f) {
        if (f.password1.value=="") return false;
        try {
            timestamp = new Date().getTime() + "";
            timestamp = timestamp.substr(0, timestamp.length-3);
            f.timestamp.value = timestamp;
            f.password1.value = sha1(timestamp + "" + f.password1.value);
            return true;
        } catch {
            alert("sha1.js not loaded.");
            return false;
        }
    }
</script>
<script src="https://cdn.bootcss.com/js-sha1/0.6.0/sha1.min.js"></script>';
    $html .= '</html>';
    return output($html, $statusCode);
}

function adminoperate()
{
    global $drive;
    global $license;
    $tmparr['statusCode'] = 0;

    if($_GET['a'] == 'getusers'){
        $data = $drive->getusers();
        if ($data['stat']!=200) return output(response(1,"Error",json_encode($data)));

        $skuId = null;
        foreach ($license as $k => $v) {
            $skuId[$v['skuid']] = $v['name'];
        }
        $value = json_decode($data['body'], true)['value'];
        foreach($value as $k => $v){
            if (!$v['assignedLicenses']) $value[$k]['sku'] .= '无许可';
            else foreach ($v['assignedLicenses'] as $k1 => $v1) {
                if($v1['skuId'] == ""){
                    $value[$k]['sku'] .= '无许可';
                } elseif (isset($skuId[$v1['skuId']])) {
                    if ($value[$k]['sku']!='') $value[$k]['sku'] .= ';';
                    $value[$k]['sku'] .= $skuId[$v1['skuId']];
                } else {
                    if ($value[$k]['sku']!='') $value[$k]['sku'] .= ';';
                    $value[$k]['sku'] .= '未知';
                };
            }
        }
        return output(response(0,"获取成员信息成功",$value,count($value)));
    }
    if ($_GET['a'] == 'admin_add_account') {
        $password = $_POST['password'];
        if ($password=='') $password = getRandomPass($_POST['add_user']);

        $request = [
            'username' => $_POST['add_user'],
            'firstname' => $_POST['firstname'],
            'lastname' => $_POST['lastname'],
            'domain' => $_POST['domain'],
            'password' => $password,
            'forceChangePassword' => $_POST['forceChangePassword'],
            'location' => $_POST['location'],
            
        ];

        $result = $drive->admin_create_user($request);
        if ($result['stat']==201) {
            $data['password'] = $password;
            $data['userPrincipalName'] = json_decode($result['body'], true)['userPrincipalName'];
            if ($_POST['sku']!='') {
                $result = $drive->addsubscribe($request['username'] . '@' . $request['domain'], $_POST['sku']);
                //error_log1('Addlin' . json_encode($result));
                if ($result['stat']==200) {
                    $data['msg'] = '创建成功，分配许可成功';
                    return output(response(0, json_encode($data))); //创建成功，分配许可成功
                } else {
                    $data['msg'] = '创建成功，分配许可失败';
                    return output(response(0, json_encode($data))); //创建成功，分配许可失败
                }
            }
            //error_log1('Nosku:' . $result['body']);
            $data['msg'] = '创建成功，无许可';
            return output(response(0, json_encode($data))); //创建成功，无许可
        } else {
            if ($result['stat']==400) {
                $data = json_decode($result['body'], true);
                //if (strpos('password', $data['error']['message'])) {
                    $data['password'] = $password;
                    return output(response($result['stat'], json_encode($data)));
                //}
            }
            return output(response($result['stat'], $result['body']));
        }
    }
    if ($_GET['a'] == 'admin_delete') {
        $result = $drive->accountdelete($_POST['email']);
        if ($result['stat']==204) return output(response(0, '删除 ' . $_POST['email'] . ' 成功'));
        return output(response($result['stat'], $result['body']));
    }
    if ($_GET['a'] == 'invitation_code_activeaccount') {
        $user_email = !empty($_POST['email']) ? $_POST['email'] : 0;
        $result = $drive->accountactive($user_email);
        //error_log1(json_encode($result));
        if ($result['stat']==204) {
            return output(response(0, $user_email . " 解锁成功"));
        } else {
            return output(response(1, $user_email . " 解锁失败"));
        }
    }
    if ($_GET['a'] == 'invitation_code_inactiveaccount') {
        $user_email = !empty($_POST['email']) ? $_POST['email'] : 0;
        $result = $drive->accountinactive($user_email);
        //error_log1(json_encode($result));
        if ($result['stat']==204) {
            return output(response(0, $user_email . " 禁止成功"));
        } else {
            return output(response(1, $user_email . " 禁止失败"));
        }
    }
    if ($_GET['a'] == 'invitation_code_setuserasadminbyid') {
        $user_id = !empty($_POST['id']) ? $_POST['id'] : 0;
        $result = $drive->setuserasadminbyid($user_id);
        if ($result['stat']!=204) {
            return output(response(1, "设置管理失败" . json_encode($result)));
        } else {
            return output(response(0, "设置管理成功"));
        }
    }
    if ($_GET['a'] == 'invitation_code_deluserasadminbyid') {
        $user_id = !empty($_POST['id']) ? $_POST['id'] : 0;
        $result = $drive->deluserasadminbyid($user_id);
        if ($result['stat']!=204) {
            return output(response(1, "取消管理失败" . json_encode($result)));
        } else {
            return output(response(0, "取消管理成功"));
        }
    }


    return $tmparr;
}

function getRandomPass($user) {
    $str = null;
    $p = 0;
    $max = rand(8, 10);
    while ($p == 0) {
        $strPol = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max1 = rand(1, 3);
        for ($i=0;$i<$max1;$i++) $str .= $strPol[rand(0, 25)];

        $strPol = 'abcdefghijklmnopqrstuvwxyz';
        $max2 = rand(2, 4);
        for ($i=0;$i<$max2;$i++) $str .= $strPol[rand(0, 25)];

        $strPol = '0123456789';
        $max3 = $max - $max1 - $max2;
        for ($i=0;$i<$max3;$i++) $str .= $strPol[rand(0, 9)];

        if (strpos(strtolower($str), strtolower($user))===false) $p = 1;
        else {
            $t = strpos(strtolower($str), strtolower($user));
            $str = substr($str, 0, $t) . substr($str, $t+strlen($user));
            //$str = str_replace(strtolower($user), '', strtolower($str));
        }
    }
    
    return $str;
}

function splitfirst($str, $split)
{
    $len = strlen($split);
    $pos = strpos($str, $split);
    if ($pos===false) {
        $tmp[0] = $str;
        $tmp[1] = '';
    } elseif ($pos>0) {
        $tmp[0] = substr($str, 0, $pos);
        $tmp[1] = substr($str, $pos+$len);
    } else {
        $tmp[0] = '';
        $tmp[1] = substr($str, $len);
    }
    return $tmp;
}

function splitlast($str, $split)
{
    $len = strlen($split);
    $pos = strrpos($str, $split);
    if ($pos===false) {
        $tmp[0] = $str;
        $tmp[1] = '';
    } elseif ($pos>0) {
        $tmp[0] = substr($str, 0, $pos);
        $tmp[1] = substr($str, $pos+$len);
    } else {
        $tmp[0] = '';
        $tmp[1] = substr($str, $len);
    }
    return $tmp;
}

function EnvOpt($needUpdate = 0)
{
    global $constStr;
    global $EnvConfigs;
    global $timezones;
    global $slash;
    global $drive;
    ksort($EnvConfigs);
    $disktags = explode('|', getConfig('disktag'));
    $envs = '';
    //foreach ($EnvConfigs as $env => $v) if (isCommonEnv($env)) $envs .= '\'' . $env . '\', ';
    $envs = substr(json_encode(array_keys ($EnvConfigs)), 1, -1);

    $html = '<title>'.getconstStr('Setup').'</title>';
    if (isset($_POST['updateProgram'])&&$_POST['updateProgram']==getconstStr('updateProgram')) {
        $response = setConfigResponse(OnekeyUpate($_POST['auth'], $_POST['project'], $_POST['branch']));
        if (api_error($response)) {
            $html = api_error_msg($response);
            $title = 'Error';
        } else {
            //WaitSCFStat();
            $html .= getconstStr('UpdateSuccess') . '<br><a href="">' . getconstStr('Back') . '</a>';
            $title = getconstStr('Setup');
        }
        return message($html, $title);
    }
    if (isset($_POST['submit1'])) {
        $_SERVER['disk_oprating'] = '';
        foreach ($_POST as $k => $v) {
            if (isShowedEnv($k) || $k=='disktag_del' || $k=='disktag_add' || $k=='disktag_rename' || $k=='disktag_copy') {
                $tmp[$k] = $v;
            }
            if ($k=='disktag_newname') {
                $v = preg_replace('/[^0-9a-zA-Z|_]/i', '', $v);
                $f = substr($v, 0, 1);
                if (strlen($v)==1) $v .= '_';
                if (isCommonEnv($v)) {
                    return message('Do not input ' . $envs . '<br><a href="">' . getconstStr('Back') . '</a>', 'Error', 201);
                } elseif (!(('a'<=$f && $f<='z') || ('A'<=$f && $f<='Z'))) {
                    return message('<a href="">' . getconstStr('Back') . '</a>', 'Please start with letters', 201);
                } elseif (getConfig($v)) {
                    return message('<a href="">' . getconstStr('Back') . '</a>', 'Same tag', 201);
                } else {
                    $tmp[$k] = $v;
                }
            }
            if ($k=='disktag_sort') {
                $td = implode('|', json_decode($v));
                if (strlen($td)==strlen(getConfig('disktag'))) $tmp['disktag'] = $td;
                else return message('Something wrong.');
            }
            if ($k == 'disk') $_SERVER['disk_oprating'] = $v;
        }
        /*if ($tmp['domain_path']!='') {
            $tmp1 = explode("|",$tmp['domain_path']);
            $tmparr = [];
            foreach ($tmp1 as $multidomain_paths){
                $pos = strpos($multidomain_paths,":");
                if ($pos>0) $tmparr[substr($multidomain_paths, 0, $pos)] = path_format(substr($multidomain_paths, $pos+1));
            }
            $tmp['domain_path'] = $tmparr;
        }*/
        $response = setConfigResponse( setConfig($tmp, $_SERVER['disk_oprating']) );
        if (api_error($response)) {
            $html = api_error_msg($response);
            $title = 'Error';
        } else {
            $html .= getconstStr('Success') . '!<br>
            <a href="">' . getconstStr('Back') . '</a>';
            $title = getconstStr('Setup');
        }
        return message($html, $title);
    }
    if (isset($_POST['config_b'])) {
        if (!$_POST['pass']) return output("{\"Error\": \"No admin pass\"}", 403);
        if (!is_numeric($_POST['timestamp'])) return output("{\"Error\": \"Error time\"}", 403);
        if (abs(time() - $_POST['timestamp']/1000) > 5*60) return output("{\"Error\": \"Timeout\"}", 403);

        if ($_POST['pass']==sha1(getConfig('admin') . $_POST['timestamp'])) {
            if ($_POST['config_b'] == 'export') {
                foreach ($EnvConfigs as $env => $v) {
                    if (isCommonEnv($env)) {
                        $value = getConfig($env);
                        if ($value) $tmp[$env] = $value;
                    }
                }
                foreach ($disktags as $disktag) {
                    $d = getConfig($disktag);
                    if ($d == '') {
                        $d = '';
                    } elseif (gettype($d)=='array') {
                        $tmp[$disktag] = $d;
                    } else {
                        $tmp[$disktag] = json_decode($d, true);
                    }
                }
                unset($tmp['admin']);
                return output(json_encode($tmp, JSON_PRETTY_PRINT));
            }
            if ($_POST['config_b'] == 'import') {
                if (!$_POST['config_t']) return output("{\"Error\": \"Empty config.\"}", 403);
                $c = '{' . splitfirst($_POST['config_t'], '{')[1];
                $c = splitlast($c, '}')[0] . '}';
                $tmp = json_decode($c, true);
                if (!!!$tmp) return output("{\"Error\": \"Config input error. " . $c . "\"}", 403);
                if (isset($tmp['disktag'])) $tmptag = $tmp['disktag'];
                foreach ($EnvConfigs as $env => $v) {
                    if (isCommonEnv($env)) {
                        if (isShowedEnv($env)) {
                            if (getConfig($env)!=''&&!isset($tmp[$env])) $tmp[$env] = '';
                        } else {
                            unset($tmp[$env]);
                        }
                    }
                }
                if ($disktags) foreach ($disktags as $disktag) {
                    if ($disktag!=''&&!isset($tmp[$disktag])) $tmp[$disktag] = '';
                }
                if ($tmptag) $tmp['disktag'] = $tmptag;
                $response = setConfigResponse( setConfig($tmp) );
                if (api_error($response)) {
                    return output("{\"Error\": \"" . api_error_msg($response) . "\"}", 500);
                } else {
                    return output("{\"Success\": \"Success\"}", 200);
                }
            }
            return output(json_encode($_POST), 500);
        } else {
            return output("{\"Error\": \"Admin pass error\"}", 403);
        }
    }
    if (isset($_POST['changePass'])) {
        if (!is_numeric($_POST['timestamp'])) return message("Error time<a href=\"\">" . getconstStr('Back') . "</a>", "Error", 403);
        if (abs(time() - $_POST['timestamp']/1000) > 5*60) return message("Timeout<a href=\"\">" . getconstStr('Back') . "</a>", "Error", 403);
        if ($_POST['newPass1']==''||$_POST['newPass2']=='') return message("Empty new pass<a href=\"\">" . getconstStr('Back') . "</a>", "Error", 403);
        if ($_POST['newPass1']!==$_POST['newPass2']) return message("Twice new pass not the same<a href=\"\">" . getconstStr('Back') . "</a>", "Error", 403);
        if ($_POST['newPass1']==getConfig('admin')) return message("New pass same to old one<a href=\"\">" . getconstStr('Back') . "</a>", "Error", 403);
        if ($_POST['oldPass']==sha1(getConfig('admin') . $_POST['timestamp'])) {
            $tmp['admin'] = $_POST['newPass1'];
            $response = setConfigResponse( setConfig($tmp) );
            if (api_error($response)) {
                return message(api_error_msg($response) . "<a href=\"\">" . getconstStr('Back') . "</a>", "Error", 403);
            } else {
                return message("Success<a href=\"\">" . getconstStr('Back') . "</a>", "Success", 200);
            }
        } else {
            return message("Old pass error<a href=\"\">" . getconstStr('Back') . "</a>", "Error", 403);
        }
    }

    if (isset($_GET['preview'])) {
        $preurl = $_SERVER['PHP_SELF'] . '?preview';
    } else {
        $preurl = path_format($_SERVER['PHP_SELF'] . '/');
    }
    $html .= '
<a href="' . $preurl . '">' . getconstStr('Back') . '</a><br>
';
    if ($_GET['setup']==='platform') {
        $frame .= '
<table border=1 width=100%>
    <form name="common" action="" method="post">';
    foreach ($EnvConfigs as $key => $val) if (isCommonEnv($key) && isShowedEnv($key)) {
        $frame .= '
        <tr>
            <td><label>' . $key . '</label></td>
            <td width=100%>';
        if ($key=='timezone') {
            $frame .= '
                <select name="' . $key .'">';
            foreach (array_keys($timezones) as $zone) {
                $frame .= '
                    <option value="'.$zone.'" '.($zone==getConfig($key)?'selected="selected"':'').'>'.$zone.'</option>';
            }
            $frame .= '
                </select>
                ' . getconstStr('EnvironmentsDescription')[$key];
        } elseif ($key=='theme') {
            $theme_arr = scandir(__DIR__ . $slash . 'theme');
            $frame .= '
                <select name="' . $key .'">
                    <option value=""></option>';
            foreach ($theme_arr as $v1) {
                if ($v1!='.' && $v1!='..') $frame .= '
                    <option value="'.$v1.'" '.($v1==getConfig($key)?'selected="selected"':'').'>'.$v1.'</option>';
            }
            $frame .= '
                </select>
                ' . getconstStr('EnvironmentsDescription')[$key];
        } /*elseif ($key=='domain_path') {
            $tmp = getConfig($key);
            $domain_path = '';
            foreach ($tmp as $k1 => $v1) {
                $domain_path .= $k1 . ':' . $v1 . '|';
            }
            $domain_path = substr($domain_path, 0, -1);
            $frame .= '
        <tr>
            <td><label>' . $key . '</label></td>
            <td width=100%><input type="text" name="' . $key .'" value="' . $domain_path . '" placeholder="' . getconstStr('EnvironmentsDescription')[$key] . '" style="width:100%"></td>
        </tr>';
        }*/ else $frame .= '
                <input type="text" name="' . $key . '" value="' . htmlspecialchars(getConfig($key)) . '" placeholder="' . getconstStr('EnvironmentsDescription')[$key] . '" style="width:100%">';
        $frame .= '
            </td>
        </tr>';
    }
    $frame .= '
        <tr><td><input type="submit" name="submit1" value="' . getconstStr('Setup') . '"></td><td></td></tr>
    </form>
</table><br>';
    } elseif (isset($_GET['disktag'])&&in_array($_GET['disktag'], $disktags)) {
        $disktag = $_GET['disktag'];
        $disk_tmp = null;
        $diskok = driveisfine($disk_tmp, $disktag);
        $frame .= '
<table width=100%>
    <tr>
        <td>
            <form action="" method="post" style="margin: 0" onsubmit="return renametag(this);">
                <input type="hidden" name="disktag_rename" value="' . $disktag . '">
                <input type="text" name="disktag_newname" value="' . $disktag . '" placeholder="' . getconstStr('EnvironmentsDescription')['disktag'] . '">
                <input type="submit" name="submit1" value="' . getconstStr('RenameDisk') . '">
            </form>
        </td>
    </tr>
</table><br>
<table>
<tr>
    <td>
        <form action="" method="post" style="margin: 0" onsubmit="return deldiskconfirm(this);">
            <input type="hidden" name="disktag_del" value="' . $disktag . '">
            <input type="submit" name="submit1" value="' . getconstStr('DelDisk') . '">
        </form>
    </td>
    <td>
        <form action="" method="post" style="margin: 0" onsubmit="return cpdiskconfirm(this);">
            <input type="hidden" name="disktag_copy" value="' . $disktag . '">
            <input type="submit" name="submit1" value="' . getconstStr('CopyDisk') . '">
        </form>
    </td>
</tr>
</table>
<table border=1 width=100%>';
        if ($diskok) {
            foreach (extendShow_diskenv($disk_tmp) as $ext_env) {
                $frame .= '
    <tr>
        <td>' . $ext_env . '</td>
        <td>' . getConfig($ext_env, $disktag) . '</td>
    </tr>';
            }

            $frame .= '
<form name="' . $disktag . '" action="" method="post">
    <input type="hidden" name="disk" value="' . $disktag . '">';
            foreach ($EnvConfigs as $key => $val) if (isInnerEnv($key) && isShowedEnv($key)) {
                $frame .= '
    <tr>
        <td><label>' . $key . '</label></td>
        <td width=100%><input type="text" name="' . $key . '" value="' . getConfig($key, $disktag) . '" placeholder="' . getconstStr('EnvironmentsDescription')[$key] . '" style="width:100%"></td>
    </tr>';
            }
            $frame .= '
    <tr><td></td><td><input type="submit" name="submit1" value="' . getconstStr('Setup') . '"></td></tr>
</form>';
        } else {
            $frame .= '
<tr>
    <td colspan="2">' . ($disk_tmp->error['body']?$disk_tmp->error['stat'] . '<br>' . $disk_tmp->error['body']:'Add this disk again.') . '</td>
</tr>';
        }
        $frame .= '
</table>

<script>
    function deldiskconfirm(t) {
        var msg="' . getconstStr('Delete') . ' ??";
        if (confirm(msg)==true) return true;
        else return false;
    }
    function cpdiskconfirm(t) {
        var msg="' . getconstStr('Copy') . ' ??";
        if (confirm(msg)==true) return true;
        //else 
        return false;
    }
    function renametag(t) {
        if (t.disktag_newname.value==\'\') {
            alert(\'' . getconstStr('DiskTag') . '\');
            return false;
        }
        if (t.disktag_newname.value==t.disktag_rename.value) {
            return false;
        }
        envs = [' . $envs . '];
        if (envs.indexOf(t.disktag_newname.value)>-1) {
            alert(\'Do not input ' . $envs . '\');
            return false;
        }
        var reg = /^[a-zA-Z]([_a-zA-Z0-9]{1,20})$/;
        if (!reg.test(t.disktag_newname.value)) {
            alert(\'' . getconstStr('TagFormatAlert') . '\');
            return false;
        }
        return true;
    }
</script>';
    } else {
        //$_GET['disktag'] = '';
        $Driver_arr = scandir(__DIR__ . $slash . 'disk');
        if (count($disktags)>1) {
            $frame .= '
<script src="//cdn.bootcss.com/Sortable/1.8.3/Sortable.js"></script>
<style>
    .sortable-ghost {
        opacity: 0.4;
        background-color: #1748ce;
    }

    #sortdisks td {
        cursor: move;
    }
</style>
<table border=1>
    <form id="sortdisks_form" action="" method="post" style="margin: 0" onsubmit="return dragsort(this);">
    <tr id="sortdisks">
        <input type="hidden" name="disktag_sort" value="">';
            $num = 0;
            foreach ($disktags as $disktag) {
                if ($disktag!='') {
                    $num++;
                    $frame .= '
        <td>' . $disktag . '</td>';
                }
            }
            $frame .= '
    </tr>
    <tr><td colspan="' . $num . '">' . getconstStr('DragSort') . '<input type="submit" name="submit1" value="' . getconstStr('SubmitSortdisks') . '"></td></tr>
    </form>
</table>
<script>
    var disks=' . json_encode($disktags) . ';
    function change(arr, oldindex, newindex) {
        //console.log(oldindex + "," + newindex);
        tmp=arr.splice(oldindex-1, 1);
        if (oldindex > newindex) {
            tmp1=JSON.parse(JSON.stringify(arr));
            tmp1.splice(newindex-1, arr.length-newindex+1);
            tmp2=JSON.parse(JSON.stringify(arr));
            tmp2.splice(0, newindex-1);
        } else {
            tmp1=JSON.parse(JSON.stringify(arr));
            tmp1.splice(newindex-1, arr.length-newindex+1);
            tmp2=JSON.parse(JSON.stringify(arr));
            tmp2.splice(0, newindex-1);
        }
        arr=tmp1.concat(tmp, tmp2);
        //console.log(arr);
        return arr;
    }
    function dragsort(t) {
        if (t.disktag_sort.value==\'\') {
            alert(\'' . getconstStr('DragSort') . '\');
            return false;
        }
        envs = [' . $envs . '];
        if (envs.indexOf(t.disktag_sort.value)>-1) {
            alert(\'Do not input ' . $envs . '\');
            return false;
        }
        return true;
    }
    Sortable.create(document.getElementById(\'sortdisks\'), {
        animation: 150,
        onEnd: function (evt) { //拖拽完毕之后发生该事件
            //console.log(evt.oldIndex);
            //console.log(evt.newIndex);
            if (evt.oldIndex!=evt.newIndex) {
                disks=change(disks, evt.oldIndex, evt.newIndex);
                document.getElementById(\'sortdisks_form\').disktag_sort.value=JSON.stringify(disks);
            }
        }
    });
</script><br>';
        }
        $frame .= '
<select name="DriveType" onchange="changedrivetype(this.options[this.options.selectedIndex].value)">';
        foreach ($Driver_arr as $v1) {
            if ($v1!='.' && $v1!='..') {
                //$v1 = substr($v1, 0, -4);
                $v2 = splitlast($v1, '.php')[0];
                if ($v2 . '.php'==$v1) $frame .= '
    <option value="' . $v2 . '"' . ($v2=='Onedrive'?' selected="selected"':'') . '>' . $v2 . '</option>';
            }
        }
        $frame .= '
</select>
<a id="AddDisk_link" href="?AddDisk=Onedrive">' . getconstStr('AddDisk') . '</a><br><br>
<script>
    function changedrivetype(d) {
        document.getElementById(\'AddDisk_link\').href="?AddDisk=" + d;
    }
</script>';
/*
        $canOneKeyUpate = 0;
        if (isset($_SERVER['USER'])&&$_SERVER['USER']==='qcloud') {
            $canOneKeyUpate = 1;
        } elseif (isset($_SERVER['HEROKU_APP_DIR'])&&$_SERVER['HEROKU_APP_DIR']==='/app') {
            $canOneKeyUpate = 1;
        } elseif (isset($_SERVER['FC_SERVER_PATH'])&&$_SERVER['FC_SERVER_PATH']==='/var/fc/runtime/php7.2') {
            $canOneKeyUpate = 1;
        } elseif ($_SERVER['BCE_CFC_RUNTIME_NAME']=='php7') {
            $canOneKeyUpate = 1;
        } elseif ($_SERVER['_APP_SHARE_DIR']==='/var/share/CFF/processrouter') {
            $canOneKeyUpate = 1;
        } else {
            $tmp = time();
            if ( mkdir(''.$tmp, 0777) ) {
                rmdir(''.$tmp);
                $canOneKeyUpate = 1;
            }
        }
        $frame .= '<a href="https://github.com/qkqpttgf/OneManager-php" target="_blank">Github</a>';
        if (!$canOneKeyUpate) {
            $frame .= '
' . getconstStr('CannotOneKeyUpate') . '<br>';
        } else {
            $frame .= '
<form name="updateform" action="" method="post">
    <input type="text" name="auth" size="6" placeholder="auth" value="qkqpttgf">
    <input type="text" name="project" size="12" placeholder="project" value="OneManager-php">
    <button name="QueryBranchs" onclick="querybranchs();return false;">' . getconstStr('QueryBranchs') . '</button>
    <select name="branch">
        <option value="master">master</option>
    </select>
    <input type="submit" name="updateProgram" value="' . getconstStr('updateProgram') . '">
</form>
<script>
    function querybranchs()
    {
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "https://api.github.com/repos/"+document.updateform.auth.value+"/"+document.updateform.project.value+"/branches");
        //xhr.setRequestHeader("User-Agent","qkqpttgf/OneManager");
        xhr.onload = function(e){
            console.log(xhr.responseText+","+xhr.status);
            if (xhr.status==200) {
                document.updateform.branch.options.length=0;
                JSON.parse(xhr.responseText).forEach( function (e) {
                    document.updateform.branch.options.add(new Option(e.name,e.name));
                    if ("master"==e.name) document.updateform.branch.options[document.updateform.branch.options.length-1].selected = true; 
                });
                document.updateform.QueryBranchs.style.display="none";
            } else {
                alert(xhr.responseText+"\n"+xhr.status);
            }
        }
        xhr.onerror = function(e){
            alert("Network Error "+xhr.status);
        }
        xhr.send(null);
    }
</script>
';
        }
        if ($needUpdate) {
            $frame .= '<div style="position: relative; word-wrap: break-word;">
        ' . str_replace("\r", '<br>', $_SERVER['github_ver_new']) . '
</div>
<button onclick="document.getElementById(\'github_ver_old\').style.display=(document.getElementById(\'github_ver_old\').style.display==\'none\'?\'\':\'none\');">More...</button>
<div id="github_ver_old" style="position: relative; word-wrap: break-word; display: none">
        ' . str_replace("\r", '<br>', $_SERVER['github_ver_old']) . '
</div>';
        }
*/
        $frame .= '<br>
<script src="https://cdn.bootcss.com/js-sha1/0.6.0/sha1.min.js"></script>
<table>
    <form id="change_pass" name="change_pass" action="" method="POST" onsubmit="return changePassword(this);">
    <tr>
        <td>old pass:</td><td><input type="password" name="oldPass">
        <input type="hidden" name="timestamp"></td>
    </tr>
    <tr>
        <td>new pass:</td><td><input type="password" name="newPass1"></td>
    </tr>
    <tr>
        <td>reinput:</td><td><input type="password" name="newPass2"></td>
    </tr>
    <tr>
        <td></td><td><button name="changePass" value="changePass">Change Admin Pass</button></td>
    </tr>
    </form>
</table><br>
<table>
    <form id="config_f" name="config" action="" method="POST" onsubmit="return false;">
    <tr>
        <td>admin pass:<input type="password" name="pass">
        <button name="config_b" value="export" onclick="exportConfig(this);">export</button></td>
    </tr>
    <tr>
        <td>config:<textarea name="config_t"></textarea>
        <button name="config_b" value="import" onclick="importConfig(this);">import</button></td>
    </tr>
    </form>
</table><br>
<script>
    var config_f = document.getElementById("config_f");
    function exportConfig(b) {
        if (config_f.pass.value=="") {
            alert("admin pass");
            return false;
        }
        try {
            sha1(1);
        } catch {
            alert("sha1.js not loaded.");
            return false;
        }
        var timestamp = new Date().getTime();
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "");
        xhr.setRequestHeader("Content-Type","application/x-www-form-urlencoded;charset=utf-8");
        xhr.onload = function(e){
            console.log(xhr.responseText+","+xhr.status);
            if (xhr.status==200) {
                var res = JSON.parse(xhr.responseText);
                config_f.config_t.value = xhr.responseText;
                config_f.parentNode.style = "width: 100%";
                config_f.config_t.style = "width: 100%";
                config_f.config_t.style.height = config_f.config_t.scrollHeight + "px";
            } else {
                alert(xhr.status+"\n"+xhr.responseText);
            }
        }
        xhr.onerror = function(e){
            alert("Network Error "+xhr.status);
        }
        xhr.send("pass=" + sha1(config_f.pass.value + "" + timestamp) + "&config_b=" + b.value + "&timestamp=" + timestamp);
    }
    function importConfig(b) {
        if (config_f.pass.value=="") {
            alert("admin pass");
            return false;
        }
        if (config_f.config_t.value=="") {
            alert("input config");
            return false;
        } else {
            try {
                var tmp = JSON.parse(config_f.config_t.value);
            } catch(e) {
                alert("config error!");
                return false;
            }
        }
        try {
            sha1(1);
        } catch {
            alert("sha1.js not loaded.");
            return false;
        }
        var timestamp = new Date().getTime();
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "");
        xhr.setRequestHeader("Content-Type","application/x-www-form-urlencoded;charset=utf-8");
        xhr.onload = function(e){
            console.log(xhr.responseText+","+xhr.status);
            if (xhr.status==200) {
                //var res = JSON.parse(xhr.responseText);
                alert("Import success");
            } else {
                alert(xhr.status+"\n"+xhr.responseText);
            }
        }
        xhr.onerror = function(e){
            alert("Network Error "+xhr.status);
        }
        xhr.send("pass=" + sha1(config_f.pass.value + "" + timestamp) + "&config_t=" + encodeURIComponent(config_f.config_t.value) + "&config_b=" + b.value + "&timestamp=" + timestamp);
    }
    function changePassword(f) {
        if (f.oldPass.value==""||f.newPass1.value==""||f.newPass2.value=="") {
            alert("Input");
            return false;
        }
        if (f.oldPass.value==f.newPass1.value) {
            alert("Same password");
            return false;
        }
        if (f.newPass1.value!==f.newPass1.value) {
            alert("Input twice new password");
            return false;
        }
        try {
            sha1(1);
        } catch {
            alert("sha1.js not loaded.");
            return false;
        }
        var timestamp = new Date().getTime();
        f.timestamp.value = timestamp;
        f.oldPass.value = sha1(f.oldPass.value + "" + timestamp);
        return true;
    }
</script>';
    }
    $html .= '
<style type="text/css">
    .tabs td { padding: 5px; }
</style>
<table border=0>
    <tr class="tabs">';
    if ($_GET['disktag']=='') {
        if ($_GET['setup']==='platform') $html .= '
        <td><a href="?setup">' . getconstStr('Home') . '</a></td>
        <td>' . getconstStr('PlatformConfig') . '</td>';
        else $html .= '
        <td>' . getconstStr('Home') . '</td>
        <td><a href="?setup=platform">' . getconstStr('PlatformConfig') . '</a></td>';
    } else $html .= '
        <td><a href="?setup">' . getconstStr('Home') . '</a></td>
        <td><a href="?setup=platform">' . getconstStr('PlatformConfig') . '</a></td>';
    foreach ($disktags as $disktag) {
        if ($disktag!='') {
            if ($_GET['disktag']==$disktag) $html .= '
        <td>' . $disktag . '</td>';
            else $html .= '
        <td><a href="?setup&disktag=' . $disktag . '">' . $disktag . '</a></td>';
        }
    }
    $html .= '
    </tr>
</table><br>';
    $html .= $frame;
    return message($html, getconstStr('Setup'));
}

function render_list($drive = null)
{
    global $constStr;
    global $license;
    //echo "r:" . $drive->disktag . PHP_EOL;
    $driveok = driveisfine($drive);
    $disktags = explode('|', getConfig('disktag'));
    //echo "d:" . json_encode($disktags) . PHP_EOL;
    $html = '<!DOCTYPE html>
    <html>
        <head>
            <meta http-equiv="content-type" content="text/html; charset=utf-8" />
            <title>Microsoft Office365 全局管理</title>
            <link rel="stylesheet" href="layui/css/layui.css">
            <link href="files/mslogo.png" rel="icon" type="image/png">
        </head>
        <body class="layui-layout-body" style="overflow-y:visible;background: #fff;">
            <div class="layui-form">
                <blockquote class="layui-elem-quote quoteBox">
                    <div class="layui-inline" style="margin-left: 2rem;">';
                    if ($disktags) {
                        $html .= '
                        <select name="account" id="account" lay-filter="account">
                            <option value="">Account Select</option>';
                                foreach ($disktags as $disktag) {
                                    if ($disktag!='') {
                                        $diskname = getConfig('diskname', $disktag);
                                        $html .= '
                            <option value="' . $disktag . '"' . ($disktag == $_SERVER['disktag']?' selected':'') . '>' . ($diskname?$diskname:$disktag) . '</option>';
                                    }
                                }
                            $html .= '
                        </select>
                    </div>
                    <!--<div class="layui-inline" style="margin-left: 2rem;">  
                        <a class="layui-btn" id="change_account"><i class="layui-icon layui-icon-template-1"></i> 切换全局</a>
                    </div>-->';
                    }
                    if ($driveok) {
                        $html .= '
                    <div class="layui-inline" style="margin-left: 2rem;">
                        <a class="layui-btn" id="add_account"><i class="layui-icon layui-icon-username"></i> 新建用户</a>
                    </div>';
                    }
                    $html .= '
                    <div class="layui-inline" style="margin-left: 2rem;">
                        <a class="layui-btn" id="setup"><i class="layui-icon layui-icon-set"></i> 后台管理</a>
                    </div>
                    <div class="layui-inline" style="margin-left: 2rem;">  
                        <a class="layui-btn" id="logout"><i class="layui-icon layui-icon-logout"></i> 注销登录</a>
                    </div>
                </blockquote>
            </div>';
            if ($_SERVER['disktag']=='') {
                $html .= 'Select an Account';
            } elseif ($driveok) {
                $html .= '
            <table class="layui-hide" id="table" lay-filter="table">
            </table>';
            } else {
                $html .= 'Something Error';
            }
            if ($driveok) {
                $html .= '
            <div id="add_account_content" class="layui-form layui-form-pane" style="display: none;margin:1rem 3rem;">
            <form class="layui-form" >
              <div class="layui-form-item">
                <label class="layui-form-label">姓/Lastname</label>
                <div class="layui-input-inline">
                  <input type="text" placeholder="英文/拼音" class="layui-input" id="lastname" pattern="[A-z0-9]{1,50}">
                </div>
                <label class="layui-form-label">名/Firstname</label>
                <div class="layui-input-inline">
                  <input type="text" placeholder="英文/拼音" class="layui-input" id="firstname" pattern="[A-z0-9]{1,50}">
                </div>
              </div>
    
              <div class="layui-form-item">
                <label class="layui-form-label">用户账号 *</label>
                <div class="layui-input-inline">
                  <input type="text" placeholder="请输入前缀" class="layui-input" id="add_user" pattern="[A-z0-9]{1,50}" required lay-verify="required">
                </div>
                <div class="layui-input-inline">
                  <select name="domain" required lay-verify="required" id="domain">';
                  $tmp = null;
                  $tmp = $drive->getdomains();
                  if ($tmp['stat']==200) {
                    $domains = json_decode($tmp['body'], true)['value'];
                  //echo json_encode($domains, JSON_PRETTY_PRINT);
                        foreach ($domains as $value) {
                            $html .= '<option value="' . $value['id'] . '">' . $value['id'] . '</option>';
                        }
                    }
                        $html .= '
                  </select>
                </div>
              </div>
              <div class="layui-form-item">
                <label class="layui-form-label">密码</label>
                <div class="layui-input-inline">
                  <input type="password" placeholder="请输入密码" class="layui-input" id="password" pattern="[A-z0-9]{8,50}">
                </div>
                <div class="layui-input-inline">
                  <input type="checkbox" name="forceChangePassword" id="forceChangePassword" lay-skin="switch" lay-text="首登强制重设密码|首登无需重设密码">
                </div>
              </div>
              
              <div class="layui-form-item">
                <label class="layui-form-label">国家(地区)</label>
                <div class="layui-input-inline">
                  <select name="location" required lay-verify="required" id="location">';
                  $locations = [//自己配置，写了几个常用的
                    '中国'=>'CN',
                    '台湾'=>'TW',
                    '香港'=>'HK',
                    '日本'=>'JP',
                    '美国'=>'US',
                    '新加坡'=>'SG',
                    '英国'=>'GB'
                  ];
                        foreach ($locations as $key => $value) {
                            $html .= '
                           <option value="' . $value . '">' . $key . '</option>';
                        }
                        $html .= '
                  </select>
                </div>
                <div class="layui-form-mid layui-word-aux">建议用全局默认区域：(' . getConfig('defaultCountry', $_SERVER['disktag']) . ')</div>
              </div>
              <div class="layui-form-item">
                <label class="layui-form-label">许可证</label>
                <div class="layui-input-inline">
                  <select name="sku" id="sku">
                    <option value="">' . '未选择</option>';
                    //echo json_encode($drive->getSku(), JSON_PRETTY_PRINT);
                    /*$skustr = getConfig('sku', $_SERVER['disktag']);
                    $skus = explode('|', $skustr);*/
                    $skuId = null;
                    foreach ($license as $k => $v) {
                        $skuId[$v['skuid']] = $v['name'];
                    }
                    $skus = $drive->getSku();
                        foreach ($skus as $id => $v) {
                            $name = '';
                            if (isset($skuId[$id])) {
                                $name = $skuId[$id];
                            } else {
                                $name = $v['name'];
                            }
                            $html .= '
                           <option value="' . $id . '">' . $name . '(' . $v['used'] . '/' . $v['total'] . ')</option>';
                        }
                        $html .= '
                  </select>
                </div>
              </div>
              <div class="layui-form-item">
                <div class="layui-input-block">
                  <button class="layui-btn" lay-filter="formDemo" id="submitaccount" type="button">立即提交</button>
                </div>
              </div>
            </form>  
            </div>';
                    }
                    $html .= '
    
        </body>
        
        <script type="text/html" id="buttons">
          <a class="layui-btn layui-btn-normal layui-btn-xs" lay-event="accountactive">允许</a>
          <a class="layui-btn layui-btn-warm layui-btn-xs" lay-event="accountinactive">禁止</a>
          <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="setuserasadminbyid">设为管理</a>
          <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="deluserasadminbyid">取消管理</a>
          <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="del">删除</a>
        </script>
        <script src="./layui/layui.js" charset="utf-8"></script>
        <script src="./layui/jquery.js"></script>
        <script type="text/javascript" charset="utf-8">
            layui.use([\'table\',\'form\',\'layer\'], function(){
              var table = layui.table;
              var form = layui.form;
              var layer = layui.layer;';
              if ($driveok) $html .= '
                table.render({
                    elem: \'#table\',//表格id
                    url:"?a=getusers&account=' . $_SERVER['disktag'] . '",//list接口地址
                    cellMinWidth: 60,//全局定义常规单元格的最小宽度
                    height: \'full-120\',
                    loading: true,
                    cols: [[
                    //align属性是文字在列表中的位置 可选参数left center right
                    //sort属性是排序功能
                    //title是这列的标题
                    //field是取接口的字段值
                    //width是宽度，不填则自动根据值的长度
                      {field:\'displayName\', title: \'displayName\',align: \'center\'},
                      {field:\'userPrincipalName\',title: \'账号\',align: \'center\',templet:function(d){
                              if(d.userPrincipalName){
                                  return d.userPrincipalName;
                              }else{
                                  return \'-\';
                              }
                      }},
                      {field:\'accountEnabled\', title: \'账户状态\',align: \'center\',templet:function(d){
                              if(d.accountEnabled == true){
                                  return \'<span style="color:#99CC00">正常</span>\';
                              }else{
                                  return \'<span style="color:red;">禁用</span>\';
                              }
                      }},
                      {field:\'usageLocation\',title: \'usageLocation\',align: \'center\'},
    
                      {field:\'id\',title: \'id\',align: \'center\',templet:function(d){
                              if(d.id){
                                  return d.id;
                              }else{
                                  return \'-\';
                              }
                      }},
                      {field:\'createdDateTime\', title: \'创建时间\',align: \'center\'},
                      {field:\'sku\', title: \'许可证\',align: \'center\',templet:function(d){
                              if(d.sku == \'无许可\'){
                                  return \'<span style="color:#ff461f">无许可</span>\';
                              }else{
                                  return d.sku;
                              }
                      }},
                      {fixed:\'right\',title: \'操作\', width: 335, align:\'center\', toolbar: \'#buttons\'}
                    ]]
              });
               //监听
              table.on(\'tool(table)\', function(obj){
                  if(obj.event === \'del\'){
                      layer.confirm(\'真的删除 \' + obj.data.userPrincipalName + \' 吗\', function(index){
                          $.post("?a=admin_delete&account=' . $_SERVER['disktag'] . '",{email:obj.data.userPrincipalName,id:obj.data.id},function(res){
                            if (res.code == 0) {
                                obj.del();//删除表格这行数据
                            }
                            layer.msg(res.msg);
                          },\'json\');
                      });
                  }
                  if(obj.event === \'accountactive\'){
                      layer.confirm(\'允许登录?\', function(index){
                          $.post("?a=invitation_code_activeaccount&account=' . $_SERVER['disktag'] . '",{email:obj.data.userPrincipalName},function(res){
                           if (res.code == 1) {
                              layer.closeAll();
                              layui.use(\'table\', function(){
                                  var table = layui.table;
                                  table.reload(\'table\', { //表格的id
                                      url:"?a=getusers&account=' . $_SERVER['disktag'] . '",
                                  });
                                 })
                            }
                            layer.msg(res.msg);
                          },\'json\');
                      });
                  }
                  if(obj.event === \'setuserasadminbyid\'){
                      layer.confirm(\'设为管理?\', function(index){
                          $.post("?a=invitation_code_setuserasadminbyid&account=' . $_SERVER['disktag'] . '",{id:obj.data.id},function(res){
                           if (res.code == 1) {
                              layer.closeAll();
                              layui.use(\'table\', function(){
                                  var table = layui.table;
                                  table.reload(\'table\', { //表格的id
                                      url:"?a=getusers&account=' . $_SERVER['disktag'] . '",
                                  });
                                 })
                            }
                            layer.msg(res.msg);
                          },\'json\');
                      });
                  }
                  if(obj.event === \'deluserasadminbyid\'){
                      layer.confirm(\'取消管理?\', function(index){
                          $.post("?a=invitation_code_deluserasadminbyid&account=' . $_SERVER['disktag'] . '",{id:obj.data.id},function(res){
                           if (res.code == 1) {
                              layer.closeAll();
                              layui.use(\'table\', function(){
                                  var table = layui.table;
                                  table.reload(\'table\', { //表格的id
                                      url:"?a=getusers&account=' . $_SERVER['disktag'] . '",
                                  });
                                 })
                            }
                            layer.msg(res.msg);
                          },\'json\');
                      });
                  }
    
                  if(obj.event === \'accountinactive\'){
                      layer.confirm(\'禁止登录?\', function(index){
                          $.post("?a=invitation_code_inactiveaccount&account=' . $_SERVER['disktag'] . '",{email:obj.data.userPrincipalName},function(res){
                           if (res.code == 1) {
                              layer.closeAll();
                              layui.use(\'table\', function(){
                                  var table = layui.table;
                                  table.reload(\'table\', { //表格的id
                                      url:"?a=getusers&account=' . $_SERVER['disktag'] . '",
                                  });
                                 })
                            }
                            layer.msg(res.msg);
                          },\'json\');
                      });
                  }
                });';
        $html .= '
                form.on(\'select(account)\', function (data) {
                    //获取当前选中下拉项的索引
                    //let indexGID = data.elem.selectedIndex;
                    //获取当前选中下拉项的自定义属性值 title
                    //let goodsName = data.elem[indexGID].title;
                    //获取当前选中下拉项的 value值
                    //let goodsID = data.value;
                    layer.confirm(\'确认切换 \' + data.elem[data.elem.selectedIndex].text + \' 全局?\', function(index){
                        var account = $(\'#account\').val();
                        location.href = "?account=" + account;
                    });
                });
                $(\'#change_account\').click(function(){
                    layer.confirm(\'确认切换全局?\', function(index){
                        var account = $(\'#account\').val();
                        location.href = "?account=" + account;
                    });
                });
                $(\'#logout\').click(function(){
                      layer.confirm(\'确认注销登录?\', function(index){
                          var expd = new Date();
                          expd.setTime(expd.getTime()+1000);
                          var expires = "expires="+expd.toGMTString();
                          document.cookie = "admin=; path=/; "+expires;
                          window.location.reload();
                      });
                });
                $(\'#setup\').click(function(){
                    location.href = "?setup";
                });
    
                $(\'#add_account\').click(function(){
                    layer.open({
                        type: 1,
                        title:\'新建账号\',
                        end: function(){
                            $(\'#add_account_content\').hide();
                          },
                        skin: \'layui-layer-rim\', //加上边框
                        //area: [\'48rem;\', \'28rem;\'], //宽高
                        content: $(\'#add_account_content\'),
                    });
                });
                $(\'#submitaccount\').click(function(){
                    var data = {
                        firstname:$(\'#firstname\').val(),
                        lastname:$(\'#lastname\').val(),
                        add_user:$(\'#add_user\').val(),
                        domain:$(\'#domain\').val(),
                        password:$(\'#password\').val(),
                        forceChangePassword:$(\'#forceChangePassword\').is(\':checked\'),
                        location:$(\'#location\').val(),
                        sku:$(\'#sku\').val(),
                    };
                    $.post("?a=admin_add_account&account=' . $_SERVER['disktag'] . '",data,function(res){
                        console.log(res.msg);
                        if (res.code == 0) {
                            let r = JSON.parse(res.msg);
                            alert("用户名：" + r.userPrincipalName + "\n密码：" + r.password);
                            layer.closeAll();
                            layui.use(\'table\', function(){
                                var table = layui.table;
                                table.reload(\'table\', { //表格的id
                                    url:"?a=getusers&account=' . $_SERVER['disktag'] . '",
                                });
                            })
                            layer.msg(r.msg);
                        } else {
                            //console.log(res.code + res.msg);
                            if (res.code > 1) alert(res.code + JSON.parse(res.msg).error.message);
                            else {
                                alert(res.code + JSON.parse(res.msg).msg);
                            }
                            layer.msg(res.msg);
                        }
                    },\'json\');
                })
            });
        </script>
    </html>';

    return output($html, $statusCode);
}

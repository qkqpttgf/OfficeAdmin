<?php

class Onedrive {
    protected $access_token;
    protected $disktag;

    function __construct($tag) {
        $this->disktag = $tag;
        $this->redirect_uri = 'https://scfonedrive.github.io';
        $this->oauth_url = 'https://login.microsoftonline.com/common/oauth2/v2.0/';
        $this->api_url = 'https://graph.microsoft.com';
        $default_client_id = '734ef928-d74c-4555-8d1b-d942fa0a1a41';
        $default_client_secret = '_I5gOpmG5vTC2Ts_K._wCW4nN1km~4Pk52';
        $default_scope = $this->api_url . '/Application.ReadWrite.All ' . $this->api_url . '/Directory.AccessAsUser.All offline_access';
        if ($tag!='') {
            if (isset($_GET['AddDisk'])||(isset($_GET['a'])&&$_GET['a']==='admin_resetpassword')) {
                $this->client_id = $default_client_id;
                $this->client_secret = $default_client_secret;
                $this->scope = $default_scope;
            } else {
                $this->client_id = getConfig('client_id', $tag);
                $this->client_secret = getConfig('client_secret', $tag);
                $this->scope = $this->api_url . '/.default';
            }
            $this->client_secret = urlencode($this->client_secret);
            $this->tenant_id = getConfig('tenant_id', $tag);
            $this->oauth_url1 = 'https://login.microsoftonline.com/' . $this->tenant_id . '/oauth2/v2.0/token';
            $this->scope = urlencode($this->scope);
            if (getConfig('refresh_token', $tag)) $res = $this->get_access_token1(getConfig('refresh_token', $tag), $default_client_id, $default_client_secret);
            $res = $this->get_access_token();
        }
    }

    public function isfine()
    {
        if (!$this->access_token) return false;
        else return true;
    }
    public function show_base_class()
    {
        return get_class();
        //$tmp[0] = get_class();
        //$tmp[1] = get_class($this);
        //return $tmp;
    }

    public function ext_show_innerenv()
    {
        //return [ ];
        return [ 'tenant_id', 'client_id', 'client_secret' ];
    }

    public function getuserscounts() {
        $url='/v1.0/users/$count';
        $h['ConsistencyLevel'] = 'eventual';
        $result = $this->MSAPI('GET', $url, '', $h);
        return $result['body'];
    }
    public function searchuser($seachStr) {
        $url='/v1.0/users?$search="userPrincipalName:' . $seachStr . '"&$count=true&$select=displayName,accountEnabled,usageLocation,id,userPrincipalName,createdDateTime,assignedLicenses';
        //$url='/v1.0/users?$search="displayName:' . $seachStr . '" OR "userPrincipalName:' . $seachStr . '"&$count=true';
        $h['ConsistencyLevel'] = 'eventual';
        $result = $this->MSAPI('GET', $url, '', $h);
        return $result;
    }
    private function getusersStartEnd($start = 0) {
        //error_log1('start=' . $start . ' end=' . $end);
        $pagesize = 900;
        if ($start<$pagesize) {
            $url='/v1.0/users/?$top=' . $pagesize . '&$select=displayName,accountEnabled,usageLocation,id,userPrincipalName,createdDateTime,assignedLicenses';
            $result = $this->MSAPI('GET', $url);
            //if ($result['stat']!=200) 
            return $result;
            //$data = json_decode($result['body'], true);
        } else {
            $page = intval($start / $pagesize);
            if (!($link = getcache('limit_' . $pagesize . '_page_' . ($page-1) . '_nextlink', $this->disktag))) {
                $result = $this->getusersStartEnd(($page-1)*$pagesize);
                $link = json_decode($result['body'], true)['@odata.nextLink'];
            }
            //error_log1('link=' . $link);
            $result = $this->MSAPI('GET', $link);
            if ($result['stat']!=200) return $result;
            $data = json_decode($result['body'], true);
            if (isset($data['@odata.nextLink'])) savecache('limit_' . $pagesize . '_page_' . $page . '_nextlink', $data['@odata.nextLink'], $this->disktag);
            $j = 0;
            $s1 = $start-$page*$pagesize;
            //error_log1('start=' . $start . ' page=' . $page . ' valuecount=' . count($data['value']) . ' s1=' . $s1);
            for ($i=$s1;$i<($s1+100<count($data['value'])?$s1+100:count($data['value']));$i++) {
                $tmp[$j] = $data['value'][$i];
                $j++;
            }
            $data['value'] = $tmp;
            $result['body'] = json_encode($data);
            return $result;
        }
    }
    public function getusers($page = 1, $limit = 0) {
        if ($page>1) {
            $url='/v1.0/users/?';
            if ($page*$limit>999) {
                $result = $this->getusersStartEnd(($page-1)*$limit);
            } else {
                if (!$limit) $limit = 100;
                $url .= '$top=' . $page * $limit . '&';
                $url .= '$select=displayName,accountEnabled,usageLocation,id,userPrincipalName,createdDateTime,assignedLicenses';
                $result = $this->MSAPI('GET', $url);
                if ($result['stat']!=200) return $result;
                $data = json_decode($result['body'], true);
                $j = 0;
                for ($i=($page-1)*$limit;$i<count($data['value']);$i++) {
                    $tmp[$j] = $data['value'][$i];
                    $j++;
                }
                $data['value'] = $tmp;
                $result['body'] = json_encode($data);
            }
        } else {
            $page = 1;
            $url='/v1.0/users/?';
            if ($limit>0) $url .= '$top=' . $limit . '&';
            $url .= '$select=displayName,accountEnabled,usageLocation,id,userPrincipalName,createdDateTime,assignedLicenses';
            $result = $this->MSAPI('GET', $url);
            if ($result['stat']!=200) return $result;
            $data = json_decode($result['body'], true);
            if (isset($data['@odata.nextLink'])) savecache('limit_' . $limit . '_page_' . $page . '_nextlink', $data['@odata.nextLink'], $this->disktag);
        }
        $data = json_decode($result['body'], true);
        $data['@odata.count'] = $this->getuserscounts();
        $result['body'] = json_encode($data);
        return $result;
    }
    public function getGlobalAdmins() {
        if (!($GlobalAdmins=getcache('GlobalAdmins', $this->disktag))) {
            $url="/v1.0/directoryRoles/roleTemplateId=62e90394-69f5-4237-9190-012177145e10/members";
            $response = $this->MSAPI('GET', $url);
            if ($response['stat']!=200) return $response;
            $GlobalAdmins = json_decode($response['body'], true)['value'];
            savecache('GlobalAdmins', $GlobalAdmins, $this->disktag);
        }
        return $GlobalAdmins;
    }
    public function admin_create_user ($request){
        $url = '/v1.0/users';
        $user_email = $request['username'] . '@' . $request['domain'];
        if ($request['firstname']!=''||$request['lastname']!='') $displayName = $request['firstname'] . ' ' . $request['lastname'];
        else $displayName = $request['username'];
        $data = [
            "accountEnabled" => true,
            "displayName" => $displayName,
            "mailNickname" => $request['username'],
            "passwordPolicies" => "DisablePasswordExpiration, DisableStrongPassword",
            "passwordProfile" => [
                "password" => $request['password'],
                "forceChangePasswordNextSignIn" => $request['forceChangePassword']
            ],
            "userPrincipalName" => $user_email,
            "usageLocation" => $request['location']
        ];
        return $this->MSAPI('POST', $url, json_encode($data));
    }
    public function addsubscribe ($user_email, $sku_id){
        $url = '/v1.0/users/' . $user_email . '/assignLicense';
        $data = [
            'addLicenses' => [
                [
                    'disabledPlans' => [],
                    'skuId' => $sku_id
                ],
            ],
            'removeLicenses' => [],
        ];
        return $this->MSAPI('POST', $url, json_encode($data));
    }
    public function addsubscribes ($user_email, $sku_ids, $del_skus = []){
        //error_log1('sku_ids: ' . json_encode($sku_ids));
        $url = '/v1.0/users/' . $user_email . '/assignLicense';
        if ($del_skus) {
            //error_log1('del_skus: ' . $del_skus . ' : ' . json_encode($del_skus));
            $i = 0;
            foreach ($del_skus as $del_sku) {
                $data['removeLicenses'][$i] = $del_sku;
                $i++;
            }
        } else $data['removeLicenses'] = [];
        if ($sku_ids) {
            $i = 0;
            foreach ($sku_ids as $sku_id) {
                $data['addLicenses'][$i] = [
                    'disabledPlans' => [],
                    'skuId' => $sku_id
                ];
                $i++;
            }
        } else $data['addLicenses'] = [];
        
        //error_log1('in: ' . json_encode($data));
        return $this->MSAPI('POST', $url, json_encode($data));
    }
    public function accountdelete ($user_email){
        $url="/v1.0/users/" . $user_email;
        return $this->MSAPI('DELETE', $url);
    }
    public function resetpassword ($user_email, $password){
        $res = $this->get_access_token1(getConfig('refresh_token', $this->disktag));
        $url="/v1.0/users/" . $user_email;
        $data['passwordProfile']['password'] = $password;
        $data['passwordProfile']['forceChangePasswordNextSignIn'] = false;
        return $this->MSAPI('PATCH', $url, json_encode($data));
    }
    public function accountactive($user_email){
        $url="/v1.0/users/" . $user_email;
        $jsdata='{"accountEnabled":"true"}';
        return $this->MSAPI('PATCH', $url, $jsdata);
    }
    public function accountinactive($user_email) {
        $url="/v1.0/users/" . $user_email;
        $jsdata='{"accountEnabled":"false"}';
        return $this->MSAPI('PATCH', $url, $jsdata);
    }
    public function setuserasadminbyid($user_id) {
        $json = [
            "@odata.id" => "https://graph.microsoft.com/v1.0/directoryObjects/{$user_id}",
        ];
        $jsdata=json_encode($json);
        $url="/v1.0/directoryRoles/roleTemplateId=62e90394-69f5-4237-9190-012177145e10/members/\$ref";
        //roletemplateID 
        //useradmin:fe930be7-5e62-47db-91af-98c3a49a38b1
        //globalAdmin:62e90394-69f5-4237-9190-012177145e10
        //Privileged role Admin:e8611ab8-c189-46e8-94e1-60213ab1f814
        savecache('GlobalAdmins', [], $this->disktag, 1);
        return $this->MSAPI('POST', $url, $jsdata);
    }
    public function deluserasadminbyid($user_id) {
        $url="/v1.0/directoryRoles/roleTemplateId=62e90394-69f5-4237-9190-012177145e10/members/{$user_id}/\$ref";
        //roletemplateID 
        //useradmin:fe930be7-5e62-47db-91af-98c3a49a38b1
        //globalAdmin:62e90394-69f5-4237-9190-012177145e10
        //Privileged role Admin:e8611ab8-c189-46e8-94e1-60213ab1f814
        savecache('GlobalAdmins', [], $this->disktag, 1);
        return $this->MSAPI('DELETE', $url);
    }

    public function getdomains() {
        $url="/v1.0/domains";
        return $this->MSAPI('GET', $url);
    }
    public function getSku() {
        // https://graph.microsoft.com/v1.0/subscribedSkus 获取已有订阅
        $url = '/v1.0/subscribedSkus';
        $result = $this->MSAPI('GET', $url);
        if ($result['stat']!=200) return $result;
        $value = json_decode($result['body'], true)['value'];
        foreach ($value as $s) {
            if ($s['capabilityStatus']=='Enabled') {
                //$skus[$s['skuId']] = $s;
                $skus[$s['skuId']]['name'] = $s['skuPartNumber'];
                $skus[$s['skuId']]['used'] = $s['consumedUnits'];
                $skus[$s['skuId']]['total'] = $s['prepaidUnits']['enabled'];
            }
        }
        return $skus;
    }
    public function getOrganization() {
        $api = "/v1.0/organization";
        $arr = $this->MSAPI('GET', $api);
        if ($arr['stat']!=200) {
            return $arr;
            //$html = $arr['stat'] . json_encode(json_decode($arr['body'], true), JSON_PRETTY_PRINT);
            //return message($html);
        }
        $result = json_decode($arr['body'], true)['value'][0];
        $tmp['defaultCountry'] = $result['countryLetterCode'];
        setConfig($tmp, $this->disktag);
        return $result['countryLetterCode'];
    }
    public function setUserUsageLocation($user_email, $region) {
        $url="/v1.0/users/" . $user_email;
        $jsdata='{"usageLocation":"' . $region . '"}';
        return $this->MSAPI('PATCH', $url, $jsdata);
    }

    public function AddDisk() {
        global $constStr;
        global $EnvConfigs;
// https://docs.microsoft.com/zh-cn/graph/api/application-post-applications?view=graph-rest-1.0&tabs=http 创建应用
// https://graph.microsoft.com/v1.0/organization 获取tenantID

        $envs = '';
        foreach ($EnvConfigs as $env => $v) if (isCommonEnv($env)) $envs .= '\'' . $env . '\', ';
        $url = path_format($_SERVER['PHP_SELF'] . '/');

        if (isset($_GET['AddClient'])) {
        if (isset($_GET['install0'])) {
            if ($_POST['disktag_add']!='') {
                $_POST['disktag_add'] = preg_replace('/[^0-9a-zA-Z|_]/i', '', $_POST['disktag_add']);
                $f = substr($_POST['disktag_add'], 0, 1);
                if (strlen($_POST['disktag_add'])==1) $_POST['disktag_add'] .= '_';
                if (isCommonEnv($_POST['disktag_add'])) {
                    return message('Do not input ' . $envs . '<br><button onclick="location.href = location.href;">'.getconstStr('Refresh').'</button>', 'Error', 201);
                } elseif (!(('a'<=$f && $f<='z') || ('A'<=$f && $f<='Z'))) {
                    return message('Please start with letters<br><button onclick="location.href = location.href;">'.getconstStr('Refresh').'</button>', 'Error', 201);
                }

                $tmp = null;
                // clear envs
                foreach ($EnvConfigs as $env => $v) if (isInnerEnv($env)) $tmp[$env] = '';

                //$this->disktag = $_POST['disktag_add'];
                $tmp['disktag_add'] = $_POST['disktag_add'];
                $tmp['Driver'] = get_class($this);
                $tmp['diskname'] = $_POST['diskname'];
                $tmp['tenant_id'] = $_POST['tenant_id'];
                $tmp['client_id'] = $_POST['client_id'];
                $tmp['client_secret'] = $_POST['client_secret'];

                $response = setConfigResponse( setConfig($tmp, $this->disktag) );
                if (api_error($response)) {
                    $html = api_error_msg($response);
                    $title = 'Error';
                } else {
                    $title = getconstStr('MayinEnv');
                    $html = getconstStr('Wait') . ' 3s<meta http-equiv="refresh" content="3;URL=' . $url . '?setup&disktag=' . $_POST['disktag_add'] . '">';
                }
                return message($html, $title, 201);
            }
        }

        $html = '
<div>
    <form id="form1" action="" method="post" onsubmit="return notnull(this);">
        ' . getconstStr('DiskTag') . ': (' . getConfig('disktag') . ')
        <input type="text" name="disktag_add" placeholder="' . getconstStr('EnvironmentsDescription')['disktag'] . '" style="width:100%"><br>
        ' . getconstStr('DiskName') . ':
        <input type="text" name="diskname" placeholder="' . getconstStr('EnvironmentsDescription')['diskname'] . '" style="width:100%"><br>
        <br>
        tenant_id:<input type="text" name="tenant_id" style="width:100%"><br>
        client_id:<input type="text" name="client_id" style="width:100%"><br>
        client_secret:<input type="text" name="client_secret" style="width:100%"><br>
        <br>
        Directory.ReadWrite.All ,<br>
        RoleManagement.ReadWrite.Directory ,<br>
        User.ReadWrite.All
        <br>';
        $html .='
        <input type="submit" value="' . getconstStr('Submit') . '">
    </form>
</div>
    <script>
        function notnull(t)
        {
            if (t.disktag_add.value==\'\') {
                alert(\'' . getconstStr('DiskTag') . '\');
                return false;
            }
            envs = [' . $envs . '];
            if (envs.indexOf(t.disktag_add.value)>-1) {
                alert("Do not input ' . $envs . '");
                return false;
            }
            var reg = /^[a-zA-Z]([_a-zA-Z0-9]{1,20})$/;
            if (!reg.test(t.disktag_add.value)) {
                alert(\'' . getconstStr('TagFormatAlert') . '\');
                return false;
            }
            if (t.tenant_id.value==\'\') {
                alert(\'input tenant_id\');
                return false;
            }
            if (t.client_id.value==\'\') {
                alert(\'input client_id\');
                return false;
            }
            if (t.client_secret.value==\'\') {
                alert(\'input client_secret\');
                return false;
            }
            
            document.getElementById("form1").action="?install0&AddClient&disktag=" + t.disktag_add.value + "&AddDisk=' . $_GET['AddDisk'] . '";
            return true;
        }
    </script>';
        $title = 'Input';
        return message($html, $title, 201);
        }

        if (isset($_GET['CreateClient1'])) {
            if ($this->access_token == '') {
                $refresh_token = getConfig('refresh_token', $this->disktag);
                if (!$refresh_token) {
                    $html = 'No refresh_token config, please AddDisk again or wait minutes.<br>' . $this->disktag;
                    $title = 'Error';
                    return message($html, $title, 201);
                }
                $response = $this->get_access_token1($refresh_token);
                if (!$response) return message($this->error['body'], $this->error['stat'] . ' Error', $this->error['stat']);
            }

            $api = "/v1.0/organization";
            $arr = $this->MSAPI('GET', $api);
            if ($arr['stat']!=200) {
                $html = $arr['stat'] . json_encode(json_decode($arr['body'], true), JSON_PRETTY_PRINT);
                return message($html);
            }
            $result = json_decode($arr['body'], true)['value'][0];
            $tmp['tenant_id'] = $result['id'];
            $tmp['defaultCountry'] = $result['countryLetterCode'];

            //$api = '/v1.0/applications/' . getConfig('client_resid', $this->disktag);
            //$result = $this->MSAPI('GET', $api);
            //return message($result['stat'] . json_encode(json_decode($result['body']), JSON_PRETTY_PRINT), 'aaa', $result['stat']);

            $api = '/v1.0/applications/' . getConfig('client_resid', $this->disktag) . '/addPassword';
            $data = null;
            $data['passwordCredential']['displayName'] = '100year';
            $data['passwordCredential']['endDateTime'] = date('Y-m-d\TH:i:s\Z', strtotime("+100 year"));
            $arr2 = $this->MSAPI('POST', $api, json_encode($data));
            error_log1($arr2['body']);
            $result = json_decode($arr2['body'], true);
            $client_secret = $result['secretText'];
            if (!$client_secret) return message('创建secret失败，请尝试刷新能否成功<br>' . $arr2['stat'] . json_encode($result, JSON_PRETTY_PRINT), 'Create secret fail', $arr2['stat']);
            $tmp['client_secret'] = $client_secret;

            $response = setConfigResponse( setConfig($tmp, $this->disktag) );
            if (api_error($response)) {
                $html = api_error_msg($response);
                $title = 'Error';
                return message($html, $title, 201);
            } else {
                $str .= '<meta http-equiv="refresh" content="5;URL=' . $url . '?setup&disktag=' . $_GET['disktag'] . '">
                tenant_id: ' . $tmp['tenant_id'] . '<br>
                client_secret: ' . $client_secret . '<br>';
                //$str = json_encode(json_decode($arr['body'], true), JSON_PRETTY_PRINT);
                return message($str, getconstStr('Wait'), 201);
            }
        }

        if (isset($_GET['CreateClient0'])) {
            if ($this->access_token == '') {
                $refresh_token = getConfig('refresh_token', $this->disktag);
                if (!$refresh_token) {
                    $html = 'No refresh_token config, please AddDisk again or wait minutes.<br>' . $this->disktag;
                    $title = 'Error';
                    return message($html, $title, 201);
                }
                $response = $this->get_access_token1($refresh_token);
                if (!$response) return message($this->error['body'], $this->error['stat'] . ' Error', $this->error['stat']);
            }
            //$response = curl('GET', $this->api_url . '/v1.0/me', '', ['Authorization'=>'Bearer ' . $ret['access_token']]);
            $response = $this->MSAPI('GET', '/v1.0/me');
            error_log1('GET ME ' . $response['body']);
            $result = json_decode($response['body'], true);
            $tmp['thisAccount'] = $result['userPrincipalName'];

            $api = '/v1.0/applications';
            $data = null;
            $data['displayName'] = 'OfficeAdmin_' . date("Ymd_His");
            $data['requiredResourceAccess'][0]["resourceAppId"] = "00000003-0000-0000-c000-000000000000";
            $data['requiredResourceAccess'][0]["resourceAccess"][0]['id'] = "9e3f62cf-ca93-4989-b6ce-bf83c28f9fe8";
            $data['requiredResourceAccess'][0]["resourceAccess"][0]['type'] = "Role";
            $data['requiredResourceAccess'][0]["resourceAccess"][1]['id'] = "741f803b-c850-494e-b5df-cde7c675a1ca";
            $data['requiredResourceAccess'][0]["resourceAccess"][1]['type'] = "Role";
            $data['requiredResourceAccess'][0]["resourceAccess"][2]['id'] = "19dbc75e-c2e2-444c-a770-ec69d8559fc7";
            $data['requiredResourceAccess'][0]["resourceAccess"][2]['type'] = "Role";
            //$data['requiredResourceAccess'][0]["resourceAccess"][3]['id'] = "0e263e50-5827-48a4-b97c-d940288653c7";
            //$data['requiredResourceAccess'][0]["resourceAccess"][3]['type'] = "Scope";
            $arr1 = $this->MSAPI('POST', $api, json_encode($data));
            //$html = $arr1['stat'] . json_encode(json_decode($arr1['body'], true), JSON_PRETTY_PRINT);
            //return message($html);
            if ($arr1['stat']!=201) return message('创建应用失败，请尝试刷新能否成功<br>' . $arr1['stat'] . json_encode(json_decode($arr1['body']), JSON_PRETTY_PRINT), 'Create client fail', $arr1['stat']);
            //if (!($arr['stat']==200||$arr['stat']==403||$arr['stat']==400||$arr['stat']==404)) return message($arr['stat'] . json_encode(json_decode($arr['body']), JSON_PRETTY_PRINT), 'Get followedSites', $arr['stat']);
            //error_log1($arr['body']);
            $result1 = json_decode($arr1['body'], true);
            $resId = $result1['id'];
            $client_id = $result1['appId'];

            $tmp['client_resid'] = $resId;
            $tmp['client_id'] = $client_id;
            
            $response = setConfigResponse( setConfig($tmp, $this->disktag) );
            if (api_error($response)) {
                $html = api_error_msg($response);
                $title = 'Error';
                return message($html, $title, 201);
            }

            if (get_class($this)=='Onedrive') $host = 'portal.azure.com';
            elseif (get_class($this)=='OnedriveCN') $host = 'portal.azure.cn';
            else return message('Drive ver Error', 'Error', 201);
            $title = 'Create Client';
            $html = 'Account: ' . $tmp['thisAccount'] . '<br>
            client_id: ' . $client_id . '<br>
            <a href="https://' . $host . '/#blade/Microsoft_AAD_RegisteredApps/ApplicationMenuBlade/CallAnAPI/appId/' . $client_id . '/isMSAApp/" target="_blank">Click here</a> add scope: <br>
            点击跳转，去代表组织同意，再来这点下一步。<br>
            Then <a href="' . $url . '?AddDisk=' . get_class($this) . '&disktag=' . $_GET['disktag'] . '&CreateClient1">next step下一步</a>';
            return message($html, $title, 201);
        }

        if (isset($_GET['install2']) && isset($_GET['code'])) {
            $tmp = curl('POST', $this->oauth_url . 'token', 'client_id=' . $this->client_id .'&client_secret=' . $this->client_secret . '&grant_type=authorization_code&requested_token_use=on_behalf_of&redirect_uri=' . $this->redirect_uri . '&code=' . $_GET['code']);
            if ($tmp['stat']==200) $ret = json_decode($tmp['body'], true);
            if (isset($ret['refresh_token'])) {
                $refresh_token = $ret['refresh_token'];
                $str = '
        refresh_token :<br>';
                $str .= '
        <textarea readonly style="width: 95%">' . $refresh_token . '</textarea><br><br>
        ' . getconstStr('SavingToken') . '
        <script>
            var texta=document.getElementsByTagName(\'textarea\');
            for(i=0;i<texta.length;i++) {
                texta[i].style.height = texta[i].scrollHeight + \'px\';
            }
        </script>';
                $tmptoken['refresh_token'] = $refresh_token;
                $tmptoken['token_expires'] = time()+7*24*60*60;
                $response = setConfigResponse( setConfig($tmptoken, $this->disktag) );
                if (api_error($response)) {
                    $html = api_error_msg($response);
                    $title = 'Error';
                    return message($html, $title, 201);
                } else {
                    savecache('tmp_access_token', $ret['access_token'], $this->disktag, $ret['expires_in'] - 60);
                    $str .= '
                <meta http-equiv="refresh" content="3;URL=' . $url . '?AddDisk=' . get_class($this) . '&disktag=' . $_GET['disktag'] . '&CreateClient0">';
                    return message($str, getconstStr('Wait') . ' 3s', 201);
                }
            }
            return message('<pre>' . json_encode(json_decode($tmp['body']), JSON_PRETTY_PRINT) . '</pre>', $tmp['stat']);
            //return message('<pre>' . json_encode($ret, JSON_PRETTY_PRINT) . '</pre>', 500);
        }

        if (isset($_GET['install1'])) {
            if (get_class($this)=='Onedrive' || get_class($this)=='OnedriveCN') {
                return message('
    <a href="" id="a1">' . getconstStr('JumptoOffice') . '</a>
    <script>
        url=location.protocol + "//" + location.host + "' . $url . '?install2&disktag=' . $_GET['disktag'] . '&AddDisk=' . get_class($this) . '";
        url="' . $this->oauth_url . 'authorize?scope=' . $this->scope . '&response_type=code&client_id=' . $this->client_id . '&redirect_uri=' . $this->redirect_uri . '&state=' . '"+encodeURIComponent(url);
        document.getElementById(\'a1\').href=url;
        //window.open(url,"_blank");
        location.href = url;
    </script>
    ', getconstStr('Wait') . ' 1s', 201);
            } else {
                return message('Something error, retry after a few seconds.', 'Retry', 201);
            }
        }

        if (isset($_GET['install0'])) {
            if ($_POST['disktag_add']!='') {
                $_POST['disktag_add'] = preg_replace('/[^0-9a-zA-Z|_]/i', '', $_POST['disktag_add']);
                $f = substr($_POST['disktag_add'], 0, 1);
                if (strlen($_POST['disktag_add'])==1) $_POST['disktag_add'] .= '_';
                if (isCommonEnv($_POST['disktag_add'])) {
                    return message('Do not input ' . $envs . '<br><button onclick="location.href = location.href;">'.getconstStr('Refresh').'</button>', 'Error', 201);
                } elseif (!(('a'<=$f && $f<='z') || ('A'<=$f && $f<='Z'))) {
                    return message('Please start with letters<br><button onclick="location.href = location.href;">'.getconstStr('Refresh').'</button>
                    <script>
                    var expd = new Date();
                    expd.setTime(expd.getTime()+1);
                    var expires = "expires="+expd.toGMTString();
                    document.cookie=\'disktag=; path=/; \'+expires;
                    </script>', 'Error', 201);
                }

                $tmp = null;
                // clear envs
                foreach ($EnvConfigs as $env => $v) if (isInnerEnv($env)) $tmp[$env] = '';

                $tmp['disktag_add'] = $_POST['disktag_add'];
                $tmp['diskname'] = $_POST['diskname'];
                $tmp['Driver'] = $_POST['Drive_ver'];

                $response = setConfigResponse( setConfig($tmp, $this->disktag) );
                if (api_error($response)) {
                    $html = api_error_msg($response);
                    $title = 'Error';
                } else {
                    $title = getconstStr('MayinEnv');
                    $html = getconstStr('Wait') . ' 3s<meta http-equiv="refresh" content="3;URL=' . $url . '?install1&disktag=' . $_GET['disktag'] . '&AddDisk=' . $_POST['Drive_ver'] . '">';
                }
                return message($html, $title, 201);
            }
        }

        $html = '
<div>
    <form id="form1" action="" method="post" onsubmit="return notnull(this);">
        ' . getconstStr('DiskTag') . ': (' . getConfig('disktag') . ')
        <input type="text" name="disktag_add" placeholder="' . getconstStr('EnvironmentsDescription')['disktag'] . '" style="width:100%"><br>
        ' . getconstStr('DiskName') . ':
        <input type="text" name="diskname" placeholder="' . getconstStr('EnvironmentsDescription')['diskname'] . '" style="width:100%"><br>
        <br>
        <div>';
        if (get_class($this)=='Onedrive') $html .='
            <label><input type="radio" name="Drive_ver" value="Onedrive" checked>MS: ' . getconstStr('DriveVerMS') . '</label><br>';
        elseif (get_class($this)=='OnedriveCN') $html .='
            <label><input type="radio" name="Drive_ver" value="OnedriveCN" checked>CN: ' . getconstStr('DriveVerCN') . '</label><br>';
        $html .= '
        </div>
        <br>';
        if ($_SERVER['language']=='zh-cn') $html .= '你要理解 scfonedrive.github.io 是github上的静态网站，<br><font color="red">除非github真的挂掉</font>了，<br>不然，稍后你如果<font color="red">连不上</font>，请检查你的运营商或其它“你懂的”问题！<br>';
        $html .='
        <input type="submit" value="' . getconstStr('Submit') . '">
    </form>
    or<br> <a href="?AddDisk=Onedrive&AddClient">Click here input id&secret manually<br> 点这里手动输入id与secret</a>
</div>
    <script>
        function notnull(t)
        {
            if (t.disktag_add.value==\'\') {
                alert(\'' . getconstStr('DiskTag') . '\');
                return false;
            }
            envs = [' . $envs . '];
            if (envs.indexOf(t.disktag_add.value)>-1) {
                alert("Do not input ' . $envs . '");
                return false;
            }
            var reg = /^[a-zA-Z]([_a-zA-Z0-9]{1,20})$/;
            if (!reg.test(t.disktag_add.value)) {
                alert(\'' . getconstStr('TagFormatAlert') . '\');
                return false;
            }
            if (t.Drive_ver.value==\'\') {
                    alert(\'Select a Driver\');
                    return false;
            }

            document.getElementById("form1").action="?install0&disktag=" + t.disktag_add.value + "&AddDisk=" + t.Drive_ver.value;
            return true;
        }
    </script>';
        $title = 'Account';
        return message($html, $title, 201);
    }

    protected function get_access_token() {
        if (!($this->access_token = getcache('access_token', $this->disktag))) {
            $url = $this->oauth_url1;
            $data = 'grant_type=client_credentials&client_id=' . $this->client_id . '&client_secret=' . $this->client_secret . '&scope=' . $this->scope;
            $p=0;
            while ($response['stat']==0&&$p<3) {
                $response = curl('POST', $url, $data);
                $p++;
            }
            if ($response['stat']==200) $ret = json_decode($response['body'], true);
            if (!isset($ret['access_token'])) {
                //error_log1($this->oauth_url . 'token' . '?client_id=' . $this->client_id . '&client_secret=' . $this->client_secret . '&grant_type=refresh_token&requested_token_use=on_behalf_of&refresh_token=' . substr($refresh_token, 0, 20) . '******' . substr($refresh_token, -20));
                error_log1('failed to get [' . $this->disktag . '] access_token. response: ' . $response['body']);
                $response['body'] = json_encode(json_decode($response['body']), JSON_PRETTY_PRINT);
                $response['body'] .= "<br>\n" . $url . "<br>\n" . $data . "<br>\n" . 'failed to get [' . $this->disktag . '] access_token.';
                $this->error = $response;
                return false;
            }
            $tmp = $ret;
            $tmp['access_token'] = substr($tmp['access_token'], 0, 10) . '******';
            if (isset($tmp['refresh_token'])) $tmp['refresh_token'] = substr($tmp['refresh_token'], 0, 10) . '******';
            error_log1('[' . $this->disktag . '] Get access token:' . json_encode($tmp, JSON_PRETTY_PRINT));
            $this->access_token = $ret['access_token'];
            savecache('access_token', $this->access_token, $this->disktag, $ret['expires_in'] - 300);
        }
        return true;
    }
    protected function get_access_token1($refresh_token, $client_id = '', $client_secret = '') {
        if (!$refresh_token) {
            $tmp['stat'] = 0;
            $tmp['body'] = 'No refresh_token';
            $this->error = $tmp;
            return false;
        }
        if (!$client_id) $client_id = $this->client_id;
        if (!$client_secret) $client_secret = $this->client_secret;
        if (!($this->access_token = getcache('tmp_access_token', $this->disktag))) {
            $p=0;
            while ($response['stat']==0&&$p<3) {
                $response = curl('POST', $this->oauth_url . 'token', 'client_id=' . $client_id . '&client_secret=' . $client_secret . '&grant_type=refresh_token&requested_token_use=on_behalf_of&refresh_token=' . $refresh_token );
                $p++;
            }
            if ($response['stat']==200) $ret = json_decode($response['body'], true);
            if (!isset($ret['access_token'])) {
                error_log1($this->oauth_url . 'token' . '?client_id=' . $client_id . '&client_secret=' . $client_secret . '&grant_type=refresh_token&requested_token_use=on_behalf_of&refresh_token=' . substr($refresh_token, 0, 20) . '******' . substr($refresh_token, -20));
                error_log1('failed to get [' . $this->disktag . '] access_token. response: ' . $response['body']);
                $response['body'] = json_encode(json_decode($response['body']), JSON_PRETTY_PRINT);
                $response['body'] .= "<br>\n" . 'failed to get [' . $this->disktag . '] access_token.';
                $this->error = $response;
                return false;
                //throw new Exception($response['stat'].', failed to get ['.$this->disktag.'] access_token.'.$response['body']);
            }
            $tmp = $ret;
            $tmp['access_token'] = substr($tmp['access_token'], 0, 10) . '******';
            $tmp['refresh_token'] = substr($tmp['refresh_token'], 0, 10) . '******';
            error_log1('[' . $this->disktag . '] Get access token:' . json_encode($tmp, JSON_PRETTY_PRINT));
            $this->access_token = $ret['access_token'];
            savecache('tmp_access_token', $this->access_token, $this->disktag, $ret['expires_in'] - 300);
            if (time()>getConfig('token_expires', $this->disktag)) setConfig([ 'refresh_token' => $ret['refresh_token'], 'token_expires' => time()+7*24*60*60 ], $this->disktag);
        }
        return true;
    }

    protected function MSAPI($method, $path, $data = '', $headers = [])
    {
        $activeLimit = getConfig('activeLimit', $this->disktag);
        if ($activeLimit!='') {
            if ($activeLimit>time()) {
                $tmp['error']['code'] = 'Retry-After';
                $tmp['error']['message'] = 'MS limit until ' . date('Y-m-d H:i:s', $activeLimit);
                return [ 'stat' => 429, 'body' => json_encode($tmp) ];
            } else {
                setConfig(['activeLimit' => ''], $this->disktag);
            }
        }
        if (substr($path,0,7) == 'http://' or substr($path,0,8) == 'https://') {
            $url = $path;
        } else {
            $url = $this->api_url . $this->ext_api_url;
            if ($method=='GET') {
                $url .= $path;
            } else {
                if ($path=='' or $path=='/') {
                    $url .= '/';
                } else {
                    $url .= ':' . $path;
                    if (substr($url,-1)=='/') $url=substr($url,0,-1);
                }
                if ($method=='PUT') {
                    if ($path=='' or $path=='/') {
                        $url .= 'content';
                    } else {
                        $url .= ':/content';
                    }
                    $headers['Content-Type'] = 'text/plain';
                } elseif ($method=='PATCH') {
                    $headers['Content-Type'] = 'application/json';
                } elseif ($method=='POST') {
                    $headers['Content-Type'] = 'application/json';
                } elseif ($method=='DELETE') {
                    $headers['Content-Type'] = 'application/json';
                } else {
                    if ($path=='' or $path=='/') {
                        $url .= $method;
                    } else {
                        $url .= ':/' . $method;
                    }
                    $method='POST';
                    $headers['Content-Type'] = 'application/json';
                }
            }
        }
        $headers['Authorization'] = 'Bearer ' . $this->access_token;
        if (!isset($headers['Accept'])) $headers['Accept'] = '*/*';
        //if (!isset($headers['Referer'])) $headers['Referer'] = $url;*
        $sendHeaders = array();
        foreach ($headers as $headerName => $headerVal) {
            $sendHeaders[] = $headerName . ': ' . $headerVal;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $sendHeaders);
        $retry = 0;
        $response = [];
        while ($retry<3&&!$response['stat']) {
            $response['body'] = curl_exec($ch);
            $response['stat'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $retry++;
        }
        //$response['Location'] = curl_getinfo($ch);
        if ($response['stat']==429) {
            $res = json_decode($response['body'], true);
            $retryAfter = $res['error']['retryAfterSeconds'];
            $retryAfter_n = (int)$retryAfter;
            if ($retryAfter_n>0) {
                $tmp1['activeLimit'] = $retryAfter_n + time();
                setConfig($tmp1, $this->disktag);
            }
        }
        curl_close($ch);
        /*error_log1($response['stat'].'
    '.$response['body'].'
    '.$url.'
    ');*/
        return $response;
    }

}

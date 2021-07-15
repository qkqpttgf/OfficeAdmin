<?php
if (!class_exists('Onedrive')) require 'Onedrive.php';

class OnedriveCN extends Onedrive {

    function __construct($tag) {
        $this->disktag = $tag;
        $this->redirect_uri = 'https://scfonedrive.github.io';
        $this->oauth_url = 'https://login.partner.microsoftonline.cn/common/oauth2/v2.0/';
        $this->api_url = 'https://microsoftgraph.chinacloudapi.cn';
        $default_client_id = '31f3bed5-b9d9-4173-86a4-72c73d278617';
        $default_client_secret = 'P5-ZNtFK-tT90J.We_-DcsuB8uV7AfjL8Y';
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
            $this->oauth_url1 = 'https://login.partner.microsoftonline.cn/' . $this->tenant_id . '/oauth2/v2.0/token';
            $this->scope = urlencode($this->scope);
            if (getConfig('refresh_token', $tag)) $res = $this->get_access_token1(getConfig('refresh_token', $tag), $default_client_id, $default_client_secret);
            $res = $this->get_access_token();
        }
    }

}

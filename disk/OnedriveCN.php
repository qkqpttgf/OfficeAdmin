<?php
if (!class_exists('Onedrive')) require 'Onedrive.php';

class OnedriveCN extends Onedrive {

    function __construct($tag) {
        $this->disktag = $tag;
        $this->redirect_uri = 'https://scfonedrive.github.io';
        $this->oauth_url = 'https://login.partner.microsoftonline.cn/common/oauth2/v2.0/';
        $this->api_url = 'https://microsoftgraph.chinacloudapi.cn';
        $this->scope = 'https://microsoftgraph.chinacloudapi.cn/Application.ReadWrite.All https://microsoftgraph.chinacloudapi.cn/Directory.ReadWrite.All https://microsoftgraph.chinacloudapi.cn/User.ReadWrite.All https://microsoftgraph.chinacloudapi.cn/RoleManagement.ReadWrite.Directory offline_access';
        $this->scope = urlencode($this->scope);
        if ($tag!='') {
            if (isset($_GET['AddDisk'])) {
                $this->client_id = '31f3bed5-b9d9-4173-86a4-72c73d278617';
                $this->client_secret = 'P5-ZNtFK-tT90J.We_-DcsuB8uV7AfjL8Y';
            } else {
                $this->client_id = getConfig('client_id', $tag);
                $this->client_secret = getConfig('client_secret', $tag);
            }
            $this->client_secret = urlencode($this->client_secret);
            $this->tenant_id = getConfig('tenant_id', $tag);
            $this->oauth_url1 = 'https://login.partner.microsoftonline.cn/' . $this->tenant_id . '/oauth2/v2.0/token';
            $res = $this->get_access_token();
            //$res = $this->get_access_token1(getConfig('refresh_token', $tag));
        }
    }

}

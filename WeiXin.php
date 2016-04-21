<?php
namespace common\components\weixin;

class WeiXin {

    /**
     * @Title: getSignPackage
     * @Description: todo(得到jssdk配置签名数据)
     * @author zhouchao
     * @param string $url
     * @return  array  返回类型
     */
    public function getSignPackage($url='') {

        if(empty($url)){

            // 注意 URL 一定要动态获取，不能 hardcode.
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

            $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        }

        $jsapiTicket = $this->getJsApiTicket();

        $timestamp = time();

        $nonceStr = WeiXinHelper::createNonceStr();

        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

        $signature = sha1($string);

        $signPackage = array(
            "appId"     => WeiXinConfig::APPID,
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "url"       => $url,
            "signature" => $signature,
            "rawString" => $string
        );

        return $signPackage;
    }

    /**
     * @Title: getOauthUrlForCode
     * @Description: todo(获取 code 的微信跳转地址)
     * @author zhouchao
     * @param $redirectUrl
     * @param string $scope 拉取用户信息(需scope为 snsapi_userinfo)会跳转授权登陆页面 只得openid 为snsapi_base不会跳转授权页面
     * @return  string  返回类型
     */
    public function getOauthUrlForCode($redirectUrl,$scope="snsapi_base"){

        $urlObj["appid"] = WeiXinConfig::APPID;

        $urlObj["redirect_uri"] = "$redirectUrl";

        $urlObj["response_type"] = "code";

        $urlObj["scope"] = $scope;

        $urlObj["state"] = "STATE"."#wechat_redirect";

        $bizString = WeiXinHelper::toUrlParams($urlObj);

        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;

    }

    /**
     * @Title: getOpenId
     * @Description: todo(得到微信用户唯一标识)
     * @author zhouchao
     * @param $code 微信回调后的code 前端传递过来
     * @return  array  返回类型
     */
    public function getOpenId($code){

        $url = $this->getOauthUrlForOpenid($code);

        $res = WeiXinHelper::httpGet($url);

        $data = json_decode($res,true);

        if(isset($data['errcode'])){
            return null;
        }

        return $data;

    }

    /**
     * @Title: getUserInfo
     * @Description: todo(得到微信用户信息)
     * @author zhouchao
     * @param $access_token
     * @param $openid
     * @return  mixed  返回类型
     */
    public function getUserInfo($access_token,$openid){

        $url = $this->getOauthUrlForUserInfo($access_token,$openid);

        $res = WeiXinHelper::httpGet($url);

        $data = json_decode($res,true);

        return $data;
    }

    /**
     * @Title: getJsApiTicket
     * @Description: todo(获取签名凭证)
     * @author zhouchao
     * @return  mixed  返回类型
     */
    private function getJsApiTicket() {

        $data = json_decode(WeiXinHelper::get_php_file("jsapi_ticket.php"));

        if ($data->expire_time < time()) {

            $accessToken = $this->getAccessToken();

            // 如果是企业号用以下 URL 获取 ticket
            // $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";

            $res = json_decode(WeiXinHelper::httpGet($url));

            $ticket = $res->ticket;

            if ($ticket) {

                $data->expire_time = time() + 7000;

                $data->jsapi_ticket = $ticket;

                WeiXinHelper::set_php_file("jsapi_ticket.php", json_encode($data));
            }

        } else {

            $ticket = $data->jsapi_ticket;

        }

        return $ticket;
    }

    /**
     * @Title: getAccessToken
     * @Description: todo(得到接口凭证)
     * @author zhouchao
     * @return  mixed  返回类型
     */
    private function getAccessToken() {

        // access_token 应该全局存储与更新，以下代码以写入到文件中做示例
        $data = json_decode(WeiXinHelper::get_php_file("access_token.php"));

        if ($data->expire_time < time()) {

            // 如果是企业号用以下URL获取access_token
            // $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$appId&corpsecret=$appSecret";
            $appId = WeiXinConfig::APPID;
            $appSecret = WeiXinConfig::APPSECRET;
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appId&secret=$appSecret";

            $res = json_decode(WeiXinHelper::httpGet($url));

            $access_token = $res->access_token;

            if ($access_token) {

                $data->expire_time = time() + 7000;

                $data->access_token = $access_token;

                WeiXinHelper::set_php_file("access_token.php", json_encode($data));

            }
        } else {

            $access_token = $data->access_token;

        }

        return $access_token;
    }

    /**
     * @Title: getOauthUrlForOpenid
     * @Description: todo(得到获取openid的Url)
     * @author zhouchao
     * @param $code
     * @return  string  返回类型
     */
    private function getOauthUrlForOpenid($code){

        $urlObj["appid"] = WeiXinConfig::APPID;

        $urlObj["secret"] = WeiXinConfig::APPSECRET;

        $urlObj["code"] = $code;

        $urlObj["grant_type"] = "authorization_code";

        $bizString = WeiXinHelper::toUrlParams($urlObj);

        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;

    }

    private function getOauthUrlForUserInfo($access_token,$openid){

        $urlObj["access_token"] = $access_token;

        $urlObj["openid"] = $openid;

        $urlObj["lang"] = 'zh_CN';

        $bizString = WeiXinHelper::formatBizQueryParaMap($urlObj, false);

        return "https://api.weixin.qq.com/sns/userinfo?".$bizString;


    }

}


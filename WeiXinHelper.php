<?php

namespace common\components\weixin;

use yii\base\ErrorException;

class WeiXinHelper {


    /**
     * @Title: createNonceStr
     * @Description: todo(产生的随机字符串)
     * @author zhouchao
     * @param int $length
     * @return  string  返回类型
     */
    public static function createNonceStr($length = 32) {

        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * @Title: toUrlParams
     * @Description: todo(拼接签名地址 key=value)
     * @author zhouchao
     * @param $urlObj
     * @return  string  返回类型
     */
    public static function toUrlParams($urlObj){

        $buff = "";

        foreach ($urlObj as $k => $v)
        {
            if($k != "sign"){

                $buff .= $k . "=" . $v . "&";

            }
        }

        $buff = trim($buff, "&");

        return $buff;
    }

    /**
     * @Title: formatBizQueryParaMap
     * @Description: todo(格式化参数)
     * @author zhouchao
     * @param $paraMap
     * @param $urlEncode
     * @return  string  返回类型
     */
    public static function formatBizQueryParaMap($paraMap, $urlEncode){

        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if($urlEncode)
            {
                $v = urlencode($v);
            }
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar = '';

        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff)-1);
        }
        return $reqPar;
    }

    /**
     * @Title: httpGet
     * @Description: todo(发起Get请求)
     * @author zhouchao
     * @param $url
     * @return  mixed  返回类型
     */
    public static function httpGet($url) {

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 500);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_URL, $url);

        $res = curl_exec($curl);
        curl_close($curl);

        return $res;
    }

    /**
     * @Title: postXmlCurl
     * @Description: todo(以post请求提交Xml)
     * @author zhouchao
     * @param $xml
     * @param $url
     * @param bool $useCert
     * @param int $second
     * @return  mixed  返回类型
     */
    public static function postXmlCurl($xml, $url, $useCert = false, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);

        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if($data){
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw new ErrorException("curl出错，错误码:$error");
        }
    }

    /**
     * @Title: ArrayToXml
     * @Description: todo(数组转成xml)
     * @author zhouchao
     * @param $data
     * @return  string  返回类型
     */
    public static function ArrayToXml($data){

        if(is_array($data)){

            $xml = "<xml>";
            foreach ($data as $key=>$val)
            {
                if (is_numeric($val)){
                    $xml.="<".$key.">".$val."</".$key.">";
                }else{
                    $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
                }
            }
            $xml.="</xml>";
            return $xml;

        }else{
            return "<xml></xml>";
        }
    }

    public static function XmlToArray($xml){
        if(!$xml){
            throw new \Exception("xml数据异常！");
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);

        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

    }

    /**
     * @Title: get_php_file
     * @Description: todo(得到cache的php文件)
     * @author zhouchao
     * @param $filename
     * @return  string  返回类型
     */
    public static function get_php_file($filename) {

        return trim(substr(file_get_contents(__DIR__.'/cache/'.$filename), 15));

    }

    /**
     * @Title: set_php_file
     * @Description: todo(设置cache的php文件)
     * @author zhouchao
     * @param $filename
     * @param $content
     * @return  void  返回类型
     */
    public static function set_php_file($filename, $content) {

        $fp = fopen(__DIR__.'/cache/'.$filename, "w");

        fwrite($fp, "<?php exit();?>" . $content);

        fclose($fp);
    }

    /**
     * 获取毫秒级别的时间戳
     */
    public static function getMillisecond()
    {
        //获取毫秒的时间戳
        $time = explode ( " ", microtime () );
        $time = $time[1] . ($time[0] * 1000);
        $time2 = explode( ".", $time );
        $time = $time2[0];
        return $time;
    }



}
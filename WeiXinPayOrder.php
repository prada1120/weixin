<?php
namespace common\components\weixin;

use yii\base\ErrorException;

class WeiXinPayOrder {

    private $out_trade_no;
    private $body;
    private $total_fee;
    private $trade_type;

    /**
     * @var 下面的都是不必填数据
     */
    private $detail;
    private $attach;
    private $fee_type;
    private $time_start;
    private $time_expire;
    private $goods_tag;
    private $product_id;
    private $limit_pay;
    private $openid;


    public function __construct($out_trade_no,$body,$total_fee,$trade_type,$openid='') {

        $this->out_trade_no = $out_trade_no;
        $this->body= $body;
        $this->total_fee = $total_fee;
        $this->openid = $openid;
        $this->trade_type = $trade_type;
    }

    /**
     * @Title: __set
     * @Description: todo()
     * @author zhouchao
     * @param $name
     * @param $value
     * @return  void  返回类型
     */
    public function __set($name,$value){
        $this->$name = $value;
    }

    public function getParameters(){

        $unifiedOrderResult = $this->unifiedOrder();//统一下单

        //生成支付参数
        if(!array_key_exists("appid", $unifiedOrderResult)
            || !array_key_exists("prepay_id", $unifiedOrderResult)
            || $unifiedOrderResult['prepay_id'] == "")
        {
            throw new \Exception("参数错误");
        }

        $timeStamp = time();
        $appId = WeiXinConfig::APPID;
        $nonceStr = WeiXinHelper::createNonceStr();
        $mch_id = WeiXinConfig::MCHID;
        $prepay_id = $unifiedOrderResult['prepay_id'];

        if($this->trade_type==WeiXinConfig::PAY_TYPE_JSAPI){

            $JsApiParams = array(
                'appId'=>$appId,
                'timeStamp'=>"$timeStamp",
                'nonceStr'=>$nonceStr,
                'package'=>"prepay_id=".$prepay_id,
                'signType'=>"MD5",
            );

            $JsApiParams['paySign'] = $this->makeSign($JsApiParams);

            return json_encode($JsApiParams);

        }elseif($this->trade_type==WeiXinConfig::PAY_TYPE_APP){

            $appParams = array(
                'appid'=>$appId,
                'noncestr'=>$nonceStr,
                'package'=>"Sign=WXPay",
                'partnerid'=>$mch_id,
                'prepayid'=>$prepay_id,
                'timestamp'=>"$timeStamp",
            );

            $appParams['sign'] = $this->makeSign($appParams);

            return json_encode($appParams);
        }else{
            return json_encode([]);
        }
    }

    /**
     * @Title: unifiedOrder
     * @Description: todo(统一下单)
     * @author zhouchao
     * @return  array  返回类型
     */
    private function unifiedOrder(){

        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";//微信统一下单接口地址

        $params = array(
            'appid'=>WeiXinConfig::APPID,//公众账号ID
            'mch_id'=>WeiXinConfig::MCHID,//商户号
            'nonce_str'=>WeiXinHelper::createNonceStr(32),//随机字符串
            'notify_url'=>WeiXinConfig::NOTIFY_URL,//通知地址
            'body'=>$this->body,//商品描述
            'detail'=>$this->detail,//商品详情
            'attach'=>$this->attach,//附加数据
            'out_trade_no'=>$this->out_trade_no,//商户订单号
            'fee_type'=>$this->fee_type,//货币类型
            'total_fee'=>$this->total_fee,//总金额
            'spbill_create_ip'=>$_SERVER['REMOTE_ADDR'],//终端IP
            'time_start'=>$this->time_start,//交易起始时间
            'time_expire'=>$this->time_expire,//交易结束时间
            'goods_tag'=>$this->goods_tag,//商品标记
            'trade_type'=>$this->trade_type,//交易类型
            'product_id'=>$this->product_id,//商品ID
            'limit_pay'=>$this->limit_pay,//指定支付方式
            'openid'=>$this->openid,//用户标识
        );

        $params = $this->validatePayParams($params);

        $params['sign'] = $this->makeSign($params);

        $xml = WeiXinHelper::ArrayToXml($params);//生成参数xml

        $response = WeiXinHelper::postXmlCurl($xml,$url);//请求微信接口提交xml

        $startTimeStamp = WeiXinHelper::getMillisecond();//请求开始时间

        $result = $this->checkSign($response);

        $this->reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;

    }

    private function validatePayParams($params){

        $params = array_filter($params);

        //检测必填参数

        if(empty($params['out_trade_no'])){
            throw new \Exception("缺少统一支付接口必填参数out_trade_no！");
        } elseif(empty($params['body'])){
            throw new \Exception("缺少统一支付接口必填参数body！");
        } elseif(empty($params['total_fee'])){
            throw new \Exception("缺少统一支付接口必填参数total_fee！");
        } elseif(empty($params['trade_type'])){
            throw new \Exception("缺少统一支付接口必填参数trade_type！");
        }

        //关联参数
        if($params['trade_type'] == WeiXinConfig::PAY_TYPE_JSAPI && empty($params['openid'])){
            throw new \Exception("统一支付接口中，缺少必填参数openid！trade_type为JSAPI时，openid为必填参数！");
        }

        return $params;
    }

    /**
     * @Title: makeSign
     * @Description: todo(生成签名)
     * @author zhouchao
     * @param $params
     * @return  string  返回类型
     */
    private function makeSign($params)
    {
        //签名步骤一：按字典序排序参数
        ksort($params);
        $string = WeiXinHelper::toUrlParams($params);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=".WeiXinConfig::KEY;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    /**
     * @Title: checkSign
     * @Description: todo(验证签名)
     * @author zhouchao
     * @param $xml
     * @return  bool|mixed  返回类型
     */
    private function checkSign($xml){

        $params = WeiXinHelper::XmlToArray($xml);

        if($params['return_code'] != 'SUCCESS'){
            return $params;
        }

        if(empty($params['sign'])){
            throw new \Exception("数据异常");
        }

        $sign = $this->makeSign($params);

        if($sign!=$params['sign']){
            throw new \Exception("签名错误！");
        }

        return $params;

    }

    /**
     * @Title: reportCostTime
     * @Description: todo(上报数据， 上报的时候将屏蔽所有异常流程)
     * @author nipeiquan
     * @param $url
     * @param $startTimeStamp
     * @param $data
     * @return  void  返回类型
     */
    private function reportCostTime($url, $startTimeStamp, $data){

        if(WeiXinConfig::REPORT_LEVENL == 0){//关闭上报

            return;
        }
        //如果仅失败上报
        if(WeiXinConfig::REPORT_LEVENL == 1 &&
            array_key_exists("return_code", $data) &&
            $data["return_code"] == "SUCCESS" &&
            array_key_exists("result_code", $data) &&
            $data["result_code"] == "SUCCESS")
        {
            return;
        }

        $endTimeStamp = WeiXinHelper::getMillisecond();

        $params = array(
            'execute_time_'=>$endTimeStamp - $startTimeStamp,//执行时间
            'interface_url'=>$url,//上报对应的接口的完整URL
        );


        //返回状态码
        if(array_key_exists("return_code", $data)){
            $params['return_code'] = $data['return_code'];
        }
        //返回信息
        if(array_key_exists("return_msg", $data)){
            $params['return_msg'] = $data["return_msg"];
        }
        //业务结果
        if(array_key_exists("result_code", $data)){
            $params['result_code'] = $data["result_code"];
        }
        //错误代码
        if(array_key_exists("err_code", $data)){
            $params['err_code'] = $data["err_code"];
        }
        //错误代码描述
        if(array_key_exists("err_code_des", $data)){
            $params['err_code_des'] = $data["err_code_des"];
        }
        //商户订单号
        if(array_key_exists("out_trade_no", $data)){
            $params['out_trade_no'] = $data["out_trade_no"];
        }
        //设备号
        if(array_key_exists("device_info", $data)){
            $params['device_info'] = $data["device_info"];
        }

        try{
            $this->report($params);
        } catch (\Exception $e){
            //不做任何处理
        }

    }

    /**
     * @Title: report
     * @Description: todo(上报)
     * @author zhouchao
     * @param $inputObj
     * @param int $timeOut
     * @return  mixed  返回类型
     */
    private function report($params, $timeOut = 1)
    {
        $url = "https://api.mch.weixin.qq.com/payitil/report";
        //检测必填参数
        if(empty($params['interface_url'])) {
            throw new \Exception("接口URL，缺少必填参数interface_url！");
        }
        if(empty($params['return_code'])) {
            throw new \Exception("返回状态码，缺少必填参数return_code！");
        }
        if(empty($params['return_code'])) {
            throw new \Exception("业务结果，缺少必填参数result_code！");
        }
        if(empty($params['user_ip'])) {
            throw new \Exception("访问接口IP，缺少必填参数user_ip！");
        }
        if(empty($params['execute_time_'])) {
            throw new \Exception("接口耗时，缺少必填参数execute_time_！");
        }
        $params['appid'] = WeiXinConfig::APPID;//公众账号ID
        $params['mch_id'] = WeiXinConfig::MCHID;//公众账号ID
        $params['mch_id'] = $_SERVER['REMOTE_ADDR'];//终端ip
        $params['time'] = date("YmdHis");//商户上报时间
        $params['nonce_str'] = WeiXinHelper::createNonceStr();//随机字符串
        $params['sign'] = WeiXinPayOrder::makeSign($params);

        $xml = WeiXinHelper::ArrayToXml($params);

        $response = WeiXinHelper::postXmlCurl($xml,$url,false,30);

        return $response;
    }

}


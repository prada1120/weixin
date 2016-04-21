<?php
namespace common\components\weixin;

class WeiXinPay {

    /**
     * @Title: getParameters
     * @Description: todo(得到支付参数)
     * @author zhouchao
     * @param $out_trade_no
     * @param $body
     * @param $total_fee
     * @return  string  返回类型
     */
    public function getParameters($out_trade_no,$body,$total_fee){

        $order = new WeiXinPayOrder($out_trade_no,$body,$total_fee,WeiXinConfig::PAY_TYPE_APP,$openid='');

        return $order->getParameters();

    }

}


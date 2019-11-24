<?php
/**
 * File: AnypayAlipay.php
 * Functionality: 易付通-支付宝扫码支付
 * Date: 2019-4-15
 */
namespace Pay\AnypayAlipay;
use \Pay\notify;

class AnypayAlipay
{
	private $apiHost="https://payment.pi.do/pay/subOrder/zfb";
	private $paymethod ="AnypayAlipay";
	
	//处理请求
	public function pay($payconfig,$params)
	{
	    $return_url = $params['weburl']."/query/auto/".$params['orderid'].".html";
		$config = array(
            "total_fee" => (float)$params['money'],//原价
            "notify_url" => $payconfig['configure3'] . '/product/notify/AnypayAlipay.html',
            "return_url" => $return_url,
            "secret" => $payconfig['app_id'],
            "out_trade_no" => $params['orderid']
		);

		$api_param = self::buildRequestPara($config);
		$api_uri = $this->apiHost . "?" . $this::_create_link($api_param). "&subject=".$params['productname'];
        $money = $config['total_fee'];
        //计算关闭时间
        $closetime = 1000;
        $result = array('type'=>1,'subjump'=>0,'url'=>$api_uri,'paymethod'=>$this->paymethod,'payname'=>$payconfig['payname'],'overtime'=>$closetime,'money'=>$money);
        return array('code'=>1,'msg'=>'success','data'=>$result);
	}
	
	
	//处理返回
	public function notify($payconfig)
	{
		file_put_contents(YEWU_FILE, CUR_DATETIME.'-'.json_encode($_POST).PHP_EOL, FILE_APPEND);
		$params = $_POST;
		if (!$params){
            $params = $_GET;
        }
		ksort($params); //排序post参数
		reset($params); //内部指针指向数组中的第一个元素
        $m_order =  \Helper::load('order');
        $order = $m_order->Where(array('orderid'=>$params['out_trade_no']))->SelectOne();
		$signVerify = self::md5Verify(floatval($order['money']),$params['out_trade_no'],$params['trade_no'],$params['trade_status'],$params['sign']);
		if ($params['trade_status'] != "TRADE_SUCCESS" || !$signVerify) { //不合法的数据 KEY密钥为你的密钥
			return 'error|Notify: auth fail';
		} else { //合法的数据
			//业务处理
			$config = array('paymethod'=>$this->paymethod,'tradeid'=>$params['trade_no'],'paymoney'=>$params['total_fee'],'orderid'=>$params['out_trade_no'] );
			$notify = new \Pay\notify();
			$data = $notify->run($config);
			if($data['code']>1){
				return 'error|Notify: '.$data['msg'];
			}else{
				return 'success';
			}
		}
	}


    private static function md5Verify($p1,$p2,$p3,$p4,$sign) {
        $preStr = $p1.$p2.$p3.$p4."yft";
        $mySign = md5($preStr);
//        echo $mySign;
        if($mySign == $sign) {
            return true;
        }else {
            return false;
        }
    }

    /**
     * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
     * @param $para 需要拼接的数组
     * return 拼接完成以后的字符串
     */
    private static function _create_link($para) {
        $arg  = "";
        while (list ($key, $val) = each ($para)) {
            $arg.=$key."=".$val."&";
        }
        //去掉最后一个&字符
        $arg = substr($arg,0,count($arg)-2);

        //如果存在转义字符，那么去掉转义
        if(get_magic_quotes_gpc()){$arg = stripslashes($arg);}

        return $arg;
    }

    /**
     * 生成要请求给易付的参数数组
     * @param $para_temp 请求前的参数数组
     * @return 要请求的参数数组
     */
    private static function buildRequestPara($para_temp) {
        //除去待签名参数数组中的空值和签名参数
        $para_filter = AnypayAlipay::paraFilter($para_temp);
        //生成签名结果
        $mysign = AnypayAlipay::buildRequestMysign($para_filter);

        //签名结果与签名方式加入请求提交参数组中
        $para_filter['sign'] = $mysign;

        return $para_filter;
    }

    /**
     * 生成签名结果
     * @param $para_filter 要签名的数组
     * return 签名结果字符串
     */
    private static function buildRequestMysign($para_filter) {
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = AnypayAlipay::_create_link($para_filter);
        $mysign = MD5($prestr);
        return $mysign;
    }
    private static function md5Sign($prestr) {
        return md5($prestr);
    }

    /**
     * 除去数组中的空值和签名参数
     * @param $para 签名参数组
     * return 去掉空值与签名参数后的新签名参数组
     */
    private static function paraFilter($para) {
        $para_filter = array();
        while (list ($key, $val) = each ($para)) {
            if($key == "sign" || $val == "")
                continue;
            else
                $para_filter[$key] = $para[$key];
        }
        return $para_filter;
    }
}

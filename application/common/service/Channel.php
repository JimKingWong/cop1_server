<?php

namespace app\common\service;

use app\common\service\util\Sign;
use fast\Http;

/**
 * 通道服务
 */
class Channel 
{

    /**
     * supepay 充值通道
     */
    public static function supepayRecharge($config, $order)
    {
        // 发起充值接口
        $apiUrl = $config['gate'] . $config['url'];
        
        $domain = config('channel.domain');

        $nonceStr = Sign::generateTraceId(); // 生成随机字符串, 借用omg游戏的
        // 请求参数
        $data = [
            'countryId'                 => $config['countryId'],
            'currency'                  => $config['currency'],
            'payProduct'                => $order['channel_code'],
            'merId'                     => $config['merchantId'],
            'merOrderNo'                => $order['order_no'],
            'orderAmount'               => $order['money'], 
            'nonceStr'                  => $nonceStr,
            'customerAccount'           => '60872832',
            'lastName'                  => $order['name'],
            'customerEmail'             => $order['email'],
            'customerName'              => $order['name'],
            'customerPhone'             => $order['phone_number'],
            'customerIdentification'    => $order['identityNo'],
            'checkOut'                  => true,
            'description'               => 'Hermes Recharge',
            'callbackUrl'               => $domain . $config['callback'],
        ];
        
        // 获取sign
        $data['sign'] = Sign::common($data, $config['secret'], 'key', 0);
        ksort($data);
        // dd($apiUrl);
        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/json',
            ]
        ];

        // 发送POST请求
        $res = Http::post($apiUrl, $data, $header);
        // $res = Http::post($apiUrl, json_encode($data), $header);
        dd($res);

        // $res = Http::post($apiUrl, http_build_query($data), $header);
        $res = json_decode($res, true);
        dd($res);

        // 成功返回支付链接
        $payUrl = '';
        if($res['code'] == 200){
            $payUrl = $res['data']['url'];
        }
        return $payUrl;
    }

    /**
     * supepay 提现通道
     */
    public static function supepayWithdraw($config, $order)
    {
        // 发起提现接口
        $apiUrl = $config['gate'] . $config['url'];
        
        $domain = config('channel.domain');

        $nonceStr = Sign::generateTraceId(); // 生成随机字符串, 借用omg游戏的
        // 请求参数
        $data = [
            'countryId'                 => $config['countryId'],
            'currency'                  => $config['currency'],
            'payProduct'                => $order['channel_method'],
            'merId'                     => $config['merchantId'],
            'merOrderNo'                => $order['order_no'],
            'orderAmount'               => $order['money'], 
            'nonceStr'                  => $nonceStr,
            'customerName'              => $order['name'],
            'customerName'              => $order['name'],
            'customerIdentificationType'=> '00', // 证件类型 暂时写死
            'customerIdentification'    => $order['identityNo'],
            'checkOut'                  => true,
            'description'               => 'Hermes Withdraw',
            'callbackUrl'               => $domain . $config['callback'],
        ];
        
        // 获取sign
        $data['sign'] = Sign::common($data, $config['secret'], 'key', 0);
        // dd($data);

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/json',
            ]
        ];

        // 发送POST请求
        $res = Http::post($apiUrl, http_build_query($data), $header);
        $res = json_decode($res, true);
        
        $code = 0;
        $msg = '';
        if($res['success']){
            $code = 1;
            $msg = $res['errorCode'];
        }

        $retval = [
            'code'  => $code,
            'msg'   => $msg,
        ];
        return $retval;
    }
    
    /**
     * supepay 查询通道
     */
    public static function supepayQuery($config, $order)
    {
        // 发起提现接口
        $apiUrl = $config['gate'] . '/api/open/merchant/payment/query';
        
        // 请求参数
        $data = [
            'merchantId'        => $config['merchantId'],
            'merchantOrderNo'   => $order['order_no'],
        ];
        
        // 获取sign
        $data['sign'] = Sign::common($data, $config['secret'], 'key', 0);

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/x-www-form-urlencoded',
            ]
        ];

        // 发送POST请求
        $res = Http::post($apiUrl, http_build_query($data), $header);
        $res = json_decode($res, true);
        return $res['data'];
    }

    /**
     * u2cpay 充值通道
     */
    public static function u2cpayRecharge($config, $order)
    {
        // 发起充值接口
        $apiUrl = $config['gate'] . $config['url'];
        
        $domain = config('channel.domain');

        // 请求参数
        $data = [
            'merchantId'        => $config['merchantId'],
            'merchantOrderNo'   => $order['order_no'],
            'amount'            => $order['money'] * 100, 
            'payType'           => $config['payType'],
            'currency'          => $config['currency'],
            'content'           => $config['content'],
            'clientIp'          => getUserIP(),
            'callback'          => $domain . $config['callback'],
        ];
        
        // 获取sign
        $data['sign'] = Sign::common($data, $config['secret'], 'secret');

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/x-www-form-urlencoded',
            ]
        ];

        // 发送POST请求
        $res = Http::post($apiUrl, http_build_query($data), $header);
        $res = json_decode($res, true);
        // dd($res);

        // 成功返回支付链接
        $payUrl = '';
        if($res['success']){
            $payUrl = $res['data']['payUrl'];
        }
        return $payUrl;
    }

    /**
     * u2cpay 提现通道
     */
    public static function u2cpayWithdraw($config, $order)
    {
        // 发起提现接口
        $apiUrl = $config['gate'] . $config['url'];
        
        $domain = config('channel.domain');

        // 请求参数
        $data = [
            'merchantId'        => $config['merchantId'],
            'merchantOrderNo'   => $order['order_no'],
            'amount'            => $order['real_money'] * 100,
            'currency'          => $config['currency'],
            'accountName'       => $order->wallet->name,
            'accountNo'         => $order->wallet->pix,
            'accountType'       => $order->wallet->chave_pix ?? 'PIX_CPF',
            'callback'          => $domain . $config['callback'],
        ];
        // dd($data);
        // 获取sign
        $data['sign'] = Sign::common($data, $config['secret'], 'secret');

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/x-www-form-urlencoded',
            ]
        ];

        // 发送POST请求
        $res = Http::post($apiUrl, http_build_query($data), $header);
        $res = json_decode($res, true);
        
        $code = 0;
        $msg = '';
        if($res['success']){
            $code = 1;
            $msg = $res['errorCode'];
        }

        $retval = [
            'code'  => $code,
            'msg'   => $msg,
        ];
        return $retval;
    }
    
    /**
     * u2cpay 查询通道
     */
    public static function u2cpayQuery($config, $order)
    {
        // 发起提现接口
        $apiUrl = $config['gate'] . '/api/open/merchant/payment/query';
        
        // 请求参数
        $data = [
            'merchantId'        => $config['merchantId'],
            'merchantOrderNo'   => $order['order_no'],
        ];
        
        // 获取sign
        $data['sign'] = Sign::common($data, $config['secret'], 'secret');

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/x-www-form-urlencoded',
            ]
        ];

        // 发送POST请求
        $res = Http::post($apiUrl, http_build_query($data), $header);
        $res = json_decode($res, true);
        return $res['data'];
    }

    /**
     * cepay 充值通道
     */
    public static function cepayRecharge($config, $order)
    {
        // 发起充值接口
        $apiUrl = $config['gate'] . $config['url'];

        $domain = config('channel.domain');
        
        // 请求参数
        $data = [
            'pay_memberid'          => $config['pay_memberid'],
            'pay_orderid'           => $order['order_no'],
            'pay_amount'            => $order['money'], 
            'pay_applydate'         => datetime(time()),
            'pay_callbackurl'       => $domain . $config['pay_callbackurl'],
            'pay_notifyurl'         => $domain . $config['pay_notifyurl'],
        ];
        
        // 获取sign
        $data['pay_md5sign']           = Sign::common($data, $config['secret'], 'key', 0);
        $data['pay_username']   = $config['pay_username'] ?? '无';
        $data['pay_useremail']  = $config['pay_useremail'] ?? '无';
        $data['pay_type']       = $config['pay_type'] ?? 'CPF';
        $data['pay_value']      = '无';

        // 发送POST请求
        $res = Http::post($apiUrl, $data);
        $res = json_decode($res, true);
        // dd($res);

        // 成功返回支付链接
        $payUrl = '';
        if($res['code'] == 1){
            $payUrl = $res['data']['pay_url'];
        }
        return $payUrl;
    }

    /**
     * cepay 提现通道
     */
     public static function cepayWithdraw($config, $order)
    {
        // 发起提现接口
        $apiUrl = $config['gate'] . $config['url'];

        $domain = config('channel.domain');

        $arr = [
            'PIX_CPF'       => 'cpf',
            'PIX_PHONE'     => 'phone',
            'PIX_CNPJ'      => 'cnpj',
            'PIX_EMAIL'     => 'email',
        ];

        $pix_type = $arr[$order->wallet->chave_pix] ?? 'cpf';

        $pix = $order->wallet->pix ?? '';
        
        // 请求参数
        $data = [
            'memberid'          => $config['memberid'],
            'out_trade_no'      => $order['order_no'],
            'amount'            => $order['real_money'], 
            'pix_type'          => $pix_type,
            'pix_key'           => $pix,
            'notifyurl'         => $domain . $config['notifyurl'],
        ];
        
        // 获取sign
        $data['pay_md5sign'] = Sign::common($data, $config['secret'], 'key', 0);

        // 发送POST请求
        $res = Http::post($apiUrl, $data);
        $res = json_decode($res, true);
        
        // 同是fast后台的
        return $res;
    }

    /**
     * cepay 查看代收
     */
    public static function cepayQuery($config, $order)
    {
        $apiUrl = $config['gate'] . '/api/pay/transactions/queryGive';

        // 请求参数
        $data = [
            'memberid'          => $config['memberid'],
            'out_trade_no'      => $order['order_no'],
        ];
        
        // 获取sign
        $data['pay_md5sign'] = Sign::common($data, $config['secret'], 'key', 0);

        // 发送POST请求
        $res = Http::post($apiUrl, $data);
        $res = json_decode($res, true);
        
        // 同是fast后台的
        return $res;
    }

    /**
     * ouropago 充值通道
     */
    public static function ouropagoRecharge($config, $order)
    {
        // 发起提现接口
        $apiUrl = $config['gate'] . $config['url'];
        
        $domain = config('channel.domain');

        $params = [
            'orderNo'       => $order['order_no'],
            'price'         => $order['money'] * 100,
            'callbackUrl'   => $domain . $config['callback'],
        ];

        $shard_str = sha1(json_encode($params));
        $authorization = md5($shard_str . $config['secret']);
        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'AppId: ' . $config['merchantId'],
                'Authorization:' . $authorization,
                'Content-Type: application/json',
            ]
        ];

        $res = Http::post($apiUrl, json_encode($params), $header);
        $res = json_decode($res, true);

        // 成功返回支付链接
        $payUrl = '';
        if($res['code'] == 0){
            $payUrl = $res['data']['paymentH5'];
        }
        return $payUrl;
    }

    /**
     * ouropago 提现通道
     */
    public static function ouropagoWithDraw($config, $order)
    {
        
        $apiUrl = $config['gate'] . $config['url'];

        $domain = config('channel.domain');
        
        $arr = [
            'PIX_CPF' => 'CPF',
            'PIX_PHONE' => 'PHONE',
            'PIX_EMAIL' => 'EMAIL',
        ];
        
        $pix_type = $arr[$order->wallet->chave_pix] ?? 'CPF';

        $pix = $order->wallet->pix ?? '';
        
        $params = [
            "orderNo"           => $order['order_no'],
            "price"             => $order['real_money'] * 100,
            'accountNo'         => $pix,
            'accountType'       => $pix_type,
            'creditorDocument'  => '', // CPF 证件号（如需校验则填写正确 CPF 号;不校验传空字符串）
            'description'       => '代付',
            "callbackUrl"       => $domain . $config['callback'],
        ];
        // dd($params);
        $shard_str = sha1(json_encode($params));
        $authorization = md5($shard_str. $config['secret']);
        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'AppId: ' . $config['merchantId'],
                'Authorization:' . $authorization,
                'Content-Type: application/json',
            ]
        ];

        $res = Http::post($apiUrl, json_encode($params), $header);
        $res = json_decode($res, true);
        
        $code = 0;
        $msg = '';
        if($res['code'] == '0'){
            $code = 1;
            $msg = $res['msg'];
        }

        $retval = [
            'code'  => $code,
            'msg'   => $msg,
        ];
        return $retval;
    }

    /**
     * ouropago 查看代收
     */
    public static function ouropagoQuery($config, $order)
    {
        $apiUrl = $config['gate'] . '/cashOut/query';

        $params = [
            'orderNo'       => $order['order_no'],
        ];

        $shard_str = sha1(json_encode($params));
        $authorization = md5($shard_str . $config['secret']);
        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'AppId: ' . $config['merchantId'],
                'Authorization:' . $authorization,
                'Content-Type: application/json',
            ]
        ];

        $res = Http::post($apiUrl, json_encode($params), $header);
        $res = json_decode($res, true);
        return $res['data'];
    }

    /**
     * funpay 充值通道
     */
    public static function funpayRecharge($config, $order)
    {
        // 发起提现接口
        $apiUrl = $config['gate'] . $config['url'];

        $pageUrl = db('user')->where('id', $order['user_id'])->value('origin');
        $pageUrl = 'https://' . $pageUrl;
        
        $domain = config('channel.domain');

        $rand = rand(100, 999);
        $params = [
            'merchant'      => $config['merchantId'],
            'businessCode'  => $config['businessCode'], // 业务代码网银, 当前选择哥伦比亚网银139
            'orderNo'       => $order['order_no'],
            'name'          => 'hms-cop',
            'phone'         => $order['user_id'] . $rand,
            'email'         => $order['user_id'] . $rand . '@hms-cop.com',
            'amount'        => $order['money'],
            'notifyUrl'     => $domain . $config['callback'],
            'pageUrl'       => $pageUrl,
            'bankCode'      => 'BANK',
            'subject'       => 'hms-cop Recharge',
        ];

        $params = [
            'merchant'      => '1009',
            'businessCode'  => '1001', // 业务代码网银, 当前选择哥伦比亚网银139
            'orderNo'       => 1722321107671,
            'name'          => 'test',
            'phone'         => '15998765432',
            'email'         => '15998765432@163.com',
            'amount'        => 200,
            'notifyUrl'     => 'https://api.funpay.tv/test',
            'pageUrl'       => 'https://api.funpay.tv/test',
            'bankCode'      => 'BANK',
            'subject'       => '测试测试',
        ];

        // 代收用秘钥
        $params = Sign::rsaencrypt($params, $config['secret']);
        // dd($params);
        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/json',
            ]
        ];

        // $res = Http::post($apiUrl, $params, $header);
        // $res = Http::post($apiUrl, json_encode($params), $header);
        $res = Http::post($apiUrl, http_build_query($params), $header);
        $res = json_decode($res, true);
        dd($res);
        // 成功返回支付链接
        $payUrl = '';
        if($res['code'] == 0){
            $payUrl = $res['data']['orderData'];
        }
        return $payUrl;
    }

    /**
     * funpay 提现通道
     */
    public static function funpayWithDraw($config, $order)
    {
        $apiUrl = $config['gate'] . $config['url'];

        $domain = config('channel.domain');
        
        $arr = [
            'PIX_CPF' => 'CPF',
            'PIX_PHONE' => 'PHONE',
            'PIX_EMAIL' => 'EMAIL',
        ];
        
        $pix_type = $arr[$order->wallet->chave_pix] ?? 'CPF';

        $pix = $order->wallet->pix ?? '';

        $rand = rand(100, 999);
        $params = [
            'merchant'      => $config['merchantId'],
            'businessCode'  => $config['businessCode'], // 业务代码网银, 当前选择哥伦比亚网银代付140
            'orderNo'       => $order['order_no'],
            'accName'       => 'hms-cop',
            'phone'         => $order['user_id'] . $rand,
            'accNo'         => $pix,
            'orderAmount'   => $order['real_money'],
            'bankCode'      => 'BANK',
            'notifyUrl'     => $domain . $config['callback'],
            'remake'       => 'hms-cop Withdraw',
        ];

        $params = Sign::rsaencrypt($params, $config['secret']);

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/json',
            ]
        ];

        $res = Http::post($apiUrl, $params, $header);
        $res = json_decode($res, true);
        
        $code = 0;
        $msg = $res['message'];
        if($res['code'] == '0'){
            $code = 1;
        }

        $retval = [
            'code'  => $code,
            'msg'   => $msg,
        ];
        return $retval;
    }

    /**
     * funpay 查看代收
     */
    public static function funpayQuery($config, $order)
    {
        $apiUrl = $config['gate'] . '/singleQuery';

         $params = [
            'merNo'            => $config['merchantId'],
            'merOrderNo'       => $order['order_no'],
        ];

        $params = Sign::rsaencrypt($params, $config['secret']);

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/json',
            ]
        ];

        $res = Http::post($apiUrl, $params, $header);
        $res = json_decode($res, true);
        return $res['data'];
    }

}
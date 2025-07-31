<?php

namespace app\common\service\util;

class Sign
{
    /**
     * 通用签名
     * @param $data 待签名数据
     * @param $key 签名key
     * @param $is_lower 是否小写
     * @return string
     */
    public static function common($data, $key, $key_field= 'key', $is_lower = 1)
    {
        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {
            $str .= $k . '=' . $v . '&';
        }
        $str .= $key_field . '=' . $key;
        
        $sign = md5($str);
        return $is_lower ? strtolower($sign) : strtoupper($sign);
    }

    /**
     * supe 签名
     * @param $data 待签名数据
     * @param $key 签名key
     * @param $is_lower 是否小写
     * @return string
     */
    public static function supeSign($data, $key)
    {
        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {
            if (is_bool($v)) {
                $str .= $k . '=' . ($v ? 'true' : 'false') . '&';
            } else {
                $str .= $k . '=' . rawurldecode((string)$v) . '&';
            }
        }
        $str .= 'key=' . $key;

        $str = str_replace('&', '&amp;', $str);
        
        $sign = md5($str);
        return strtoupper($sign);
    }

    /**
     * funpay 加密
     */
    public static function rsaencrypt($data, $mch_private_key)
    {
        ksort($data);
        $str = '';
        foreach($data as $k => $v){
            if(!empty($v)){
                $str .= (string) $k.'='. $v . '&';
            }
        }

        $str = rtrim($str, '&');
        $encrypted = '';

        //替换成自己的私钥
        $pem = chunk_split($mch_private_key, 64, "\n");
        $pem = "-----BEGIN PRIVATE KEY-----\n" . $pem . "-----END PRIVATE KEY-----\n";
        $private_key = openssl_pkey_get_private($pem);
        $crypto = '';
        foreach(str_split($str, 117) as $chunk){
            openssl_private_encrypt($chunk, $encryptData, $private_key);
            $crypto .= $encryptData;
        }
        $encrypted = base64_encode($crypto);
        $encrypted = str_replace(array('+','/','='), array('-','_',''), $encrypted);

        $data['sign'] = $encrypted;
        return $data;
    }

    /**
     * funpay 解密
     */
    public static function rsadecrypt($data, $mch_public_key)
    {
        ksort($data);
        $toSign ='';
        foreach($data as $key=>$value){
            if(strcmp($key, 'sign')!= 0  && $value!=''){
                $toSign .= $key . '=' . $value . '&';
            }
        }

        $str = rtrim($toSign, '&');

        // 替换自己的公钥
        $pem = chunk_split($mch_public_key, 64, "\n");
        $pem = "-----BEGIN PUBLIC KEY-----\n" . $pem . "-----END PUBLIC KEY-----\n";
        $publickey = openssl_pkey_get_public($pem);

        $base64 = str_replace(array('-', '_'), array('+', '/'), $data['sign']);

        $crypto = '';
        foreach(str_split(base64_decode($base64), 128) as $chunk) {
            openssl_public_decrypt($chunk, $decrypted, $publickey);
            $crypto .= $decrypted;
        }

        if($str != $crypto){
            return false;
        }
        return true;
    }

    /**
     * cp签名
     */
    public static function cpSign($data, $key)
    {
        ksort($data);
        $str = "";
        foreach($data as $k => $val){
            $str .= $k . "=" . $val . "&";
        }

        $str .= "secret=" . $key;
        return strtoupper(sha1(md5($str)));
    }

    /**
     * PP签名
     */
    public static function ppSign($data, $key)
    {
        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {
            $str .= $k . '=' . $v . '&';
        }

        $str = rtrim($str, '&');

        $sign = md5($str . $key);
        return $sign;
    }

    /**
     * omg签名
     */
    public static function omgSign($urlParams, $rawJsonBody, $key)
    {
        // 1. 拼接URL参数（示例要求格式：trace_id=value）
        $urlPart = '';
        if(!empty($urlParams)){
            ksort($urlParams); // 按字母排序
            foreach($urlParams as $k => $v){
                $urlPart .= $k . '=' . $v;
            }
        }
        
        // 2. 拼接：URL参数 + 原始JSON + 密钥
        $signString = $urlPart . $rawJsonBody . $key;
        
        // 4. 返回小写MD5
        return md5($signString);
    }

    /**
     * omg生成trace_id
     */
    public static function generateTraceId($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $traceId = '';
        $max = strlen($chars) - 1;
        
        for($i = 0; $i < $length; $i++){
            $traceId .= $chars[random_int(0, $max)]; // 使用cryptographically secure随机
        }
        
        return $traceId;
    }

    /**
     * jdb加密
     */
    public static function encrypt($data, $key, $iv)
    {
        $data = self::padString($data);
        $encrypted = openssl_encrypt($data, 'AES-128-CBC', $key, OPENSSL_NO_PADDING, $iv);
        $encrypted = base64_encode($encrypted);
        $encrypted = str_replace(array('+','/','=') , array('-','_','') , $encrypted);
        return $encrypted;
    }

    /**
     * jdb解密
     */
    public static function decrypt($data, $key, $iv)
    {
        $data = str_replace(array('-','_') , array('+','/') , $data);
        $data = base64_decode($data);
        $decrypted = openssl_decrypt($data, 'AES-128-CBC', $key, OPENSSL_NO_PADDING, $iv);
        return utf8_encode(trim($decrypted));
    }

    /**
     * jdb填充
     */
    private static function padString($source)
    {
        $paddingChar = ' ';
        $size = 16;
        $x = strlen($source) % $size;
        $padLength = $size - $x;
        for ($i = 0; $i < $padLength; $i++){
            $source .= $paddingChar;
        }
       
        return $source;
    }

}
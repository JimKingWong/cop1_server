<?php

namespace app\common\service\game;

use app\common\service\Base;
use app\common\service\util\Sign;
use fast\Http;

class Platform extends Base
{
    /**
     * 配置
     */
    protected $config;

    public function __construct($platform)
    {
        parent::__construct();


        $this->config = $platform->config;
        if(empty($this->config)){
            $this->error($platform->code . __( '游戏配置不存在'));
        }

        if($platform->status != 1){
           $this->error($platform->code . __('游戏未开启'));
        }

        foreach($this->config as $k => $v){
            if($v == ''){
                $this->error($platform->code . __('游戏配置不完整, 缺少%s配置', $k));
            }
        }
    }

    /**
     * 获取omg游戏链接
     */
    public function omgLink($game)
    {
        if(!$game){
            $this->error(__('请先选择游戏'));
        }

        // $language = $this->language;
        // if($language == 'spa'){
        //     $language = 'es'; // 西班牙语
        // }

        $language = 'es'; // 西班牙语

        // 游戏id
        $game_id = $game->game_id;
        
        $token = $this->auth->getToken();

        $trace_id = Sign::generateTraceId();
        
        $apiUrl = $this->config['gameUrl'] . "/api/usr/ingame?trace_id=" . $trace_id;
        $data = [
            "app_id"    => $this->config['app_id'],
            "gameid"    => $game_id,
            "token"     => $token,
            "nick"      => $this->auth->nickname,
            "lang"      => $language,
            "cid"       => $this->config['cid'],
        ];
        // dd($data);
        $urlParams = ['trace_id' => $trace_id];

        $jsonData = json_encode($data);

        $sign = Sign::omgSign($urlParams, $jsonData, $this->config['secret_key']);

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/json; charset=utf-8',
                'sign:' . $sign
            ]
        ];
        $res = Http::post($apiUrl, $jsonData, $header);
        // dd($res);
        $res = json_decode($res, true);
        // 5. 根据code值返回结果
        if($res['code'] != 0){
            $this->error(__('请求失败'));
        }

        $retval = [
            'game_url' => $res['data']['gameurl'] ?? '',
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 获取omg测试游戏链接
     */
    public function omgLinkTest($game)
    {
        if(!$game){
            $this->error(__('请先选择游戏'));
        }

        // 游戏id
        $game_id = $game->game_id;
        
        $token = $this->auth->getToken();

        $trace_id = Sign::generateTraceId();
        
        $apiUrl = $this->config['gameUrl'] . "/api/usr/ingame?trace_id=" . $trace_id;
        $data = [
            "app_id"    => $this->config['app_id'],
            "gameid"    => $game_id,
            "token"     => $token,
            "nick"      => $this->auth->nickname,
            "lang"      => 'pt',
            "cid"       => 8,
        ];
        // dd($data);
        $urlParams = ['trace_id' => $trace_id];

        $jsonData = json_encode($data);

        $sign = Sign::omgSign($urlParams, $jsonData, $this->config['secret_key']);

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/json; charset=utf-8',
                'sign:' . $sign
            ]
        ];
        $res = Http::post($apiUrl, $jsonData, $header);
        // dd($res);
        $res = json_decode($res, true);
        // 5. 根据code值返回结果
        if($res['code'] != 0){
            $this->error(__('请求失败'));
        }

        $retval = [
            'game_url' => $res['data']['gameurl'] ?? '',
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 获取pg游戏链接
     */
    public function pgLink($game)
    {
        if(!$game){
            $this->error(__('请先选择游戏'));
        }

        // 判断游戏id
        $game_id = $game->game_id;

        $user_token = $this->auth->getToken();

        // 请求地址
        $apiUrl = $this->config['gameUrl'];

        // 请求参数
        $data = [
            'form_params' => [
                'operator_token'    => $this->config['operator_token'],
                'path'              => "/". $game_id . "/index.html",
                'extra_args'        => 'l=pt&btt=1&ops=' . $user_token,
                'url_type'          => 'game-entry',
                'client_ip'         => GetUserIP(),
            ]
        ];

        // 异步请求
        $res = Http::post($apiUrl, $data);
        dd($res);
        header("Cache-Control: no-cache, no-store, must-revalidate, Content-Type: text/html");
        // echo $res->getBody();
    }

    /**
     * 获取cp游戏链接
     */
    public function cpLink($game)
    {
        if(!$game){
            $this->error(__('请先选择游戏'));
        }

        // 判断游戏id
        $game_id = $game->game_id;

        $user = $this->auth->getUser();
        
        $apiUrl = $this->config['gameUrl'];

        $data = [
            "appid"         => $this->config['appId'],
            "game_key"      => "hog",
            "sub_uid"       => $user['id'],
            "game_id"       => $game_id,
            "lang"          => "en",
            "time"          => time(),
        ];
        
        $data['token'] = Sign::cpSign($data, $this->config['gameKey']);

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/x-www-form-urlencoded',
            ]
        ];

        $res = Http::post($apiUrl, http_build_query($data), $header);
        $res = json_decode(htmlspecialchars_decode($res),true);
       
        $retval = [
            'game_url' => $res['data'],
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 获取pp游戏链接
     */
    public function ppLink($game)
    {
        if(!$game){
            $this->error(__('请先选择游戏'));
        }

        // 判断游戏id
        $game_id = $game->game_id;

        // 用户信息
        $user = $this->auth->getUser();

        // 用户token
        $token = $this->auth->getToken();

        // 请求接口
        $apiUrl = $this->config['gameUrl'];

        $lobbyUrl = "https://".$user['origin']."/";

        $data = [
            "secureLogin"       => $this->config['secureLogin'],
            "symbol"            => $game_id,
            "language"          => "en",
            "token"             => $token,
            "externalPlayerId"  => $user->id,
            'lobbyUrl'          => $lobbyUrl,
            'cashierUrl'        => $lobbyUrl . "#/recharge",
            'jurisdiction'      => '99',
        ];
        
        $data['hash'] = Sign::ppSign($data, $this->config['gameKey']);

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/x-www-form-urlencoded',
            ]
        ];
        $res = Http::post($apiUrl, http_build_query($data), $header);
        $res = json_decode($res, true);

        $retval = [
            'game_url' => $res['gameURL'],
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 获取tada游戏链接
     */
    public function tadaLink($game)
    {
        if(!$game){
            $this->error(__('请先选择游戏'));
        }

        // 判断游戏id
        $game_id = $game->game_id;

        $user = $this->auth->getUser();
        
        $randomstr_1 = "jkluio";
        $randomstr_2 = "poihy7";
        
        $dateStr = date('ymj');
        $agentId = $this->config['agentId'];
        $agentKey = $this->config['agentKey'];
        $keyG = md5($dateStr. $agentId. $agentKey);

        $lang = "pt-BR";
        $params= "Token=". $this->config['agentPre']. $user['id'] . "&GameId=" . $game_id . "&Lang=". $lang. "&AgentId=" . $agentId;
        
        $key = $randomstr_1 . md5($params . $keyG) . $randomstr_2; 
       
        $url = "https://wb-api-2.tadagaming.com/api1/singleWallet/LoginWithoutRedirect?" . $params . "&Key=" . $key;
       
        $res = file_get_contents($url);
        $res = json_decode($res, true);
       
        if($res['status'] !=  0){
            $this->error(__('获取失败'));
        }
        
        $retval = [
            'game_url' => $res['Data'],
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 获取jdb游戏链接
     */
    public function jdbLink($game)
    {
        if(!$game){
            $this->error(__('请先选择游戏'));
        }

        // 判断游戏id
        $game_id = $game->game_id;

        $user = $this->auth->getUser();
        
        $language = $this->language;
        
        $data = [
            'action'        => 21,
            'ts'            => time() * 1000,
            'uid'           => $this->config['parentName'] . $user->id,
            'parent'        => $this->config['parentName'],
            'balance'       => $user->money,
            'gType'         => $game->game_type,
            'mType'         => $game_id,
            'lang'          => $language
        ];

        $encryptData = Sign::encrypt(json_encode($data, true), $this->config['key'], $this->config['iv']);
       
        $api_url = $this->config['gameUrl'] . $this->config['dc'] . '&x=' . $encryptData;

        $res = Http::get($api_url);
        $res = json_decode($res, true);
        
        if($res['status'] != '0000'){
            $this->error(__('获取失败'));
        }

        $retval = [
            'game_url' => $res['path'],
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 获取pgnew游戏链接
     */
    public function pgnewLink($game)
    {
        if(!$game){
            $this->error(__('请先选择游戏'));
        }

        $url = "https://m.mmv1nd.com/" . $game->game_id . "/index.html";
        $url .= '?btt=1';
        $url .= '&ot=' . $this->config['operator_token'];
        $url .= '&l=pt';
        $url .= '&ops=' . $this->auth->getToken();
        $url .= '&f=https://m.mmv1nd.com&__refer=https://m.mmv1nd.com&or=https://m.mmv1nd.com&__hv=1fb275f1';

        $retval = [
            'game_url' => $url,
        ];
        $this->success(__('请求成功'), $retval);
    }

    /**
     * 获取pgnew3游戏链接
     */
    public function pgnew3Link($game)
    {
        if(!$game){
            $this->error(__('请先选择游戏'));
        }

        $user_id = $this->auth->id;
        $user = $this->GetSession($user_id);

        $apiUrl = $this->config['gameUrl'] . "/api/web/get_launch_url";
        $data = [
            "operator_token"        => $this->config['operator_token'],
            "user_id"               => $user['user_id'],
            "user_token"            => $user['token'],
            "game_code"             => $game->game_id,
            "language"              => 'pt',
            "ts"                    => time(),
            "currency"              => "BRL"
        ];

        $data['sign'] = Sign::common($data, $this->config['secret_key']);
        $jsonData = json_encode($data);

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ]
        ];
        $res = Http::post($apiUrl, $jsonData, $header);
        
        $res = json_decode($res, true);

        $retval = [
            'game_url' => $res['data']['url'],
        ];

        $this->success(__('请求成功'), $retval);
        
        header("Cache-Control: no-cache, no-store, must-revalidate");
        echo $res;
    }

    /**
     * 获取raspa游戏链接
     */
    public function raspaLink($game)
    {
        if(!$game){
            $this->error(__('请先选择游戏'));
        }

        $user_id = $this->auth->id;
        $user = $this->GetSession($user_id);

        $language = $this->language;
        $language = 'pt';

        $apiUrl = $this->config['gameUrl'] . "/api/web/game_url";
        $data = [
            "operator_token"        => $this->config['operator_token'],
            "user_id"               => $user['user_id'],
            "user_token"            => $user['token'],
            "game_code"             => $game->game_id,
            "language"              => $language,
            "ts"                    => time(),
            "currency"              => "BRL"
        ];

        $data['sign'] = Sign::common($data, $this->config['secret_key']);
        $jsonData = json_encode($data);

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ]
        ];
        $res = Http::post($apiUrl, $jsonData, $header);
        $res = json_decode($res, true);

        $retval = [
            'game_url' => $res['data']['url'],
        ];

        $this->success(__('请求成功'), $retval);
    }

    /**
     * 获取用户信息
     */
    public function GetSession($user_id)
    {
        $cacheKey = 'pgnew3_' . $user_id;
        $cachedData = cache($cacheKey);
        if($cachedData){
            return $cachedData;
        }

        $apiUrl = $this->config['gameUrl'] . "/api/web/user_session";
        $data = [
            "operator_token"    => $this->config['operator_token'],
            "user_id"           => $user_id,
            "user_name"         => $user_id,
            "ts"                => time(),
            "currency"          => "BRL"
        ];

        $data['sign'] = Sign::common($data, $this->config['secret_key']);
        $jsonData = json_encode($data);

        // 设置请求头
        $header = [
            CURLOPT_HTTPHEADER  => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ]
        ];
        $res = Http::post($apiUrl, $jsonData, $header);
        $res = json_decode($res, true);

        if($res['status'] == 0 && isset($res['data'])){  
            $data = $res['data'];  
            //缓存7天
            cache($cacheKey, $data, 3600 * 24 * 7);  
            return $data;
        } 
    }

    /**
     * 获取jdb游戏链接
     */
    public function cqLink($game)
    {
        if(!$game){
            $this->error(__('请先选择游戏'));
        }

        // 判断游戏id
        $game_id = $game->game_id;

        $user = $this->auth->getUser();

        //   "zh-cn",
        //   "en",
        //   "id",
        //   "ko",
        //   "pt-br",
        //   "th",
        //   "vn"
        
        // 游戏语言
        $lanArr = [
            'en'    => 'en',
            'pt'    => 'pt-br',
        ];

        $language = isset($lanArr[$this->language]) ? $lanArr[$this->language] : 'en';

        $header = [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: ' . $this->config['token']
            ]
        ];
        
        $data = [
            'account'       => $this->config['agent'] . '_' . $user->id,
            'gamehall'      => $this->config['gamehall'],
            'gamecode'      => $game_id,
            'gameplat'      => 'mobile',
            'lang'          => $language,
            // 'session'       => '',
            'app'           => 'N', // 选填, 是否是透过app 执行游戏，Y=是，N=否，预设为N
            'detect'        => 'N'  // 选填, 是否开启阻挡不合游戏规格浏览器提示， Y=是，N=否，预设为N
        ];
     
        $api_url = $this->config['gameUrl'] . '/gameboy/player/sw/gamelink';
        // dd($api_url);
        $res = Http::post($api_url, http_build_query($data), $header);
        $res = json_decode($res, true);
        // dd($res);
        
        if(!isset($res['status'])){
            $this->error(__('获取失败'));
        }

        if($res['status']['code'] != 0){
            $this->error($res['status']['message']);
        }

        $retval = [
            'game_url' => $res['data'],
        ];

        $this->success(__('请求成功'), $retval);
    }
}
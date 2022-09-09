<?php
/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 https://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  https://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */

/* 翻译器 */
namespace util;

class Translator
{
    public static $all_langs = array(
        'zh' => '中文',
        'en' => '英语',
        'fra' => '法语',
        'jp' => '日语',
        'kor' => '韩语',
        'de' => '德语',
        'ru' => '俄语',
        'spa' => '西班牙语',
        'pt' => '葡萄牙语',
        'it' => '意大利语',
        'ara' => '阿拉伯语',
        'th' => '泰语',
        'el' => '希腊语',
        'nl' => '荷兰语',
        'pl' => '波兰语',
        'per' => '波斯语',
        'bul' => '保加利亚语',
        'est' => '爱沙尼亚语',
        'dan' => '丹麦语',
        'fin' => '芬兰语',
        'cs' => '捷克语',
        'rom' => '罗马尼亚语',
        'slo' => '斯洛文尼亚语',
        'swe' => '瑞典语',
        'ukr' => '乌克兰语',
        'hu' => '匈牙利语',
        'tr' => '土耳其语',
        'hi' => '印地语',
        'id' => '印尼语',
        'may' => '马来语',
        'vie' => '越南语',
        'ben' => '孟加拉语',
        'bur' => '缅甸语',
        'hau' => '豪萨语',
        'yue' => '粤语',
        'wyw' => '文言文',
        'cht' => '繁体中文'
    );

    
    public static $api_langs = array(
        'baidu' => array(
            'zh' => 'zh',
            'en' => 'en',
            'fra' => 'fra',
            'jp' => 'jp',
            'kor' => 'kor',
            'de' => 'de',
            'ru' => 'ru',
            'spa' => 'spa',
            'pt' => 'pt',
            'it' => 'it',
            'ara' => 'ara',
            'th' => 'th',
            'el' => 'el',
            'nl' => 'nl',
            'pl' => 'pl',
            'per' => 'per',
            'bul' => 'bul',
            'est' => 'est',
            'dan' => 'dan',
            'fin' => 'fin',
            'cs' => 'cs',
            'rom' => 'rom',
            'slo' => 'slo',
            'swe' => 'swe',
            'ukr' => 'ukr',
            'hu' => 'hu',
            'tr' => 'tr',
            'hi' => 'hi',
            'id' => 'id',
            'may' => 'may',
            'vie' => 'vie',
            'ben' => 'ben',
            'bur' => 'bur',
            'hau' => 'hau',
            'yue' => 'yue',
            'wyw' => 'wyw',
            'cht' => 'cht'
        ), 
        'google' => array(
            'zh' => 'zh-CN',
            'en' => 'en',
            'fra' => 'fr',
            'jp' => 'ja',
            'kor' => 'ko',
            'de' => 'de',
            'ru' => 'ru',
            'spa' => 'es',
            'pt' => 'pt',
            'it' => 'it',
            'ara' => 'ar',
            'th' => 'th',
            'el' => 'el',
            'nl' => 'nl',
            'pl' => 'pl',
            'per' => 'fa',
            'bul' => 'bg',
            'est' => 'et',
            'dan' => 'da',
            'fin' => 'fi',
            'cs' => 'cs',
            'rom' => 'ro',
            'slo' => 'sl',
            'swe' => 'sv',
            'ukr' => 'uk',
            'hu' => 'hu',
            'tr' => 'tr',
            'hi' => 'hi',
            'id' => 'id',
            'may' => 'ms',
            'vie' => 'vi',
            'ben' => 'bn',
            'bur' => 'my',
            'hau' => 'ha',
            'cht' => 'zh-TW'
        ), 
        'youdao' => array(
            'zh' => 'zh-CHS',
            'en' => 'en',
            'jp' => 'ja',
            'kor' => 'ko',
            'fra' => 'fr',
            'spa' => 'es',
            'pt' => 'pt',
            'it' => 'it',
            'ru' => 'ru',
            'vie' => 'vi',
            'de' => 'de',
            'ara' => 'ar',
            'id' => 'id',
            'it' => 'it'
        ), 
        'qq' => array(
            'zh' => 'zh',
            'en' => 'en',
            'jp' => 'jp',
            'kor' => 'ko',
            'de' => 'de',
            'fra' => 'fr',
            'spa' => 'es',
            'it' => 'it',
            'tr' => 'tr',
            'ru' => 'ru',
            'pt' => 'pt',
            'vie' => 'vi',
            'hi' => 'hi',
            'id' => 'id',
            'may' => 'ms',
            'th' => 'th',
            'ara' => 'ar',
            'cht' => 'zh-TW'
        ) 
    );

    /* 翻译入口 */
    public static function translate($q, $from, $to, $returnState = false)
    {
        $transConf = g_sc_c('translate'); 
        init_array($transConf);
        if (empty($from) || empty($to)) {
            
            return $q;
        }
        $apiType = strtolower($transConf['api']);
        if (empty($apiType)) {
            
            return $q;
        }
        
        $from = self::$api_langs[$apiType][$from] ?: $from;
        $to = self::$api_langs[$apiType][$to] ?: $to;
        
        if (empty($from) || empty($to)) {
            return $q;
        }
        if ($from == $to) {
            return $q; 
        }
        
        $result = array();
        
        switch ($apiType) {
            case 'baidu':
                $result = self::api_baidu($q, $from, $to);
                break;
            case 'youdao':
                $result = self::api_youdao($q, $from, $to);
                break;
            case 'qq':
                $result = self::api_qq($q, $from, $to);
                break;
            case 'google':
                $result = self::api_google($q, $from, $to);
                break;
        }
        
        if ($returnState) {
            
            return $result;
        } else {
            
            return empty($result['success']) ? $q : $result['data'];
        }
    }

    /* 百度翻译接口 */
    public static function api_baidu($q, $from, $to)
    {
        $apiConf = g_sc_c('translate', 'baidu'); 
        init_array($apiConf);
        
        $salt = time();
        $sign = $apiConf['appid'] . $q . $salt . $apiConf['key'];
        $sign = md5($sign);
        $data = get_html('https://api.fanyi.baidu.com/api/trans/vip/translate', null, null, 'utf-8', array(
            'from' => $from,
            'to' => $to,
            'appid' => $apiConf['appid'],
            'salt' => $salt,
            'sign' => $sign,
            'q' => $q
        ));
        $data = json_decode($data?:'');
        
        $result = return_result('', false, array(
            'error' => '',
            'data' => null
        ));
        if ($data->error_code) {
            $result['error'] = 'error:' . $data->error_code . '-' . $data->error_msg;
        } else {
            $transData = '';
            foreach ($data->trans_result as $trans) {
                $transData .= $trans->dst . "\r\n";
            }
            if ($transData) {
                $result['success'] = true;
                $result['data'] = $transData;
            }
        }
        
        return $result;
    }

    /* 有道翻译接口 */
    public static function api_youdao($q, $from, $to)
    {
        $apiConf = g_sc_c('translate', 'youdao'); 
        init_array($apiConf);
        
        $salt = time();
        $sign = $apiConf['appkey'] . $q . $salt . $apiConf['key'];
        $sign = md5($sign);
        $data = get_html('https://openapi.youdao.com/api', null, null, 'utf-8', array(
            'from' => $from,
            'to' => $to,
            'appKey' => $apiConf['appkey'],
            'salt' => $salt,
            'sign' => $sign,
            'q' => $q
        ));
        $data = json_decode($data?:'');
        
        $result = return_result('', false, array(
            'error' => '',
            'data' => null
        ));
        if (! empty($data->errorCode)) {
            $result['error'] = 'error:' . $data->errorCode;
        } else {
            $transData = '';
            foreach ($data->translation as $trans) {
                $transData .= $trans . "\r\n";
            }
            if ($transData) {
                $result['success'] = true;
                $result['data'] = $transData;
            }
        }
        return $result;
    }

    /* 腾讯翻译接口 */
    public static function api_qq($q, $from, $to)
    {
        $apiConf = g_sc_c('translate', 'qq'); 
        init_array($apiConf);
        
        $SecretId = $apiConf['secretid'];
        $SecretKey = $apiConf['secretkey'];
        
        
        
        $param = array();
        $param["Nonce"] = rand();
        $param["Timestamp"] = time();
        $param["Region"] = "ap-shanghai";
        $param["SecretId"] = $SecretId;
        $param["Action"] = "TextTranslate";
        $param["Version"] = "2018-03-21";
        $param["SourceText"] = $q;
        $param["Source"] = $from;
        $param["Target"] = $to;
        $param['ProjectId'] = '0';
        
        
        ksort($param);
        
        
        $signStr = "GETtmt.ap-shanghai.tencentcloudapi.com/?";
        foreach ($param as $key => $value) {
            $signStr = $signStr . $key . "=" . $value . "&";
        }
        $signStr = substr($signStr, 0, - 1);
        
        
        $param['Signature'] = base64_encode(hash_hmac("sha1", $signStr, $SecretKey, true));
        
        $result = return_result('', false, array(
            'error' => '',
            'data' => null
        ));
        
        
        ksort($param);
        
        $url = '';
        foreach ($param as $key => $value) {
            $url = $url . $key . "=" . urlencode($value) . "&";
        }
        $url = trim($url, '&');
        
        $data = get_html('https://tmt.' . $param["Region"] . '.tencentcloudapi.com/?' . $url, null, null, 'utf-8');
        $data = json_decode($data?:'', true);
        $data = is_array($data['Response']) ? $data['Response'] : array();
        if (is_array($data['Error']) && ! empty($data['Error']['Message'])) {
            $result['error'] = $data['Error']['Message'];
        } elseif (! empty($data['TargetText'])) {
            $result['success'] = true;
            $result['data'] = $data['TargetText'];
        }
        return $result;
    }

    /* 谷歌翻译接口 */
    public static function api_google($q, $from, $to)
    {
        $apiConf = g_sc_c('translate', 'google'); 
        init_array($apiConf);
        
        $apiKey = $apiConf['key'];
        
        $post = array(
            'key' => $apiKey,
            'source' => $from,
            'target' => $to,
            'q' => $q,
            'format' => 'html'
        );
        $result = return_result('', false, array(
            'error' => '',
            'data' => null
        ));
        
        $data = get_html('https://translation.googleapis.com/language/translate/v2', null, array('return_curl_body'=>1), 'utf-8', $post);
        $data = json_decode($data?:'', true);
        
        if(is_array($data['error'])&&!empty($data['error'])){
            $result['error']=$data['error']['message'];
        }elseif(is_array($data['data'])&&is_array($data['data']['translations'])&&is_array($data['data']['translations'][0])&&$data['data']['translations'][0]['translatedText']){
            $result['success']=true;
            $result['data']=$data['data']['translations'][0]['translatedText'];
        }
        return $result;
    }

    
    public static function get_api_langs($api)
    {
        $apiLangs = self::$api_langs[$api];
        if (! empty($apiLangs) && is_array($apiLangs)) {
            foreach ($apiLangs as $k => $v) {
                if (empty(self::$all_langs[$k])) {
                    
                    unset($apiLangs[$k]);
                } else {
                    $apiLangs[$k] = self::$all_langs[$k];
                }
            }
        }
        return is_array($apiLangs) ? $apiLangs : null;
    }
}

?>
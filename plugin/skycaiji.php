<?php
namespace plugin;

/**
 * 方法整合，方便在插件中使用
 * 使用：\plugin\skycaiji::方法名(参数)
 */
class skycaiji{
    /**
     * 蓝天采集器的根目录
     * @param string $filename 附加的文件名称
     * @return string
     */
    public static function root_path($filename=''){
        $filename=isset($filename)?$filename:'';
        return config('root_path').DIRECTORY_SEPARATOR.$filename;
    }
    /**
     * 蓝天采集器根网址
     * @param string $url 附加的网址
     * @return string
     */
    public static function root_url($url=''){
        $url=isset($url)?$url:'';
        return config('root_website').'/'.$url;
    }
    /**
     * 数据文件目录
     * @param string $filename 附加的文件名称
     * @return string
     */
    public static function data_path($filename=''){
        $filename=isset($filename)?$filename:'';
        return self::root_path('data'.DIRECTORY_SEPARATOR.$filename);
    }
    /**
     * 应用程序目录
     * @param string $filename 附加的文件名称
     * @return string
     */
    public static function app_path($filename=''){
        $filename=isset($filename)?$filename:'';
        return config('apps_path').DIRECTORY_SEPARATOR.$filename;
    }
    /**
     * 插件文件目录
     * @param string $filename 附加的文件名称
     * @return string
     */
    public static function plugin_path($filename=''){
        $filename=isset($filename)?$filename:'';
        return config('plugin_path').DIRECTORY_SEPARATOR.$filename;
    }
    /**
     * 应用程序网址
     * @param string $url 附加的网址
     * @return string
     */
    public static function app_url($url=''){
        $url=isset($url)?$url:'';
        return self::root_url('app/'.$url);
    }
    /**
     * 请求网址
     * @param string $url 网址
     * @param mixed $post (bool)post模式 或者 (array)post数据
     * @param string $pageCharset 页面编码，不填可自动识别
     * @param array $headers 头信息
     * @return array 返回数组：(bool)success是否成功，(string)header头信息，(string)content页面内容
     */
    public static function curl($url,$post=null,$pageCharset=null,$headers=array()){
        $pageCharset=isset($pageCharset)?$pageCharset:'auto';//默认自动识别
        $data=get_html($url,$headers,array('timeout'=>60),$pageCharset,$post,true);
        $data=is_array($data)?$data:array();
        $data=array(
            'success'=>$data['ok']?true:false,
            'header'=>$data['header']?$data['header']:'',
            'content'=>$data['html']?$data['html']:''
        );
        return $data;
    }
    /**
     * 数据库对象
     * @return \think\db\Query
     */
    public static function db(){
        return db();
    }
}
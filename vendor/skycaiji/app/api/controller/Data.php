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

namespace skycaiji\api\controller;
use skycaiji\common\controller\BaseController;
class Data extends BaseController {
    public function indexAction(){
        $params=input('param.');
        $mda=model('Dataapi');
        $daData=array();
        if(is_numeric($params['i'])){
            $daData=$mda->getById($params['i']);
        }else{
            $daData=$mda->where('route',$params['i'])->find();
            $daData=$mda->get_da_data($daData);
        }
        if(empty($daData)){
            $this->jsonSend('数据接口不存在');
        }
        $config=$daData['config'];
        if(empty($config)){
            $this->jsonSend('无效的数据接口');
        }
        if(!empty($config['api_key'])){
            
            $iptK=$params['k']?:'';
            if(empty($config['api_mode'])){
                if(strcasecmp($iptK,md5($config['api_key']))!==0){
                    
                    $this->jsonSend('密钥错误');
                }
            }else{
                
                $iptK=explode('_', $iptK);
                if(strcasecmp($iptK[0],md5(md5($config['api_key']).$iptK[1]))!==0){
                    
                    $this->jsonSend('密钥错误');
                }
                if(abs(time()-$iptK[1])>300){
                    $this->jsonSend('密钥已过期');
                }
            }
        }
        if(!empty($config['api_method'])){
            
            if(strtolower($config['api_method'])=='post'){
                if(!$this->request->isPost()){
                    $this->jsonSend('请使用post模式请求接口');
                }
            }else{
                if(!$this->request->isGet()){
                    $this->jsonSend('请使用get模式请求接口');
                }
            }
        }
        if(empty($daData['status'])){
            $this->jsonSend('接口已关闭');
        }
        $dsData=$daData['ds_id']?model('Dataset')->getById($daData['ds_id']):array();
        if(empty($dsData)){
            $this->jsonSend('接口无数据');
        }
        if(empty($config['conds'])){
            $this->jsonSend('接口没有数据查询条件');
        }
        
        $pageNo=intval($params[$config['page_name']?:'p']);
        $pageNo=$pageNo<=1?1:$pageNo;
        
        $config['page_max']=intval($config['page_max']);
        if($config['page_max']>0&&$pageNo>$config['page_max']){
            $this->jsonSend('已超出最大分页数');
        }
        
        $error='';
        try{
            $queryData=$mda->do_query_conds($pageNo,$config,$dsData,$params);
        }catch (\Exception $ex){
            $error=$ex->getMessage();
        }
        if($error){
            $this->jsonSend($error);
        }
        if($queryData['count']<=0){
            $this->jsonSend('没有查询到数据');
        }
        $options=array();
        if($queryData['pages']>1){
            $options['page']=$pageNo;
            $options['pages']=$queryData['pages'];
        }
        $this->jsonSend('',$queryData['data'],1,$options);
    }
}

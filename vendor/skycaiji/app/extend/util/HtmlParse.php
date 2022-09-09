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

namespace util;

class HtmlParse{
    public static function getContent($html){
        try {
            $html=new \util\Readability($html,'utf-8');
            $html=$html->getContent();
            $html=$html['content'];
        }catch (\Exception $ex){
            $html='';
        }
        return $html?$html:'';
    }
    
	
	public static function getTitle($html){
	    
	    if(preg_match_all('/<h1\b[^<>]*?>(?P<content>[\s\S]+?)<\/h1>/i', $html,$title)){
	        if (count($title['content'])>1){
	            
	            $title=null;
	        }else{
	            $title=strip_tags(reset($title['content']));
	            if (preg_match('/^((\&nbsp\;)|\s)*$/i', $title)){
	                $title=null;
	            }
	        }
	    }else{
	        $title=null;
	    }
	    if (empty($title)){
	        $pattern = array (
	            '<(h[12])\b[^<>]*?(id|class)=[\'\"]{0,1}[^\'\"<>]*(title|article)[^<>]*>(?P<content>[\s\S]+?)<\/\1>',
	            '<title>(?P<content>[\s\S]+?)([\-\_\|][\s\S]+?)*<\/title>'
	        );
	        $title=self::returnPregMatch($pattern, $html);
	    }
	    return trim(strip_tags($title));
	}
	public static function getKeywords($html){
	    $patterns=array(
	        '<meta[^<>]*?name=[\'\"]keywords[\'\"][^<>]*?content=[\'\"](?P<content>[\s\S]*?)[\'\"]',
	        '<meta[^<>]*?content=[\'\"](?P<content>[\s\S]*?)[\'\"][^<>]*?name=[\'\"]keywords[\'\"]'
	    );
	    $data=self::returnPregMatch($patterns, $html);
	    return trim(strip_tags($data));
	}
	public static function getDescription($html){
	    $patterns=array(
	        '<meta[^<>]*?name=[\'\"]description[\'\"][^<>]*?content=[\'\"](?P<content>[\s\S]*?)[\'\"]',
	        '<meta[^<>]*?content=[\'\"](?P<content>[\s\S]*?)[\'\"][^<>]*?name=[\'\"]description[\'\"]'
	    );
	    $data=self::returnPregMatch($patterns, $html);
	    return trim(strip_tags($data));
	}
	
	
	public static function returnPregMatch($pattern,$content,$reg_key='content'){
	    if(is_array($pattern)){
	        
	        foreach ($pattern as $patt){
	            if(preg_match('/'.$patt.'/i', $content,$cont)){
	                $cont=$cont[$reg_key];
	                break;
	            }else{
	                $cont=false;
	            }
	        }
	    }else{
	        if(preg_match('/'.$pattern.'/i', $content,$cont)){
	            $cont=$cont[$reg_key];
	        }else{
	            $cont=false;
	        }
	    }
	    return empty($cont)?'':$cont;
	}
	
	private static function _getContent($html){
	    static $YUZHI_SPACE_HANG=2;
	    static $YUZHI_IMG_NUM=3;
	    static $YUZHI_A_PERCENT=0.2;
	    static $YUZHI_IMG_TXT_PERCENT=0.65;
	    static $YUZHI_END_PERCENT=0.9;
	    
	    $matchTags=array();
	    
	    $html=preg_replace('/<(script|style|textarea|iframe)[^<>]*>[\s\S]*?<\/\1>/i', "\r\n", $html);
	    $html=preg_replace('/[\s\r\n]*<(\/{0,1}(p|br|img)\b[^<>]*)>[\s\r\n]*/i',"<$1>",$html);
	    $html=preg_replace('/<div\b[^<>]*(id|class)=[\'\"]{0,1}[^\'\"<>]*(content|article|text)[^<>]*?>/i',"\r\n\r\n\r\n", $html);
	    
	    
	    $html=preg_replace_callback('/<(img|a|video|embed)\b[^<>]*?>/i',function($reMatch)use(&$matchTags){
	        $tag=strtolower($reMatch[1]);
	        $matchTags[$tag][]=$reMatch[0];
	        
	        end($matchTags[$tag]);
	        return '['.$tag.':'.key($matchTags[$tag]).']';
	    }, $html);
	    
	    $html=preg_replace('/<(p|br)\b[^<>]*>/i', "[$1:]", $html);
	    $html=preg_replace('/<\/(a|p|video|embed)>/i', "[:$1]", $html);
	    
	    $html=strip_tags($html)."\r\n\r\n\r\n\r\n\r\n";
	    
	    $data=array();
	    $totalHangNum=0;
	    /*匹配每行*/
	    if(preg_match_all('/(?P<space>^[\r\n]*)(?P<txt>[^\r\n]*)/m',$html,$list)){
	        $totalHangNum=count($list[0]);
	        $listSpaceHangNum=0;
	        $zhengwenStart=0;
	        $zhengwenZishu=0; 
	        $zhengwenImgNum=0; 
	        for($i=0;$i<count($list[0]);$i++){
	            $txt=trim($list['txt'][$i]);
	            $spaceNum=mb_strlen($list['space'][$i],'utf-8');
	            $spaceNum=floatval($spaceNum/2);
	            
	            /*旧的区块数据统计*/
	            if(($listSpaceHangNum+$spaceNum)>=$YUZHI_SPACE_HANG){
	                
	                
	                $endI=$i-1;
	                if($endI>=0){
	                    $data['start'][$endI]=$zhengwenStart;
	                    $data['zishu'][$endI]=$zhengwenZishu;
	                    $data['img_num'][$endI]=$zhengwenImgNum;
	                    if($zhengwenImgNum>$YUZHI_IMG_NUM){
	                        $data['img_sort'][$endI]=$zhengwenZishu;
	                    }
	                }
	                
	                
	                $listSpaceHangNum=0;
	                $zhengwenZishu=0;
	                $zhengwenStart=$i;
	                $zhengwenImgNum=0;
	            }
	            
	            /*开始新的区块数据计算*/
	            if(empty($txt)){
	                
	                $listSpaceHangNum++;
	            }else{
	                if(preg_match_all('/\[img\:\d+\]/i',$txt,$mImgList)){
	                    $imgNum=count($mImgList[0]);
	                }else{
	                    $imgNum=0;
	                }
	                if(preg_match_all('/\[a\:\d+\]/i',$txt,$mAList)){
	                    $aNum=count($mAList[0]);
	                }else{
	                    $aNum=0;
	                }
	                
	                if($imgNum>$aNum){
	                    $zhengwenImgNum+=$imgNum;
	                }
	                
	                
	                $txt=preg_replace('/(\[\:\w+\])|(\[\w+\:\d*\])/', '', $txt);
	                $zhengwenZishu+=mb_strlen($txt);
	            }
	        }
	    }
	    if($data){
	        arsort($data['zishu'],6);
	        if($data['img_sort']){
	            arsort($data['img_sort'],6);
	        }
	        
	        
	        $contents=array();
	        
	        foreach (array('zishu','img_sort') as $dkey){
	            if(is_array($data[$dkey])){
	                foreach($data[$dkey] as $zhengwenEnd=>$sortNum){
	                    if($zhengwenEnd/$totalHangNum>$YUZHI_END_PERCENT){
	                        
	                        continue;
	                    }
	                    
	                    $zhengwenStart=$data['start'][$zhengwenEnd];
	                    if($zhengwenEnd>=$zhengwenStart){
	                        
	                        $content='';
	                        for($i=$zhengwenStart;$i<=$zhengwenEnd;$i++){
	                            $content.=$list[0][$i];
	                        }
	                        
	                        
	                        if(preg_match_all('/\[a\:\d*\]([\s\S]*?)\[\:a\]/i',$content,$mAList)){
	                            $aZishu=mb_strlen(preg_replace('/\[\w+\:\d*\]/i', '',implode('', $mAList[1])));
	                        }else{
	                            $aZishu=0;
	                        }
	                        
	                        if($data['img_num'][$zhengwenEnd]>$YUZHI_IMG_NUM){
	                            $aPercent=$YUZHI_A_PERCENT+0.15;
	                        }else{
	                            $aPercent=$YUZHI_A_PERCENT;
	                        }
	                        
	                        if($aZishu/$data['zishu'][$zhengwenEnd]>$aPercent){
	                            
	                            continue;
	                        }
	                        $contents[$dkey]=array('zishu'=>$data['zishu'][$zhengwenEnd],'content'=>$content);
	                        break;
	                    }
	                }
	            }
	        }
	        
	        
	        if($contents['img_sort']&&$contents['img_sort']['zishu']>0){
	            
	            $content=($contents['img_sort']['zishu']/$contents['zishu']['zishu']>$YUZHI_IMG_TXT_PERCENT)?$contents['img_sort']['content']:$contents['zishu']['content'];
	        }else{
	            $content=$contents['zishu']['content'];
	        }
	    }
	    
	    
	    $content=$content?trim($content):'';
	    
	    if($content){
	        
	        $content=preg_replace('/\s*(\w+\:\/\/){0,1}([\w\-]+\.){2,}\w+\b([\/\w\.\?\#\%\&\=\_\-\+]*)\s*/i', ' ', $content);
	        
	        $content=preg_replace_callback('/\[(\w+)\:(\d+)\]/', function($reMatch)use($matchTags){
	            $tag=strtolower($reMatch[1]);
	            $id=intval($reMatch[2]);
	            if(is_array($matchTags[$tag])){
	                return $matchTags[$tag][$id];
	            }else{
	                return '';
	            }
	        }, $content);
	            
	            $content=preg_replace('/\[(\w+)\:\]/', "<$1>", $content);
	            $content=preg_replace('/\[\:(\w+)\]/', "</$1>", $content);
	            
	            
	            $content=preg_replace('/\s*<p>([\s\r\n]|(\&nbsp\;)|(<br[\s\/]*>))*?<\/p>\s*/i', ' ', $content);
	            $content=preg_replace('/(\s*<br\s*\/*>\s*)+/i', '<br>', $content);
	            
	            $content=preg_replace('/\s*<p>([\s\x{3000}]|(\&nbsp\;))*/ui', '<p>', $content);
	            
	            $regPages='/[\d\s]+(\x{4e0b}\x{4e00}\x{9875})\s*/u';
	            if(preg_match($regPages, $content)){
	                
	                $content=preg_replace($regPages, ' ', $content);
	            }
	    }
	    
	    
	    
	    $content=preg_replace('/\s*<p>([\s\r\n]|(\&nbsp\;)|(<br[\s\/]*>))*?<\/p>\s*/i', ' ', $content);
	    $content=preg_replace('/(\s*<br\s*\/*>\s*)+/i', '<br>', $content);
	    
	    $content=preg_replace('/\s*<p>([\s\x{3000}]|(\&nbsp\;))*/ui', '<p>', $content);
	    
	    return trim($content);
	}
	
}

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

namespace skycaiji\admin\event;

class Rfile extends Release {
	/**
	 * 设置页面post过来的config
	 * @param unknown $config
	 */
	public function setConfig($config){
	    $file=\util\UnmaxPost::val('file/a',array());
	    $file['path']=trim($file['path'],'\/\\');
	    $file['max_line']=intval($file['max_line']);
		$file['hide_fields']=is_array($file['hide_fields'])?$file['hide_fields']:array();
		if(empty($file['path'])){
			$this->error('请输入文件存放目录');
		}
		if(!preg_match('/^[a-zA-Z0-9\-\_]+$/i', $file['path'])){
			$this->error('目录只能由字母、数字、下划线组成');
		}
		if(empty($file['type'])){
			$this->error('请选择文件格式');
		}
		$config['file']=$file;
		return $config;
	}
	/*导出数据*/
	public function export($collFieldsList,$options=null){
	    $filetype=$this->config['file']['type'];
	    if(!in_array($filetype,array('xlsx','xls','txt'))){
	        return $this->echo_msg_return(array('不支持的文件格式：%s',$filetype));
		}
		$hideFields=$this->config['file']['hide_fields'];
		
		$filepath=config('root_path').'/data/'.$this->config['file']['path'].'/'.$this->release['task_id'];
		$filename=date('Y-m-d',time());
		$fileno=0;
		$filefull='';
		
		$maxLine=$this->config['file']['max_line'];
		$maxLine=intval($maxLine);
		if($maxLine>0){
		    
		    if(is_dir($filepath)){
		        $fileList=scandir($filepath);
		        if(!empty($fileList)){
    		        foreach( $fileList as $file ){
    		            if('.' != $file && '..' != $file ){
    		                $file=explode('.', $file);
    		                if($file[1]==$filetype){
    		                    
    		                    if($file[0]==$filename){
    		                        $fileno=0;
    		                    }elseif(strpos($file[0], $filename.'_')===0){
    		                        $filenoCur=str_replace($filename.'_', '', $file[0]);
    		                        $fileno=max($fileno,intval($filenoCur));
    		                    }
    		                }
    		            }
    		        }
		        }
		    }
		}
		
		$addedNum=0;
		$lineNum=0;
		$curNum=0;
		
		$excelType=array('xlsx'=>'Excel2007','xls'=>'Excel5');
		if(!empty($excelType[$filetype])){
			
		    $excelType=$excelType[$filetype];
			if(empty($excelType)){
			    return $this->echo_msg_return('错误的文件格式');
			}
			
			$firstFields=reset($collFieldsList);
			$phpExcel=null;
			foreach ($collFieldsList as $collFieldsKey=>$collFields){
			    if($curNum<=0){
			        do{
			            if($phpExcel){
			                
			                $phpExcel->disconnectWorksheets();
			                unset($phpExcel);
			            }
			            $isMaxLine=false;
			            $filefull=$this->_file_fullname($filepath, $filename, $fileno, $filetype);
			            if(!file_exists($filefull)){
			                
			                $this->_create_excel($filefull,$excelType,$firstFields);
			            }
			            if(file_exists($filefull)){
			                
			                $objReader = \PHPExcel_IOFactory::createReader($excelType);
			                $phpExcel = $objReader->load($filefull);
			                $phpExcel->setActiveSheetIndex(0); 
			                $lineNum=$phpExcel->getSheet(0)->getHighestRow();
			                $lineNum=intval($lineNum);
			                if($maxLine>0&&$lineNum>$maxLine){
			                    
			                    $isMaxLine=true;
			                }
			            }else{
			                $lineNum=0;
			            }
			            if($isMaxLine){
			                $fileno++;
			            }
			        }while($isMaxLine);
			        
			        $filefull=realpath($filefull);
			        if(empty($filefull)){
			            break;
			        }
			    }
			    
			    $addedNum++;
			    $curNum++;
			    $lineNum++;
				
			    
				$this->init_download_config($this->task,$collFields['fields']);
				$this->hide_coll_fields($hideFields, $collFields);
				
				$collFields['fields']=is_array($collFields['fields'])?array_values($collFields['fields']):array();
				foreach ($collFields['fields'] as $k=>$v){
				    $phpExcel->getActiveSheet()->setCellValue(chr(65+$k).$lineNum,$this->get_field_val($v));
				}
				$this->record_collected($collFields['url'], array('id'=>1,'target'=>$filefull,'desc'=>'行：'.$lineNum), $this->release,array('title'=>$collFields['title'],'content'=>$collFields['content']));
				
				unset($collFieldsList[$collFieldsKey]['fields']);
				
				
				if($maxLine>0&&$lineNum>$maxLine){
				    
				    $fileno++;
				    $curNum=0;
				    
				    $objWriter = \PHPExcel_IOFactory::createWriter($phpExcel,$excelType);
				    $objWriter->save($filefull);
				    
				    $phpExcel->disconnectWorksheets();
				    unset($phpExcel);
				}
			}
			if($phpExcel){
			    
			    $objWriter = \PHPExcel_IOFactory::createWriter($phpExcel,$excelType);
			    $objWriter->save($filefull);
			}
		}elseif('txt'==$filetype){
			
		    foreach ($collFieldsList as $collFieldsKey=>$collFields){
		        if($curNum<=0){
		            do{
		                $isMaxLine=false;
		                $filefull=$this->_file_fullname($filepath, $filename, $fileno, $filetype);
		                if(file_exists($filefull)){
		                    
		                    $lineNum=$this->_txt_line($filefull);
		                    if($maxLine>0&&$lineNum>=$maxLine){
		                        
		                        $isMaxLine=true;
		                    }
		                }else{
		                    
		                    write_dir_file( $filefull, '');
		                    $lineNum=0;
		                }
		                if($isMaxLine){
		                    $fileno++;
		                }
		            }while($isMaxLine);
		            
		            $filefull=realpath($filefull);
		            if(empty($filefull)){
		                break;
		            }
			    }
			    
			    $addedNum++;
			    $curNum++;
			    
			    
			    $this->init_download_config($this->task,$collFields['fields']);
			    $this->hide_coll_fields($hideFields, $collFields);
			    
				$fieldVals=array();
				foreach ($collFields['fields'] as $k=>$v){
					$fieldVal=str_replace(array("\r","\n"), array('\r','\n'), $this->get_field_val($v));
					if(empty($this->config['file']['txt_implode'])){
						
						$fieldVal=str_replace("\t", ' ', $fieldVal);
					}
					$fieldVals[]=$fieldVal;
				}
				$fieldVals=implode($this->config['file']['txt_implode']?$this->config['file']['txt_implode']:"\t", $fieldVals);
				if(write_dir_file($filefull,$fieldVals."\r\n",FILE_APPEND)){
					
				    $lineNum++;
				    $this->record_collected($collFields['url'], array('id'=>1,'target'=>$filefull,'desc'=>'行：'.$lineNum), $this->release,array('title'=>$collFields['title'],'content'=>$collFields['content']));
				}
				
				unset($collFieldsList[$collFieldsKey]['fields']);
				
				
				if($maxLine>0&&$lineNum>=$maxLine){
				    
				    $fileno++;
				    $curNum=0;
				}
			}
		}
		return $addedNum;
	}
	
	private function _file_fullname($filepath,$filename,$fileno,$filetype){
	    $fileno=intval($fileno);
	    $filefull=$filepath.'/'.$filename.($fileno>0?('_'.$fileno):'').'.'.$filetype;
	    return $filefull;
	}
	
	private function _create_excel($filefull,$excelType,$firstFields){
	    if(!file_exists($filefull)){
	        
	        write_dir_file( $filefull, null); 
	        $newPhpExcel=new \PHPExcel();
	        
	        $sheet1 = new \PHPExcel_Worksheet($newPhpExcel, 'Sheet1'); 
	        $newPhpExcel->addSheet($sheet1);
	        $newPhpExcel->setActiveSheetIndex(0); 
	        
	        $this->hide_coll_fields($this->config['file']['hide_fields'], $firstFields);
	        $firstFields=array_keys($firstFields['fields']);
	        foreach ($firstFields as $k=>$v){
	            $newPhpExcel->getActiveSheet()->setCellValue(chr(65+$k).'1',$v);
	        }
	        $newWriter = \PHPExcel_IOFactory::createWriter($newPhpExcel,$excelType);
	        $newWriter->save($filefull);
	        $newPhpExcel->disconnectWorksheets();
	        unset($newPhpExcel);
	        unset($newWriter);
	    }
	}
	
	private function _txt_line($filefull){
	    
	    $txtLine=0;
	    $fpTxt=fopen($filefull,'r');
	    while(!feof($fpTxt)) {
	        
	        if(($fpData=fread($fpTxt,1024*1024*2))!=false){
	            
	            $txtLine+=substr_count($fpData,"\r\n");
	        }
	    }
	    fclose($fpTxt);
	    return $txtLine;
	}
}
?>
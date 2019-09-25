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
		$file=input('file/a');
		$file['path']=trim($file['path'],'\/\\');
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
		if(!in_array($this->config['file']['type'],array('xlsx','xls','txt'))){
			$this->echo_msg('不支持的文件格式：'.$this->config['file']['type']);
		}
		$this->hide_coll_fields($this->config['file']['hide_fields'],$collFieldsList);
		
		$filepath=config('root_path').'/data/'.$this->config['file']['path'].'/'.$this->release['task_id'];
		$filename=date('Y-m-d',NOW_TIME).'.'.$this->config['file']['type'];
		$filename=$filepath.'/'.$filename;
		
		$excelType=array('xlsx'=>'Excel2007','xls'=>'Excel5');
		if(!empty($excelType[$this->config['file']['type']])){
			
			$excelType=$excelType[$this->config['file']['type']];
			if(empty($excelType)){
				$this->echo_msg('错误的文件格式');
				exit();
			}
			
			if(!file_exists($filename)){
				
				write_dir_file( $filename, null); 
				$newPhpExcel=new \PHPExcel();
				
				$sheet1 = new \PHPExcel_Worksheet($newPhpExcel, 'Sheet1'); 
				$newPhpExcel->addSheet($sheet1);
				$newPhpExcel->setActiveSheetIndex(0); 
				
				$firstFields=reset($collFieldsList);
				$firstFields=array_keys($firstFields['fields']);
				foreach ($firstFields as $k=>$v){
					$newPhpExcel->getActiveSheet()->setCellValue(chr(65+$k).'1',$v);
				}
				$newWriter = \PHPExcel_IOFactory::createWriter($newPhpExcel,$excelType);
				$newWriter->save($filename);
				unset($newWriter);
				unset($newPhpExcel);
			}
			$filename=realpath($filename);
			$objReader = \PHPExcel_IOFactory::createReader($excelType);
			$phpExcel = $objReader->load($filename);
			$phpExcel->setActiveSheetIndex(0); 
			$rowNum=$phpExcel->getSheet(0)->getHighestRow();
			$rowNum=intval($rowNum);
			
			$addedNum=0;
			foreach ($collFieldsList as $collFieldsKey=>$collFields){
				
				$addedNum++;
				$curRow=$rowNum+$addedNum;
				$collFields['fields']=is_array($collFields['fields'])?array_values($collFields['fields']):array();
				foreach ($collFields['fields'] as $k=>$v){
					$phpExcel->getActiveSheet()->setCellValue(chr(65+$k).$curRow,$this->get_field_val($v));
				}
				$this->record_collected($collFields['url'], array('id'=>1,'target'=>$filename,'desc'=>'行：'.$curRow), $this->release,$collFields['title']);
				
				unset($collFieldsList[$collFieldsKey]['fields']);
			}
			$objWriter = \PHPExcel_IOFactory::createWriter($phpExcel,$excelType);
			$objWriter->save($filename);
		}elseif('txt'==$this->config['file']['type']){
			
			$txtLine=0;
			if(file_exists($filename)){
				
				$fpTxt=fopen($filename,'r');
				while(!feof($fpTxt)) {
					
					if(($fpData=fread($fpTxt,1024*1024*2))!=false){
						
						$txtLine+=substr_count($fpData,"\r\n");
					}
				}
				fclose($fpTxt);
			}else{
				
				write_dir_file( $filename, null);
			}
			
			
			foreach ($collFieldsList as $collFieldsKey=>$collFields){
				
				$addedNum++;
				$fieldVals=array();
				foreach ($collFields['fields'] as $k=>$v){
					$fieldVal=str_replace(array("\r","\n"), array('\r','\n'), $this->get_field_val($v));
					if(empty($this->config['file']['txt_implode'])){
						
						$fieldVal=str_replace("\t", ' ', $fieldVal);
					}
					$fieldVals[]=$fieldVal;
				}
				$fieldVals=implode($this->config['file']['txt_implode']?$this->config['file']['txt_implode']:"\t", $fieldVals);
				if(write_dir_file($filename,$fieldVals."\r\n",FILE_APPEND)){
					
					$txtLine++;
					$this->record_collected($collFields['url'], array('id'=>1,'target'=>$filename,'desc'=>'行：'.$txtLine), $this->release,$collFields['title']);
				}
				
				unset($collFieldsList[$collFieldsKey]['fields']);
			}
		}
		
		return $addedNum;
	}
}
?>
/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 http://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  http://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */
'use strict';function CollectedClass(){}
CollectedClass.prototype={constructor:CollectedClass,init_list:function(){$('#list_table .delete').bind('click',function(){var obj=$(this);confirmRight(window.tpl_lang.confirm_delete,function(){$.ajax({type:"GET",url:obj.attr('url'),dataType:"json",success:function(data){data.code==1?toastr.success(data.msg):toastr.error(data.msg);if(data.code==1){obj.parents('tr').eq(0).remove()}}})})});$('#deleteall').bind('click',function(){var obj=$(this);confirmRight(window.tpl_lang.confirm_delete,function(){$.ajax({type:"POST",url:ulink('Collected/op?op=deleteall'),dataType:"json",data:$('#form_list').serialize(),success:function(data){data.code==1?toastr.success(data.msg):toastr.error(data.msg);setTimeout("window.location.reload();",2500)}})})});$('#btn_clear_error').bind('click',function(){windowModal('清理失败的数据',ulink('Collected/clearError'))})}}
var collectedClass=new CollectedClass()
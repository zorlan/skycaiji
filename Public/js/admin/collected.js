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
CollectedClass.prototype={constructor:CollectedClass,init_list:function(){$('#list_table .delete').bind('click',function(){var obj=$(this);confirmRight(window.tpl_lang.confirm_delete,function(){$.ajax({type:"GET",url:obj.attr('url'),dataType:"json",success:function(data){data.status==1?toastr.success(data.info):toastr.error(data.info);if(data.status==1){obj.parents('tr').eq(0).remove()}}})})});$('#deleteall').bind('click',function(){var obj=$(this);confirmRight(window.tpl_lang.confirm_delete,function(){$.ajax({type:"POST",url:ulink('Collected/op?op=deleteall'),dataType:"json",data:$('#form_list').serialize(),success:function(data){data.status==1?toastr.success(data.info):toastr.error(data.info);setTimeout("window.location.reload();",2500)}})})})}}
var collectedClass=new CollectedClass()
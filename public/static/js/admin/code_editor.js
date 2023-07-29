/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 https://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  https://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */
'use strict';function CodeEditorClass(){}
CodeEditorClass.prototype={constructor:CodeEditorClass,init_deve:function(config){var $_o=this;config=config?config:{};$('#btn_editor_save').bind('click',function(){ajax_check_userpwd({type:'POST',dataType:'json',url:ulink('develop/editor_save'),data:{type:config.type,module:config.module,app:config.app,appcode:$_o.editor_get_value()},beforeSend:function(){$('#btn_editor_save').attr('disabled',!0)},success:function(data){if(data){data.url=''}
ajaxDataMsg(data)},complete:function(){$('#btn_editor_save').removeAttr('disabled')}})});var editorHeight=$(document.body).height()-$('#deve_editor_main').offset().top;editorHeight=parseInt(editorHeight)-60;$('#code_editor_box').height(editorHeight);$_o.editor_iframe($('#code_editor_txt').val());var appsScroll=!1;var deveAppsNav=$('#deve_editor_main .deve-editor-apps-nav');if(deveAppsNav.height()>editorHeight){appsScroll=!0;deveAppsNav.css('overflow-y','scroll')}
deveAppsNav.css('height',editorHeight+'px');if(appsScroll){var curApp=$('.deve-editor-apps .cur');if(curApp.length>0){var curAppTop=curApp.offset().top-deveAppsNav.offset().top;if(curAppTop>editorHeight){deveAppsNav.scrollTop(curAppTop-(editorHeight/2)-20)}}}
$('#plugin_skycaiji_ul').on('click','a[data-scj-method]',function(){var method=$(this).attr('data-scj-method');windowModal('方法：\\plugin\\skycaiji::'+method+'()',ulink('develop/plugin_skycaiji?op=method&method=_method_',{'_method_':method}),{lg:1,'full_height':1})});ajaxOpen({type:'GET',dataType:'json',url:ulink('develop/plugin_skycaiji'),async:!0,success:function(data){data=data.data;var html='';for(var i in data){html+='<li><a href="javascript:;" data-scj-method="'+i+'"><b>\\plugin\\skycaiji::'+i+'()</b><br>'+data[i]+'</a></li>'}
$('#plugin_skycaiji_ul').html(html)}})},editor_iframe:function(appcode){var $_o=this;$('#code_editor_ifr').attr('src',ulink('develop/editor_code'));$('#code_editor_ifr').off('load').bind('load',function(){$_o.editor_set_value(appcode);$('#code_editor_ifr')[0].contentWindow.editor_code_op.ctrl_s(function(){$('#btn_editor_save').trigger('click')})})},editor_get_value:function(){return $('#code_editor_ifr')[0].contentWindow.editor_code_op.get()},editor_set_value:function(val){$('#code_editor_ifr')[0].contentWindow.editor_code_op.set(val)}}
var codeEditorClass=new CodeEditorClass()
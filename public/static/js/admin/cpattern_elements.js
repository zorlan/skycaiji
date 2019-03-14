/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 http://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  http://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */
'use strict';function SkycaijiCpatternElement(){}
SkycaijiCpatternElement.prototype={constructor:SkycaijiCpatternElement,init:function(){var $_o=this;$('*').css('cursor','pointer');$('*').bind('click',function(){var tagName=$(this).prop('tagName').toLowerCase();if(tagName=='body'||tagName=='html'){return!1}
var xpaths=$_o.get_xpaths(this);var tpl='<div id="skycaiji_console">'+'<em class="skycaiji-close" style="" onclick="window.skycaijiCE.close()">×</em>'+'<table>'+'<tr><td width="130">当前元素的xpath：</td><td><textarea>'+xpaths.xpath+'</textarea></td><td width="50"><em onclick="window.skycaijiCE.show_xpath_ele(this,$(this).parents(\'tr\').find(\'textarea\').val())">显示</em></td></tr>'+(xpaths.listXpath?('<tr><td>同类型元素xpath：</td><td><textarea>'+xpaths.listXpath+'</textarea></td><td><em onclick="window.skycaijiCE.show_xpath_ele(this,$(this).parents(\'tr\').find(\'textarea\').val())">显示</em></td></tr>'):'')+'</table>'+'</div>';$('#skycaiji_console').remove();$('body').append(tpl);return!1});$('*').bind('mouseenter',function(){$(this).css({'background-color':'#C8ECE6'})});$('*').bind('mouseout',function(){$(this).css({'background-color':''})})},get_xpaths:function(element){var $_o=this;var listXpath='';var maxEleNum=1;var xpath=$_o.ele_xpath(element);xpath=xpath.split('/');for(var i=(xpath.length-1);i>=0;i--){if(!xpath[i]){continue}
var parentXpath=xpath.slice(0,i+1);parentXpath[i]=parentXpath[i].replace(/\[\d+\]/,'');parentXpath=parentXpath.join('/');var subXpath=xpath.slice(i+1);subXpath=subXpath.join('/');var parentCsspath=$_o.xpath2csspath(parentXpath);var subCsspath=$_o.xpath2csspath(subXpath);var eleNum=0;if(subCsspath){var curIndex=-1;$(parentCsspath).each(function(){curIndex++;var curCsspath=parentCsspath+':eq('+curIndex+')>'+subCsspath;eleNum+=parseInt($(curCsspath).length)})}else{eleNum+=parseInt($(parentCsspath).length)}
if(eleNum>maxEleNum){maxEleNum=eleNum;listXpath=parentXpath+(subXpath?('/'+subXpath):'')}}
return{'xpath':xpath.join('/'),'listXpath':listXpath}},ele_xpath:function(ele){if($(ele).prop('id')){return'//*[@id="'+$(ele).prop('id')+'"]'}
var tagName=$(ele).prop('tagName').toLowerCase();if(tagName=='body'){return'/html/body'}
if(!tagName){return''}
var nodes=$(ele).parent().children(tagName);var index=$(nodes).index(ele);index=parseInt(index)+1;return this.ele_xpath($(ele).parent())+'/'+tagName+'['+index+']'},xpath2csspath:function(xpath){if(!xpath){return''}
xpath=xpath.replace(/\/\//g,' ');xpath=xpath.replace(/\//g,'>');xpath=xpath.replace(/\[([^@].*?)\]/ig,function(match,index){index=parseInt(index)-1;return':eq('+index+')'});xpath=xpath.replace(/\@/g,'');xpath=xpath.replace(/^\s+|\s+$/gm,'');return xpath},show_xpath_ele:function(obj,xpath){if(!xpath){return}
var $_o=this;xpath=xpath.split('/');var reg=/^((?!(html|body))\w)+$/;var ix=-1;for(var i=(xpath.length-1);i>=0;i--){if(reg.test(xpath[i])){ix=i;break}}
var isShow=$(obj).text()=='显示'?true:!1;var bgColor=(isShow?'#C8ECE6':'');if(ix>-1){var parentXpath=xpath.slice(0,ix+1);parentXpath=parentXpath.join('/');var subXpath=xpath.slice(ix+1);subXpath=subXpath.join('/');var parentCsspath=$_o.xpath2csspath(parentXpath);var subCsspath=$_o.xpath2csspath(subXpath);if(subCsspath){var curIndex=-1;$(parentCsspath).each(function(){curIndex++;var curCsspath=parentCsspath+':eq('+curIndex+')>'+subCsspath;$(curCsspath).css({'background-color':bgColor})})}else{$(parentCsspath).css({'background-color':bgColor})}}else{var csspath=$_o.xpath2csspath(xpath.join('/'));$(csspath).css({'background-color':bgColor})}
$(obj).text(isShow?'取消':'显示')},close:function(){$('#skycaiji_console').remove();$('*').css({'background-color':''})}}
var skycaijiCE=new SkycaijiCpatternElement()
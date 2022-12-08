/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 https://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  https://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */
'use strict';$(document).ready(function(){toastr.options={"closeButton":!1,"debug":!1,"newestOnTop":!1,"progressBar":!1,"positionClass":"toast-top-center","preventDuplicates":!1,"onclick":null,"showDuration":"300","hideDuration":"1000","timeOut":"3000","extendedTimeOut":"1000","showEasing":"swing","hideEasing":"linear","showMethod":"fadeIn","hideMethod":"fadeOut"};$('body').on('submit','form[ajax-submit="true"]',function(){var settings=getFormAjaxSettings($(this));ajaxOpen(settings);return!1})});function getFormAjaxSettings(formObj){var settings={type:'POST',dataType:'json',url:formObj.attr('action'),beforeSend:function(){formObj.find('button[type="submit"]').attr('disabled',!0)},success:function(data){if(data.url){window.setTimeout("window.location.href='"+data.url+"';",2000)}else{formObj.find('button[type="submit"]').removeAttr('disabled')}
if(data.code==1){if(data.msg){toastr.success(data.msg)}
formObj.find('.verify-img').trigger('click')}else{if(data.msg){toastr.error(data.msg)}}
var dataData=isNull(data.data)?{}:data.data;if(dataData.js){eval(dataData.js)}},error:function(data){formObj.find('button[type="submit"]').removeAttr('disabled');toastr.error(data)}};if(formObj.attr('enctype')&&formObj.attr('enctype').toLowerCase()=='multipart/form-data'){var formData=new FormData(formObj[0]);settings.data=formData;settings.contentType=!1;settings.processData=!1}else{settings.data=formObj.serialize()}
return settings}
function htmlspecialchars(str){str=str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');return str}
function setCookie(c_name,value,expiredays){var exdate=new Date();exdate.setDate(exdate.getDate()+expiredays);document.cookie=c_name+"="+escape(value)+((expiredays==null)?"":";expires="+exdate.toGMTString())+';path='+(window.site_config?(window.site_config.root?window.site_config.root:'/'):'/')}
function getCookie(c_name){if(document.cookie.length>0){var c_start=document.cookie.indexOf(c_name+"=");if(c_start!=-1){c_start=c_start+c_name.length+1;var c_end=document.cookie.indexOf(";",c_start);if(c_end==-1)c_end=document.cookie.length;return unescape(document.cookie.substring(c_start,c_end))}}
return""}
function isNull(str){var space=/^[\s\r\n]*$/;if(space.test(str)||str==null||str==''||!str){return!0}else{return!1}}
function isObject(data){if(data&&typeof(data)=='object'){return!0}else{return!1}}
function toInt(val){val=val?val:0;val=parseInt(val);if(isNaN(val)){val=0}
return val}
function dataIsJson(data){if((/^\{[\s\S]*\}$/).test(data)||(/^\[[\s\S]*\]$/).test(data)){return!0}else{return!1}}
function dataIsHtml(data){if((/<\w+[^<>]*>/).test(data)){return!0}else{return!1}}
function ajaxOpen(settings){if(settings.type&&'post'==settings.type.toLowerCase()){if(window.site_config){var regToken=new RegExp("_usertoken_\\s*=",'i');if(!regToken.test(settings.url)){var usertoken=window.site_config.usertoken;var data=settings.data;if(isNull(data)){data={'_usertoken_':usertoken}}else{if(typeof(data)=='object'){data._usertoken_=usertoken}else{if(!regToken.test(data)){data+='&_usertoken_='+encodeURIComponent(usertoken)}}}
settings.data=data}}}
return $.ajax(settings)}
function modal(title,body,options){if(!options){options={}}
if(document.getElementById('myModal')){$('#myModal').off();$('#myModal').modal('hide');$('#myModal').remove()}
if(!document.getElementById('myModal')){var modal='<div class="modal '+(options.lg?' bs-example-modal-lg':'')+' myModal" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true"><div class="modal-dialog'+(options.lg?' modal-lg':'')+'"><div class="modal-content">'+'<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-hidden="true" style="font-size:24px;">&times;</button><h4 class="modal-title" id="myModalLabel"></h4></div><div class="modal-body" '+(options.bodyStyle?options.bodyStyle:'')+'></div>'+'<div class="modal-footer"><button type="button" class="close" data-dismiss="modal" aria-hidden="true">'+tpl_lang.close+'</button></div></div></div></div>';$('body').append(modal)}
$('#myModal .modal-title').html(title);$('#myModal .modal-body').html(body);if(options.backdrop_static){$('#myModal').modal({backdrop:'static'})}
$('#myModal').modal('show');if(options.full_height){$('#myModal .modal-body').css('padding','0');var height=toInt($(window).height());height=height-toInt($('#myModal .modal-body').offset().top-$(document).scrollTop())*2;$('#myModal .modal-body').css('height',height)}
$('#myModal').on('hidden.bs.modal',function(e){execVarFuncs(options.hidden_func)});if(options.scroll_top){$('#myModal').scrollTop(options.scroll_top)}}
function windowModal(title,url,options){if(!options){options={}}
modal(title,'<div class="loading"></div>',options);if(options.ajax_scroll_top){options.scroll_top=options.ajax_scroll_top}
var ajaxSet={type:'get',url:url,success:function(data){if(dataIsJson(data)){$('#myModal').modal('hide');ajaxDataMsg(data)}else{modal(title,data,options)}},dataType:'html'};if(options.ajax){ajaxSet=$.extend(ajaxSet,options.ajax)}
var win_ajax_request=ajaxOpen(ajaxSet);$('#myModal').on('hidden.bs.modal',function(e){win_ajax_request.abort()})}
function windowIframe(title,url,options){if(!options){options={}}
options.full_height=1;modal(title,'<div class="loading" style="margin:10px;"></div>',options);var ifrHtml='<iframe id="myModalIframe" '+(url?(' src="'+url+'"'):'')+' width="100%" height="100%" frameborder="0" scrolling="yes"></iframe>';$('#myModal iframe').remove();$('#myModal .modal-body').html(ifrHtml);$('#myModal iframe').bind('load',function(){$('#myModal').attr('data-iframe-loaded',1);execVarFuncs(options.ifr_loaded_func)});$('#myModal').on('hidden.bs.modal',function(e){$('#myModal iframe').remove();execVarFuncs(options.close_func)});execVarFuncs(options.loaded_func)}
function execVarFuncs(funcs){if(!isNull(funcs)){if(typeof(funcs)=='function'){funcs()}else if(typeof(funcs)=='object'){for(var i in funcs){var func=funcs[i];if(typeof(func)=='function'){func()}}}}}
function ajaxDataMsg(data){if(typeof data=='string'){data=eval('('+data+')')}
if(data.code==1){toastr.success(data.msg)}else{toastr.error(data.msg)}
if(data.url){window.setTimeout("window.location.href='"+data.url+"';",2000)}}
function checkall(obj,chkName){var status=$(obj).is(":checked")?true:!1;$("input[name='"+chkName+"']:checkbox").prop('checked',status)}
function url_base64encode(str){str=Base64.encode(str);str=str.replace(/\+/g,'-').replace(/\//g,'_').replace(/\=/g,'');return str}
function url_base64decode(str){str=str.replace(/\-/g,'+').replace(/\_/g,'/');var mod4=str.length%4;if(mod4){str+=('====').substr(mod4)}
str=Base64.decode(str);return str}
function encode_json2urlbase(data){try{data=url_base64encode(JSON.stringify(data))}catch(e){data=''}
return data}
function decode_urlbase2json(urlBase64Str){var json={};try{json=url_base64decode(urlBase64Str);json=JSON.parse(json)}catch(e){json={}}
return json}
function generateUUID(){var d=new Date().getTime();var uuid='xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,function(c){var r=(d+Math.random()*16)%16|0;d=Math.floor(d/16);return(c=='x'?r:(r&0x7|0x8)).toString(16)});return uuid}
function refreshVerify(obj){var src=$(obj).attr('src');if(src.indexOf('version')>0){src=src.replace(/([\?\&]version\=)[\.\d]+/i,"$1"+Math.random())}else{src+=(src.indexOf('?')>-1?'&':'?')+'version='+Math.random()}
$(obj).attr('src',src)}
function verifyImgError(){ajaxOpen({type:'get',dataType:'json',url:ulink('admin/index/verify_img_error'),success:function(data){if(data.msg){ajaxDataMsg(data)}}})}
function ulink(url,vals){url=url?url:'';url=url.replace(/^\s*\//,'');if(url.indexOf('/')>-1){var path=url.split('/');if(path.length==2){url='admin/'+path[0]+'/'+path[1]}else if(path.length==3){url=path[0]+'/'+path[1]+'/'+path[2]}}
var newurl=window.site_config.root+'/';var curUrl=window.location.href.toLowerCase();if(curUrl.indexOf('/index.php?s=')>-1){newurl+='index.php?s=/';url=url.replace('?','&')}else if(curUrl.indexOf('/index.php')>-1){newurl+='index.php/'}
newurl+=url;if(vals&&typeof(vals)=='object'){for(var i in vals){newurl=newurl.replace(i,encodeURIComponent(vals[i]))}}
return newurl}
function confirmRight(params,func1,func2){var strMsg='';var strYes='确定';var strNo='取消';var close=!1;var closeAfterFunc=!1;var width=300;var textAlign='center';if((typeof params)=='object'){strMsg=params.msg;strYes=params.yes;strNo=params.no;close=params.close?true:!1;closeAfterFunc=params.closeAfterFunc?true:!1;if(params.width){width=parseInt(params.width)}
if(params.textAlign){textAlign=params.textAlign}}else{strMsg=params}
var yesHtml='';var noHtml='';var closeHtml='';if(strYes){yesHtml='<button type="button" class="btn btn-info cr-btn-yes" style="border:0;border-radius:0;width:'+(strNo?'50%':'100%')+';">'+strYes+'</button>'}
if(strNo){noHtml='<button type="button" class="btn btn-warning cr-btn-no" style="border:0;border-radius:0;width:'+(strYes?'50%':'100%')+';">'+strNo+'</button>'}
if(close){closeHtml='<button type="button" class="close" style="position:absolute;right:5px;top:4px;font-size:18px;">&times;</button>'}
var isPc=!0;var pcTop=150;var mainStyle='background:#fff;position:absolute;border-radius:2px;box-shadow:0 3px 9px rgba(0,0,0,0.5);';if($(window).width()<=500){isPc=!1;mainStyle+='top:15px;left:15px;right:15px;'}else{mainStyle+='top:'+pcTop+'px;left:50%;width:'+width+'px;margin-left:-'+(width/2)+'px;'}
var html='<div id="confirm_right" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:999999;">'+'<div style="'+mainStyle+'" class="cr-main">'+'<div class="cr-msg" style="padding:20px;text-align:'+textAlign+';"></div>'+'<div>'+noHtml+yesHtml+'</div>'+closeHtml+'</div></div>';$('#confirm_right').remove();$('body').append(html);$('#confirm_right .cr-msg').html(strMsg);if(isPc){var otherHeight=$(window).height()-$('#confirm_right .cr-main').height();otherHeight=parseInt(otherHeight);otherHeight=parseInt(otherHeight/2);if(otherHeight<pcTop){$('#confirm_right .cr-main').css('top',otherHeight)}}
var btnFunc=function(func){if(!closeAfterFunc){$('#confirm_right').remove()}
if(func&&typeof(func)){func()}
if(closeAfterFunc){$('#confirm_right').remove()}};$('#confirm_right .cr-btn-yes').bind('click',function(){btnFunc(func1)});$('#confirm_right .cr-btn-no').bind('click',function(){btnFunc(func2)});$('#confirm_right .close').bind('click',function(){$('#confirm_right').remove()})}
function page_translator(publicPath,acceptLang){window.gg_page_translator_loaded=!1;if(!window.gg_page_translator_loaded){window.gg_page_translator_loaded=!0;(function(){var allLanguage='de,hi,lt,hr,lv,ht,hu,zh-CN,hy,uk,mg,id,ur,mk,ml,mn,af,mr,uz,ms,el,mt,is,it,my,es,et,eu,ar,pt-PT,ja,ne,az,fa,ro,nl,en-GB,no,be,fi,ru,bg,fr,bs,sd,se,si,sk,sl,ga,sn,so,gd,ca,sq,sr,kk,st,km,kn,sv,ko,sw,gl,zh-TW,pt-BR,co,ta,gu,ky,cs,pa,te,tg,th,la,cy,pl,da,tr'.split(',');var localLanguage=null;if(acceptLang){var regQ=new RegExp("\\s*\\bq\\s*=\\s*\\d*(\\.\\d+){0,1}",'gi');acceptLang=acceptLang.replace(regQ,'');var regLang=new RegExp("^[\,\;]{0,1}\\s*([a-zA-Z\-]+)");acceptLang=acceptLang.match(regLang);acceptLang=acceptLang?acceptLang[1]:'';if(acceptLang){localLanguage=acceptLang}}
if(!localLanguage){if(navigator.language){localLanguage=navigator.language}else{localLanguage=navigator.browserLanguage}}
if(localLanguage&&localLanguage.toLowerCase()!='zh-cn'){if(allLanguage.indexOf(localLanguage)<=-1){if(localLanguage.indexOf('-')>-1){localLanguage=localLanguage.split('-');localLanguage=localLanguage[0]}
if(allLanguage.indexOf(localLanguage)<=-1&&localLanguage!='en'){var regLangStart=new RegExp("^"+localLanguage+"\\b");var hasLangStart=null;for(var i in allLanguage){if(allLanguage[i]&&regLangStart.test(allLanguage[i])){hasLangStart=allLanguage[i];break}}
localLanguage=hasLangStart?hasLangStart:null}}
if(localLanguage&&localLanguage.toLowerCase()!='zh-cn'){window.localLanguage=localLanguage;window.resourcesUrl=publicPath+'/googleTrans';var eleLink=document.createElement('link');eleLink.setAttribute('rel','stylesheet');eleLink.setAttribute('href',window.resourcesUrl+'/page.css');var eleScript=document.createElement('script');eleScript.setAttribute('type','text/javascript');eleScript.setAttribute('src',window.resourcesUrl+'/page.js');$('body').append(eleLink);$('body').append(eleScript)}}})()}}
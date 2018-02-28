/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 http://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  http://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */
function StoreClass(){}
StoreClass.prototype={constructor:StoreClass,init_iframe:function(){var boxHeight=$(window).height()-$('.main-header').height();$('.content').height(boxHeight+'px');$('#iframe_main').on('load',function(){$('.iframe-loading').remove();$(this).show()})},init_my:function(){$('.store-detail').bind('click',function(){windowStore('详细',$(this).attr('href'));return!1})},init_link:function(){var $_o=this;if(window.site_config.clientinfo){var domain='http://www.skycaiji.com';$('[src^="'+domain+'"]').each(function(){$_o.set_link('src',$(this))});$('[href^="'+domain+'"]').each(function(){$_o.set_link('href',$(this))});$('[action^="'+domain+'"]').each(function(){$_o.set_link('action',$(this))})}},set_link:function(type,obj){var url=obj.attr(type);if(type){url+=(url.indexOf('?')>-1?'&':'?')+'clientinfo='+encodeURIComponent(window.site_config.clientinfo);obj.attr(type,url)}},is_login:function(func){var cname='store_islogin';var is_login=getCookie(cname);if(!is_login||is_login!='yes'){$.ajax({url:'http://www.skycaiji.com/user/account/is_login',type:'get',dataType:'jsonp',jsonp:'callback',success:function(status){if(status){setCookie(cname,'yes',1);func()}else{setCookie(cname,'no',1);toastr.error('请先登录');windowStore('登录','http://www.skycaiji.com/user/account/login')}}})}else if(is_login=='yes'){func()}}}
var storeClass=new StoreClass();$(document).ready(function(){storeClass.init_link()})
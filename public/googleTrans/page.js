'use strict';//严格模式
function googleTranslateElementInit(){
	new google.translate.TranslateElement({
		pageLanguage: 'zh-CN',//页面源语言，防止翻译非中文内容
		layout:google.translate.TranslateElement.InlineLayout.SIMPLE,
		autoDisplay:true,
	},'google_translate_element');
}
function googleTranslateCookie(a, b) {
    for (var c = window.location.hostname.split("."); 2 < c.length; )
        c.shift();
    c = ";domain=" + c.join(".");
    null != b ? a = a + "=" + b : (b = new Date,
    b.setTime(b.getTime() - 1),
    a = a + "=none;expires=" + b.toGMTString());
    a += ";path=/";
    document.cookie = a;
    try {
        document.cookie = a + c;
    } catch (d) {}
}

$(document).ready(function(){
	//添加元素
	$('body').append('<div id="google_translate_element" style="position:fixed;bottom:10px;right:10px;z-index:2000;opacity:0.4;cursor:move!important;"></div>');
	
	//设置cookie
	var hasCookie=false;
	if (document.cookie.length>0){
		if(document.cookie.indexOf("googtrans=")>-1){
			hasCookie=true;
		}
	}
	if(!hasCookie){
		googleTranslateCookie('googtrans',null);//先删除，防止保留了旧cookie
		googleTranslateCookie('googtrans','/zh-CN/'+localLanguage);//设置cookie实现自动翻译
	}
	$.getScript(window.resourcesUrl+'/js/element.js?cb=googleTranslateElementInit');

	if($('#google_translate_element').draggable){
		$('#google_translate_element').draggable();//可拖拽
	}
	if(window.site_config){
		//跳转设置页面
		$('#google_translate_element').on('click','.goog-te-gadget-icon',function(){
			window.location.href=ulink('admin/setting/site');
		});
	}
});
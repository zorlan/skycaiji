/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 http://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  http://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */
'use strict';function JsonTree(){this.treeId='';this.treeClass=''}
JsonTree.prototype={constructor:JsonTree,load:function(data){var $_o=this;if(htmlIsJson(data)){data=JSON.parse(data);if(data){var html='<ul>';for(var i in data){html+=$_o.node(i,data[i])}
html+='</ul>';$($_o.treeId).html(html)}}
$($_o.treeId).on('click',$_o.treeClass,function(){if($(this).hasClass('glyphicon-triangle-bottom')){$(this).removeClass('glyphicon-triangle-bottom');$(this).addClass('glyphicon-triangle-right');var hasSub=!1;$(this).siblings('ul').children('li').each(function(){var subTree=$(this).find($_o.treeClass).eq(0);if(subTree.length>0){subTree.removeClass('glyphicon-triangle-bottom').addClass('glyphicon-triangle-right').siblings('ul').hide();hasSub=!0}});if(!hasSub){$(this).siblings('ul').hide()}}else{$(this).removeClass('glyphicon-triangle-right');$(this).addClass('glyphicon-triangle-bottom');$(this).siblings('ul').show();$(this).siblings('ul').children('li').each(function(){$(this).find($_o.treeClass).eq(0).removeClass('glyphicon-triangle-right').addClass('glyphicon-triangle-bottom').siblings('ul').show()})}})},node:function(node,list){var $_o=this;var html='<li>';var isList=!1;if(list){if(typeof(list)=='object'&&!$.isEmptyObject(list)){isList=!0}}
if(isList){html+='<span class="glyphicon glyphicon-triangle-bottom tree"></span><span class="node">'+node+'</span><ul>';for(var i in list){html+=$_o.node(i,list[i])}
html+='</ul>'}else{html+='<span class="node">'+node+'</span>: <span class="val">'+list+'</span>'}
html+='</li>';return html}}
var jsonTree=new JsonTree()
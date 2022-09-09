/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 https://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  https://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */
'use strict';function ToolJsonTree(){this.treeId=''}
ToolJsonTree.prototype={constructor:ToolJsonTree,load:function(data){var $_o=this;if(dataIsJson(data)){$($_o.treeId).html('<div class="tool-json-tree"></div>');$($_o.treeId).off('click','[data-tree-visualize]').on('click','[data-tree-visualize]',function(){var objId='#'+$(this).attr('data-tree-visualize');visualizeData($(objId).val())});data=JSON.parse(data);if(data){var html='<ul>';for(var i in data){html+=$_o.node(i,data[i])}
html+='</ul>';$($_o.treeId+' .tool-json-tree').html(html)}}else{$($_o.treeId).html('未获取到JSON数组')}
$($_o.treeId).on('click','.tree',function(){if($(this).hasClass('glyphicon-triangle-bottom')){$(this).removeClass('glyphicon-triangle-bottom');$(this).addClass('glyphicon-triangle-right');var hasSub=!1;$(this).siblings('ul').children('li').each(function(){var subTree=$(this).find('.tree').eq(0);if(subTree.length>0){subTree.removeClass('glyphicon-triangle-bottom').addClass('glyphicon-triangle-right').siblings('ul').hide();hasSub=!0}});$(this).siblings('ul').hide()}else{$(this).removeClass('glyphicon-triangle-right');$(this).addClass('glyphicon-triangle-bottom');$(this).siblings('ul').show();$(this).siblings('ul').children('li').each(function(){$(this).find('.tree').eq(0).removeClass('glyphicon-triangle-right').addClass('glyphicon-triangle-bottom').siblings('ul').show()})}})},node:function(node,list){var $_o=this;var html='<li>';var isList=!1;if(list){if(typeof(list)=='object'&&!$.isEmptyObject(list)){isList=!0}}
if(isList){html+='<span class="glyphicon glyphicon-triangle-bottom tree"></span><span class="node">'+node+'</span><ul>';for(var i in list){html+=$_o.node(i,list[i])}
html+='</ul>'}else{html+='<span class="node">'+node+': </span>';if(dataIsJson(list)||dataIsHtml(list)){var eleId='txt_'+generateUUID();list=list.replace(/\</g,'&lt;').replace(/\>/g,'&gt;');html+='<span class="val"><a href="javascript:;" data-tree-visualize="'+eleId+'">预览</a></span><div class="text"><textarea id="'+eleId+'" rows="3">'+list+'</textarea></div>'}else if(typeof(list)!='object'){html+='<span class="val">'+list+'</span>'}}
html+='</li>';return html}}
window.tool_json_tree=new ToolJsonTree()
/*
 |--------------------------------------------------------------------------
 | SkyCaiji (蓝天采集器)
 |--------------------------------------------------------------------------
 | Copyright (c) 2018 https://www.skycaiji.com All rights reserved.
 |--------------------------------------------------------------------------
 | 使用协议  https://www.skycaiji.com/licenses
 |--------------------------------------------------------------------------
 */
'use strict';function StoreClass(){}
StoreClass.prototype={constructor:StoreClass,init_store:function(info){var $_o=this;info=info?info:{};if(info.notSafe){confirmRight(info.notSafe,function(){window.location.href=info.url},function(){window.location.href=ulink('admin/provider/list')})}else{window.location.href=info.url}},init_download:function(downloadData){var $_o=this;downloadData=downloadData?downloadData:{};var installFunc=function(){$_o.install(downloadData.provider_id,downloadData.addon_cat,downloadData.addon_id,null)}
if(downloadData.exist_addon){var msg='';if(downloadData.update_addon){msg='确定更新'+downloadData.cat_name+'？'}else{msg='存在'+downloadData.cat_name+'，是否覆盖？'}
confirmRight(msg,function(){installFunc()},function(){toastr.warning('已取消')})}else{installFunc()}},install:function(provId,addonCat,addonId,params){var $_o=this;params=params?params:{};var url='store/install?provider_id=_provid_&addon_cat=_cat_&addon_id=_id_&'+urlUsertoken();var urlParams={'_provid_':provId,'_cat_':addonCat,'_id_':addonId};if(addonCat=='app'){if(params.block_no){url+='&block_no='+params.block_no}}else{$('#down_percentage').text('');$('#down_progress_bar').css('width','30%')}
url=ulink(url,urlParams);ajaxOpen({type:'get',dataType:'json',url:url,success:function(data){if(data.code==1){if(addonCat=='app'){var dataData=data.data;dataData=dataData?dataData:{};if(dataData.next_block_no>0){var per=parseFloat(dataData.next_block_no/dataData.blocks)*100;per=parseInt(per);$('#down_progress_bar').css('width',per+'%');$('#down_percentage').text(per+'%');$_o.install(provId,addonCat,addonId,{'block_no':dataData.next_block_no})}else{$('#down_progress_bar').css('width','100%');ajaxDataMsg(data)}}else{$('#down_progress_bar').css('width','100%');if(addonCat=='app'){$('#down_percentage').text('100%')}
ajaxDataMsg(data)}}else{toastr.error(data.msg)}}})}}
var storeClass=new StoreClass()
'use strict';
function addCookie(sName,sValue,iDay){
	if(iDay){
		var oDate = new Date();
		oDate.setDate(oDate.getDate()+iDay);
		document.cookie=sName+'='+sValue+'; PATH=/; EXPIRES='+oDate.toGMTString();
	}else{
		document.cookie=sName+'='+sValue+'; PATH=/';
	}
}

function getCookie(sName){
	var arr = document.cookie.split('; ');
	for(var i=0;i<arr.length;i++){
		var arr2 = arr[i].split('=');
		if(arr2[0]==sName){
			return arr2[1];
		}
	}
}

function removeCookie(sName){
	addCookie(sName,1,-1);
}

window.onload = function(){
	var top = getCookie('top');
	document.body.scrollTop = top;
	$(window).bind("scroll", function(){
		addCookie('top',document.body.scrollTop,10);
	})
} 














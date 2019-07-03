//https://github.com/paulirish/matchMedia.js/
window.billmatepopupLoaded = true;
function match_media_mount(){
    window.matchMedia = window.matchMedia || (function(doc, undefined){

        var docElem  = doc.documentElement,
            refNode  = docElem.firstElementChild || docElem.firstChild,
        // fakeBody required for <FF4 when executed in <head>
            fakeBody = doc.createElement('body'),
            div      = doc.createElement('div');

        div.id = 'mq-test-1';
        div.style.cssText = "position:absolute;top:-100em";
        fakeBody.style.background = "none";
        fakeBody.appendChild(div);

        var mqRun = function ( mq ) {
                div.innerHTML = '&shy;<style media="' + mq + '"> #mq-test-1 { width: 42px; }</style>';
                docElem.insertBefore( fakeBody, refNode );
                bool = div.offsetWidth === 42;
                docElem.removeChild( fakeBody );

                return { matches: bool, media: mq };
            },

            getEmValue = function () {
                var ret,
                    body = docElem.body,
                    fakeUsed = false;

                div.style.cssText = "position:absolute;font-size:1em;width:1em";

                if( !body ) {
                    body = fakeUsed = doc.createElement( "body" );
                    body.style.background = "none";
                }

                body.appendChild( div );

                docElem.insertBefore( body, docElem.firstChild );

                if( fakeUsed ) {
                    docElem.removeChild( body );
                } else {
                    body.removeChild( div );
                }

                //also update eminpx before returning
                ret = eminpx = parseFloat( div.offsetWidth );

                return ret;
            },

        //cached container for 1em value, populated the first time it's needed
            eminpx,

        // verify that we have support for a simple media query
            mqSupport = mqRun( '(min-width: 0px)' ).matches;

        return function ( mq ) {
            if( mqSupport ) {
                return mqRun( mq );
            } else {
                var min = mq.match( /\(min\-width:[\s]*([\s]*[0-9\.]+)(px|em)[\s]*\)/ ) && parseFloat( RegExp.$1 ) + ( RegExp.$2 || "" ),
                    max = mq.match( /\(max\-width:[\s]*([\s]*[0-9\.]+)(px|em)[\s]*\)/ ) && parseFloat( RegExp.$1 ) + ( RegExp.$2 || "" ),
                    minnull = min === null,
                    maxnull = max === null,
                    currWidth = doc.body.offsetWidth,
                    em = 'em';

                if( !!min ) { min = parseFloat( min ) * ( min.indexOf( em ) > -1 ? ( eminpx || getEmValue() ) : 1 ); }
                if( !!max ) { max = parseFloat( max ) * ( max.indexOf( em ) > -1 ? ( eminpx || getEmValue() ) : 1 ); }

                bool = ( !minnull || !maxnull ) && ( minnull || currWidth >= min ) && ( maxnull || currWidth <= max );

                return { matches: bool, media: mq };
            }
        };

    }( document ));

//https://raw.github.com/paulirish/matchMedia.js/master/matchMedia.addListener.js
    (function(){if(window.matchMedia&&window.matchMedia("all").addListener){return false}var e=window.matchMedia,t=e("only all").matches,n=false,r=0,i=[],s=function(t){clearTimeout(r);r=setTimeout(function(){for(var t=0,n=i.length;t<n;t++){var r=i[t].mql,s=i[t].listeners||[],o=e(r.media).matches;if(o!==r.matches){r.matches=o;for(var u=0,a=s.length;u<a;u++){s[u].call(window,r)}}}},30)};window.matchMedia=function(r){var o=e(r),u=[],a=0;o.addListener=function(e){if(!t){return}if(!n){n=true;window.addEventListener("resize",s,true)}if(a===0){a=i.push({mql:o,listeners:u})}u.push(e)};o.removeListener=function(e){for(var t=0,n=u.length;t<n;t++){if(u[t]===e){u.splice(t,1)}}};return o}})()
}
var xxx_modalPopupWindow = null;
var popupshowed = false;
var $checkoutbtn = null;
function CreateModalPopUpObject() {
    if (xxx_modalPopupWindow == null) {
        xxx_modalPopupWindow = new ModalPopupWindow();
    }
    return xxx_modalPopupWindow;
}

function ModalPopupWindow() {
    var strOverLayHTML = '<div id="divOverlay" style="position:absolute;z-index:1011; background-color:WHITE; filter: alpha(opacity = 70);opacity:0.7;"></div><div id="divFrameParent" style="position:absolute;z-index:1012; display:none;background-color:white;border:1px solid;-moz-box-shadow: 0 0 10px 10px #BBB;-webkit-box-shadow: 0 0 10px 10px #BBB;box-shadow: 0 0 10px 10px #BBB;padding:10px;line-height:21px;font-size:15px;color:#000;font-family:Arial,Helvetica,sans-serif;"	class="Example_F"><span class="closebutton" onclick="modalWin.HideModalPopUp();" style="float: right;">X</span><div class="checkout-heading" id="spanOverLayTitle"></div><div id="divMessage" style="display:none;"><span id="spanMessage"></span></div><span id="spanLoading"></span></div>';
    var orginalHeight;
    var orginalWidth;
    var btnStyle="";
    var maximize = false;
    div = document.createElement("div");
    div.innerHTML = strOverLayHTML;
    //document.body.appendChild(div);
    document.body.insertBefore(div, document.body.firstChild);

    this.ResizePopUp = function(height, width) {
        var divFrameParent = document.getElementById("divFrameParent");
        var divOverlay = document.getElementById("divOverlay");
        var left = (window.screen.availWidth - width) / 2;
        var top = (window.screen.availHeight - height) / 2;
        var xy = GetScroll();
        if (maximize) {
            left = xy[0] + 10;
            top = xy[1] + 10;
        } else {
            left += xy[0];
            top += xy[1];
        }
        divFrameParent.style.top = top + "px";
        divFrameParent.style.left = left + "px";
        divFrameParent.style.height = height + "px";
        divFrameParent.style.width = width + "px";
        ShowDivInCenter("divFrameParent");
    };
    var onPopUpCloseCallBack = null;
    var callbackArray = null;
    this.SetLoadingImagePath = function (imagePath) {
        document.getElementById("imgOverLayLoading").src = imagePath;
    };
    this.SetCloseButtonImagePath = function (imagePath) {
        //document.getElementById("imgOverLayClose").src = imagePath;
    };

    this.SetButtonStyle = function (_btnStyle) {
        btnStyle =_btnStyle;
    };

    function ApplyBtnStyle(){
    }

    function __InitModalPopUp(height, width, title) {
        orginalWidth = width;
        orginalHeight = height;
        maximize = false;
        var divFrameParent = document.getElementById("divFrameParent");
        var divOverlay = document.getElementById("divOverlay");
        var left = (window.screen.availWidth - width) / 2;
        var top = (window.screen.availHeight - height) / 2;
        var xy = GetScroll();
        left += xy[0];
        top += xy[1];
        document.getElementById("spanOverLayTitle").innerHTML = title;
        divOverlay.style.top = "0px";
        divOverlay.style.left = "0px";
        var e = document;
        var c = "Height";
        var maxHeight = Math.max(e.documentElement["client" + c], e.body["scroll" + c], e.documentElement["scroll" + c], e.body["offset" + c], e.documentElement["offset" + c]);
        c = "Width";
        var maxWidth = Math.max(e.documentElement["client" + c], e.body["scroll" + c], e.documentElement["scroll" + c], e.body["offset" + c], e.documentElement["offset" + c]);
        divOverlay.style.height = maxHeight + "px";
        divOverlay.style.width = maxWidth - 2 + "px";
        divOverlay.style.display = "";
        divFrameParent.style.display = "";
        //$('#divFrameParent').animate({ opacity: 1 }, 2000);
        divFrameParent.style.top = (top-100) + "px";
        divFrameParent.style.left = left + "px";
        divFrameParent.style.height = height + "px";
        divFrameParent.style.width = width + "px";
        onPopUpCloseCallBack = null;
        callbackArray = null;
    }
    this.ShowURL = function (url, height, width, title, onCloseCallBack, callbackFunctionArray, maxmizeBtn) {
    };
    this.ShowMessage = function (message, height, width, title) {
        popupshowed = true;
        __InitModalPopUp(height, width, title);
        document.getElementById("spanMessage").innerHTML = message;
        document.getElementById("divMessage").style.display = "";
        document.getElementById("spanLoading").style.display = "none";
        ApplyBtnStyle();
        ShowDivInCenter("divFrameParent");
    };
    this.ShowConfirmationMessage = function (message, height, width, title, onCloseCallBack, firstButtonText, onFirstButtonClick, secondButtonText, onSecondButtonClick) {
        this.ShowMessage(message, height, width, title);
        var maxWidth = 100;
        document.getElementById("spanMessage").innerHTML = message;
        document.getElementById("divMessage").style.display = "";
        document.getElementById("spanLoading").style.display = "none";
        if (onCloseCallBack != null && onCloseCallBack != '') {
            onPopUpCloseCallBack = onCloseCallBack;
        }
        ApplyBtnStyle();
    };

    function ShowLoading() {
        document.getElementById("spanLoading").style.display = "";
    }
    this.HideModalPopUp = function () {
        var divFrameParent = document.getElementById("divFrameParent");
        popupshowed = false;
        var divOverlay = document.getElementById("divOverlay");
        divOverlay.style.display = "none";
        divFrameParent.style.display = "none";
        if (onPopUpCloseCallBack != null && onPopUpCloseCallBack != '') {
            onPopUpCloseCallBack();
        }
    };
    this.CallCallingWindowFunction = function (index, para) {
        callbackArray[index](para);
    };
    this.ChangeModalPopUpTitle = function (title) {
        document.getElementById("spanOverLayTitle").innerHTML = title;
    };

    function setParentVariable(variableName, variableValue) {
        window[String(variableName)] = variableValue;
    }

    function GetScroll() {
        if (window.pageYOffset != undefined) {
            return [pageXOffset, pageYOffset];
        } else {
            var sx, sy, d = document,
                r = d.documentElement,
                b = d.body;
            sx = r.scrollLeft || b.scrollLeft || 0;
            sy = r.scrollTop || b.scrollTop || 0;
            return [sx, sy];
        }
    }
}


function AddEvent(html_element, event_name, event_function)
{
    if(html_element.attachEvent) //Internet Explorer
        html_element.attachEvent("on" + event_name, function() {event_function.call(html_element);});
    else if(html_element.addEventListener) //Firefox & company
        html_element.addEventListener(event_name, event_function, false); //don't need the 'call' trick because in FF everything already works in the right way
}
var modalWin = null;
function changeBillEvent(){

    if( oldurl == null && typeof payment != 'undefined' && typeof billmateindexurl != 'undefined'){

        oldurl = payment.saveUrl;
        payment.saveUrl = billmateindexurl;
        payment.onComplete = function(res){
            checkout.setLoadWaiting(false);
            eval(res.responseText);
        }
    }
//	if( typeof FireCheckout != 'undefined' && fireoldurl == null ){
//		fireoldurl = checkout.urls.save;
//	}
}
function updateAddress(){
    if( typeof FireCheckout != 'undefined' || typeof Lightcheckout != 'undefined' || typeof checkout.form != 'undefined'){
        if( typeof checkout.form == 'undefined'){
            params = Form.serialize(checkoutForm.form.id);
        } else if(typeof checkout.form != 'undefined'){
            params = Form.serialize(checkout.form);
        }
        checkout.setLoadWaiting(true);
        new Ajax.Request(billmatesaveurl, {
            method: 'post',
            parameters: params,
            onSuccess: function(res) {
                checkout.setLoadWaiting(false);
                eval(res.responseText);
            }
        });
    }else {
        payment.saveUrl = billmatesaveurl;
        payment.onComplete = function(res){
            eval(res.responseText);
            modalWin.HideModalPopUp();
        };
        payment.save();
    }
}
function afterSave(){
    if(typeof $checkoutbtn == 'function' ){
        $checkoutbtn.call();
    }
}
function closefunc(obj){
    checkout.setLoadWaiting(false);
    modalWin.HideModalPopUp();
}
function reviewstep(){
}
String.prototype.trim=function(){return this.replace(/^\s+|\s+$/g, '');};

function checkAddress() {
    var paymentInputObjects = $$('[name="payment[method]"]:checked');
    var selectedGateway = paymentInputObjects.length > 0 ? paymentInputObjects[0].value : '';

    if (selectedGateway != 'billmateinvoice' && selectedGateway != 'billmatepartpayment' && !onchange_person_number) {
        afterSave();
        return;
    }

    if (
        ( typeof checkout == 'undefined' || typeof checkout.form == 'undefined' )
        && ( typeof checkoutForm == 'undefined' || typeof checkoutForm.form.id == 'undefined' )
    ) {
        return false;
    }
    if (typeof checkout.form == 'undefined') {
        params = Form.serialize(checkoutForm.form.id);
    } else if (typeof checkout.form != 'undefined') {
        params = Form.serialize(checkout.form);
    }

    checkout.setLoadWaiting(true);

    url = billmateindexurl;
    if (onchange_person_number) {
        params += '&pophide=true';
    }
    if ($('billing-new-address-form').visible() &&  $('person_number') && $('person_number').value == '') {
        if ($('getaddress_failure'))
            $('getaddress_failure').remove();
        $('person_number').addClassName('validation-failed');
        $('person_number').insert({after: '<div class="validation-advice" id="getaddress_failure">' + PNO_ERROR + '</div>'})
        checkout.setLoadWaiting(false);

    }else if(!$('billing-new-address-form').visible() &&  $(selectedGateway+'_pno').value == ''){
        $(selectedGateway+'_pno').insert({after: '<div class="validation-advice" id="getaddress_failure">' + PNO_ERROR + '</div>'});
        $(selectedGateway+'_pno').addClassName('validation-failed');
        checkout.setLoadWaiting(false);
    }else if(!$('person_number') && $(selectedGateway+'_pno').value == ''){
        $(selectedGateway+'_pno').insert({after: '<div class="validation-advice" id="getaddress_failure">' + PNO_ERROR + '</div>'});
        $(selectedGateway+'_pno').addClassName('validation-failed');
        checkout.setLoadWaiting(false);
    }
    else if(($('person_number') && $('person_number').value != '') || (!$('personnumber') && $(selectedGateway+'_pno').value != '')) {
        if($('person_number'))
            $('person_number').removeClassName('validation-failed');
        $(selectedGateway+'_pno').removeClassName('validation-failed');


        if ($('getaddress_failure')) {
            $('getaddress_failure').remove();
        }

        new Ajax.Request(billmateindexurl, {
            method: 'post',
            parameters: params,
            onSuccess: function (res) {
                checkout.setLoadWaiting(false);

                eval(res.responseText);
            }
        });
    }
}
var onchange_person_number = false;
function paymentSave(){
    if( onchange_person_number ) return false;

    if(typeof checkout.form == 'undefined'){
        checkout.setLoadWaiting(false);
        /*payment.saveUrl = oldurl;
        payment.onComplete = function(){
            checkout.setLoadWaiting(false);
            payment.saveUrl = billmateindexurl;
            payment.onComplete = function(res){
                checkout.setLoadWaiting(false);
                eval(res.responseText);
            }
        };*/
        review.save();
    }else{
        if( typeof FireCheckout != 'undefined' ){
            checkout.save();
        }
    }
}
function selectDropDown(mySelect, value){
    var optCount      = mySelect.options.length;
    var reverseLookup = {};
    for (var i = 0; i < optCount; i++)
    {
        var option = mySelect.options[i];
        if( option.value == value ){
            mySelect.selectedIndex = i;
        }
    }
}
function ShowMessage(content,wtitle){
    if(matchMedia('(max-width: 800px)').matches){
        modalWin.ShowMessage(content,370,250,wtitle);
    }else if(matchMedia('(min-width: 800px)').matches){
        modalWin.ShowMessage(content,280,500,wtitle);
    }else{
    }
}
AddEvent(window,'resize',function(){
    if( popupshowed ){
        if(matchMedia('(max-width: 800px)').matches){
            modalWin.ResizePopUp(370,250);
        }else if(matchMedia('(min-width: 800px)').matches){
            modalWin.ResizePopUp(240,500);
        }
    }
});

Element.prototype.triggerEvent = function(eventName)
{
    if (document.createEvent)
    {
        var evt = document.createEvent('HTMLEvents');
        evt.initEvent(eventName, true, true);
        return this.dispatchEvent(evt);
    }
    if (this.fireEvent)
        return this.fireEvent('on' + eventName);
};
function SaveAddress(){
    var a = jQuery.each(jQuery("#billing-address input, #shipping-address input"),function(){
        if(this.id != 'billing:postcode' && this.id != 'shipping:postcode' && this.id!= 'billing:person_number'){
            jQuery(this).change(function(){
                $('billing:postcode').triggerEvent('change');
                //$('payment-method-reset').triggerEvent('click')
            });
        }
    });
}
function addTerms(){

        jQuery(document).Terms("villkor",{invoicefee:0},'#terms');
        jQuery(document).Terms("villkor_delbetalning",{eid: PARTPAYMENT_EID,effectiverate:34},"#terms-delbetalning");

}
AddEvent(window, 'load', function(){
    match_media_mount();



    if(typeof checkout!= 'undefined' && typeof checkout.form == 'undefined'){
        changeBillEvent();
    }
    modalWin = new CreateModalPopUpObject();
    jQuery.getScript('https://billmate.se/billmate/base_jquery.js',function(){addTerms();});

    if( $$('#checkout-review-submit .btn-checkout').length > 0 ){
        if($('billing-new-address-form').visible()) {
            SaveAddress();
        }
        //$checkoutbtn = $$('#checkout-review-submit .btn-checkout')[0].onclick;
        //$$('#checkout-review-submit .btn-checkout')[0].onclick = function(){ onchange_person_number = false; checkAddress(); };
    }
    var selectedmethod = $$('input:checked[type="radio"][name="payment[method]"]').pluck('value');
    if ($('person_number') && (selectedmethod == 'billmateinvoice' || selectedmethod == 'billmatepartpayment')) {
        if (!$('person_number').up('div').previous().down('em')) {
            $('person_number').up('div').previous().insert('<em>*</em>');
            $('person_number').up('div').previous().addClassName('required');
        }
    }
    if($('p_method_billmatepartpayment') || $('p_method_billmateinvoice')) {
        jQuery(document).on('click','input[name="payment[method]"]',function(e){
            if(e.target.id == 'p_method_billmatepartpayment' || e.target.id == 'p_method_billmateinvoice') {
                if ($('person_number')) {
                    if (!$('person_number').up('div').previous().down('em')) {
                        $('person_number').up('div').previous().insert('<em>*</em>');
                        $('person_number').up('div').previous().addClassName('required');
                    }
                }
            } else {
                if($('person_number')){
                    if ($('person_number').up('div').previous().down('em')) {
                        $('person_number').up('div').previous().down('em').remove();
                        $('person_number').up('div').previous().removeClassName('required');
                    }
                }
            }
        })
    }

});
function billmateGetAddress(e){
    Event.stop(e);
    onchange_person_number = true;
    checkAddress();
}

function ShowDivInCenter(divId)
{
    try
    {
        var div = document.getElementById(divId);
        divWidth = document.getElementById("divFrameParent").offsetWidth;
        divHeight = document.getElementById("divFrameParent").offsetHeight;

        // Get the x and y coordinates of the center in output browser's window
        var centerX, centerY;
        if (self.innerHeight)
        {
            centerX = self.innerWidth;
            centerY = self.innerHeight;
        }
        else if (document.documentElement && document.documentElement.clientHeight)
        {
            centerX = document.documentElement.clientWidth;
            centerY = document.documentElement.clientHeight;
        }
        else if (document.body)
        {
            centerX = document.body.clientWidth;
            centerY = document.body.clientHeight;
        }

        var offsetLeft = (centerX - divWidth) / 2;
        var offsetTop = (centerY - divHeight) / 2;

        // The initial width and height of the div can be set in the
        // style sheet with display:none; divid is passed as an argument to // the function
        var ojbDiv = document.getElementById(divId);

        left = (offsetLeft) / 2 + window.scrollX;
        top = (offsetTop) / 2 + window.scrollY;

        ojbDiv.style.position = 'absolute';
        ojbDiv.style.top = top + 'px';
        ojbDiv.style.left = offsetLeft + 'px';
        ojbDiv.style.display = "block";

    }
    catch (e) {}
}

function Action1(){
    alert('Action1 is excuted');
    modalWin.HideModalPopUp();
}

function Action2(){
    alert('Action2 is excuted');
    modalWin.HideModalPopUp();
}

function EnrollNow(msg){
    modalWin.HideModalPopUp();
    modalWin.ShowMessage(msg,200,400,'User Information',null,null);
}

function EnrollLater(){
    modalWin.HideModalPopUp();
    modalWin.ShowMessage(msg,200,400,'User Information',null,null);
}



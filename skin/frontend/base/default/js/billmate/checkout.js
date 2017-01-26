/**
 * Created by Boxedsolutions on 2016-12-07.
 */
var BillmateIframe = new function(){
    var self = this;
    var childWindow = null;
    this.updateAddress = function (data) {
        // When address in checkout updates;

        jQuery.ajax({
            url : UPDATE_ADDRESS_URL,
            data: data,
            type: 'POST',
            success: function(response){


                jQuery('#shipping-container').html(response);
            }
        });

    };
    this.updatePaymentMethod = function(data){
            jQuery.ajax({
                url : UPDATE_PAYMENT_METHOD_URL,
                data: data,
                type: 'POST',
                success: function(response){
                    console.log(response);
                }
            });
        
    };
    this.updateShippingMethod = function(){

    }
    this.createOrder = function(){
      // Create Order
            jQuery.ajax({
                url : CREATE_ORDER_URL,
                data: data,
                type: 'POST',
                success: function(response){
                    console.log(response);
                }
            });

    };
    this.initListeners = function () {
        document.observe('dom:loaded',function () {
            console.log('initEventListeners');
            window.addEventListener("message",self.handleEvent);

        })
    }
    this.handleEvent = function(event){
        console.log(event);
        var json = JSON.parse(event.data);
        self.childWindow = json.source;
        console.log(json);
        switch(json.event){
            case 'address_selected':
                self.updateAddress(json.data);
                break;
            case 'payment_method_selected':
                self.updatePaymentMethod(json.data);
                break;
            case 'checkout_success':
                self.createOrder(json.data);
                break;
            default:
                console.log(event);
                console.log('not implemented')
                break;

        }

    };

    this.updateCheckout = function(){
        var win = document.getElementById('checkout').contentWindow;
        win.postMessage(JSON.stringify({event: 'update_checkout'}),'*')
    }

    
};

var b_iframe = BillmateIframe;
b_iframe.initListeners();
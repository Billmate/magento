/**
 * Created by Boxedsolutions on 2017-02-28.
 */
document.observe('dom:loaded',function(){
    if($('billmate_checkout_cart_right') && $('billmate_checkout_cart_left')){
        $('billmate_checkout_cart_left').observe('change',function(e){

            if(e.target.value == 1){
                $('billmate_checkout_cart_right').setValue(0);
            }
            if(e.target.value == 0){
                $('billmate_checkout_cart_right').setValue(1);
            }
        })

        $('billmate_checkout_cart_right').observe('change',function(e){

            if(e.target.value == 1){
                $('billmate_checkout_cart_left').setValue(0);
            }
            if(e.target.value == 0){
                $('billmate_checkout_cart_left').setValue(1);
            }
        })
    }
});
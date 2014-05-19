<?php

class Billmate_Partpayment_Block_TablePclass extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    public function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $pclass = Mage::getModel('partpayment/pclass')->getCollection();
        $this->setElement($element);
        if( $pclass->count() > 0 ){
            $html = '<div class="grid"><table border="0" class="data"><tr class="headings"><th>PClassid</th><th>Type</th><th>Description</th><th>Months</th><th>Interest Rate</th><th>Invoice Fee</th><th>Start Fee</th><th>Min Amount</th><th>Max Amount</th><th>Expire</th><th>Country</th></tr>';
            $i=0;
            foreach($pclass as $_item ){
                $id = $_item->getPclassid();
                $typ= $_item->getType();
                $des= $_item->getDescription();
                $mont= $_item->getMonths();
                $int = $_item->getInterestrate();
                $fee = $_item->getInvoicefee();
                $min = $_item->getMinamount();
                $max = $_item->getMaxamount();				
                $startfee = $_item->getStartfee();
                $country= $_item->getCountryCode();
                $exp = $_item->getExpire();
                $class = $i%2 == 0 ? 'even' :'odd';
                $i++;
                $html.='<tr class="'.$class.' pointer"><td>'.$id.'</td><td>'.$typ.'</td><td>'.$des.'</td><td class="a-center">'.$mont.'</td><td>'.$int.'</td><td>'.$fee.'</td><td>'.$startfee.'</td><td>'.$min.'</td><td>'.$max.'</td><td>'.$exp.'</td><td>'.$country.'</td></tr>';
            }
            $html.='</table></div>';
        } else {
            $html = '<b>'.$this->__('No Pclasses found').'</b>';
        }
        return $html;
    }
}

<?php

class Billmate_CustomPay_Block_Partpayment_TablePclass extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     *
     * @return string
     */
    public function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $pclass = Mage::getModel('billmatecustompay/pclass')->getCollection();
        $pclass->addFieldToFilter('store_id', Mage::helper('billmatecustompay')->getStoreIdForConfig());
        $this->setElement($element);
        if ( $pclass->count() > 0 ) {
            $html = '<div class="grid"><table border="0" class="data"><tr class="headings"><th>'
                . Mage::helper('billmatecustompay')->__('PClassid').'</th><th>'.Mage::helper('billmatecustompay')->__('Type')
                . '</th><th>'.Mage::helper('billmatecustompay')->__('Description')
                . '</th><th>'.Mage::helper('billmatecustompay')->__('Months')
                . '</th><th>'.Mage::helper('billmatecustompay')->__('Interest Rate')
                . '</th><th>'.Mage::helper('billmatecustompay')->__('Invoice Fee')
                . '</th><th>'.Mage::helper('billmatecustompay')->__('Start Fee')
                . '</th><th>'.Mage::helper('billmatecustompay')->__('Min Amount')
                . '</th><th>'.Mage::helper('billmatecustompay')->__('Max Amount')
                . '</th><th>'.Mage::helper('billmatecustompay')->__('Expire')
                . '</th><th>'.Mage::helper('billmatecustompay')->__('Country')
                . '</th></tr>';
            $i=0;
            foreach ($pclass as $_item ) {
                $id = $_item->getPaymentplanid();
                $typ= $_item->getType();
                $des= $_item->getDescription();
                $mont= $_item->getNbrofmonths();
                $int = $_item->getInterestrate();
                $fee = $_item->getHandlingfee();
                $min = $_item->getMinamount();
                $max = $_item->getMaxamount();				
                $startfee = $_item->getStartfee();
                $country= $_item->getCountryCode();
                $exp = $_item->getExpirydate();
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

<?php
require_once 'Mage/Adminhtml/controllers/Sales/Order/InvoiceController.php';
class FireGento_Pdf_Adminhtml_Sales_Order_InvoiceController extends Mage_Adminhtml_Sales_Order_InvoiceController
{
    /**
     * Create pdf for current invoice
     *
     * Overwritten to change the pdf output name
     */
    public function printAction()
    {
        $this->_initInvoice();

        if ($invoiceId = $this->getRequest()->getParam('invoice_id')) {
            if ($invoice = Mage::getModel('sales/order_invoice')->load($invoiceId)) {
                $pdf = Mage::getModel('sales/order_pdf_invoice')->getPdf(array($invoice));
                $this->_prepareDownloadResponse(
                    Mage::helper('firegento_pdf')->__('invoice').Mage::getSingleton('core/date')->date('Y-m-d_H-i-s').'.pdf',
                    $pdf->render(),
                    'application/pdf'
                );
            }
        }
        else {
            $this->_forward('noRoute');
        }
    }

}

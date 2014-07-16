<?php
/**
 * This file is part of the FIREGENTO project.
 *
 * FireGento_Pdf is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 3 as
 * published by the Free Software Foundation.
 *
 * This script is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * PHP version 5
 *
 * @category  FireGento
 * @package   FireGento_Pdf
 * @author    FireGento Team <team@firegento.com>
 * @copyright 2013 FireGento Team (http://www.firegento.com)
 * @license   http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 * @version   $Id:$
 * @since     0.1.0
 */
/**
 * Creditmemo model rewrite.
 *
 * @category  FireGento
 * @package   FireGento_Pdf
 * @author    FireGento Team <team@firegento.com>
 * @copyright 2013 FireGento Team (http://www.firegento.com)
 * @license   http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 * @version   $Id:$
 * @since     0.1.0
 */
class FireGento_Pdf_Model_Engine_Creditmemo_Default extends FireGento_Pdf_Model_Engine_Abstract
{

    /**
     * constructor to set mode to creditmemo
     */
    public function __construct()
    {
        parent::__construct();
        $this->setMode('creditmemo');
    }

    /**
     * Return PDF document
     *
     * @param  array $creditmemos creditmemos to generate pdfs for
     *
     * @return Zend_Pdf
     */
    public function getPdf($creditmemos = array())
    {
        $this->_beforeGetPdf();
        $this->_initRenderer('creditmemo');

        $pdf = new Zend_Pdf();
        $this->_setPdf($pdf);

        // pagecounter is 0 at the beginning, because it is incremented in newPage()
        $this->pagecounter = 0;

        foreach ($creditmemos as $creditmemo) {
            if ($creditmemo->getStoreId()) {
                Mage::app()->getLocale()->emulate($creditmemo->getStoreId());
                Mage::app()->setCurrentStore($creditmemo->getStoreId());
            }
            $order = $creditmemo->getOrder();
            $this->setOrder($order);

            $page = $this->newPage(array());

            $this->insertAddressesAndHeader($page, $creditmemo, $order);

            $this->_setFontRegular($page, 9);
            $this->_drawHeader($page);

            $this->y -= 20;

            $position = 0;

            foreach ($creditmemo->getAllItems() as $item) {
                if ($item->getOrderItem()->getParentItem()) {
                    continue;
                }
                /* Draw item */
                $position++;
                $this->_drawItem($item, $page, $order, $position);
                $page = end($pdf->pages);
            }

            /* add line after items */
            $page->drawLine($this->margin['left'], $this->y + 5, $this->margin['right'], $this->y + 5);

            /* Add totals */
            $page = $this->insertTotals($page, $creditmemo);

            /* add note */
            $page = $this->_insertNote($page, $order, $creditmemo);

            // Add footer
            $this->_addFooter($page, $creditmemo->getStore());
        }

        $this->_afterGetPdf();

        if ($creditmemo->getStoreId()) {
            Mage::app()->getLocale()->revert();
        }
        return $pdf;
    }

    /**
     * Draw table header for product items
     *
     * @param  Zend_Pdf_Page $page page to draw on
     *
     * @return void
     */
    protected function _drawHeader(Zend_Pdf_Page $page)
    {
        $page->setFillColor($this->colors['grey1']);
        $page->setLineColor($this->colors['grey1']);
        $page->setLineWidth(1);
        $page->drawRectangle($this->margin['left'], $this->y, $this->margin['right'], $this->y - 15);

        $page->setFillColor($this->colors['black']);
        $font = $this->_setFontRegular($page, 9);

        $this->y -= 11;
        $page->drawText(
            Mage::helper('firegento_pdf')->__('Pos'),
            $this->margin['left'] + 3,
            $this->y,
            $this->encoding
        );
        $page->drawText(
            Mage::helper('firegento_pdf')->__('No.'),
            $this->margin['left'] + 25,
            $this->y,
            $this->encoding
        );
        $page->drawText(
            Mage::helper('firegento_pdf')->__('Description'),
            $this->margin['left'] + 80,
            $this->y,
            $this->encoding
        );

        $singlePrice = Mage::helper('firegento_pdf')->__('Price (excl. tax)');
        $page->drawText(
            $singlePrice,
            $this->margin['right'] - 153 - $this->widthForStringUsingFontSize($singlePrice, $font, 9),
            $this->y,
            $this->encoding
        );

        $page->drawText(
            Mage::helper('firegento_pdf')->__('Qty'),
            $this->margin['left'] + 360,
            $this->y,
            $this->encoding
        );

        $taxLabel = Mage::helper('firegento_pdf')->__('Tax item');
        $page->drawText(
            $taxLabel,
            $this->margin['right'] - 75 - $this->widthForStringUsingFontSize($taxLabel, $font, 9),
            $this->y,
            $this->encoding
        );

        $totalLabel = Mage::helper('firegento_pdf')->__('Total');
        $page->drawText(
            $totalLabel,
            $this->margin['right'] - 5 - $this->widthForStringUsingFontSize($totalLabel, $font, 10),
            $this->y,
            $this->encoding
        );
    }

    /**
     * Initialize renderer process.
     *
     * @param  string $type renderer type to initialize
     *
     * @return void
     */
    protected function _initRenderer($type)
    {
        parent::_initRenderer($type);

        $this->_renderers['default'] = array(
            'model'    => 'firegento_pdf/items_default',
            'renderer' => null
        );
        $this->_renderers['grouped'] = array(
            'model'    => 'firegento_pdf/items_grouped',
            'renderer' => null
        );
        $this->_renderers['bundle'] = array(
            'model'    => 'firegento_pdf/items_bundle',
            'renderer' => null
        );
    }

    /**
     * @param Zend_Pdf_Page              $page
     * @param Mage_Sales_Model_Abstract $source
     * @param Mage_Sales_Model_Order     $order
     */
    protected function insertAddressesAndHeader(Zend_Pdf_Page $page, Mage_Sales_Model_Abstract $source, Mage_Sales_Model_Order $order)
    {
        // Add logo
        $this->insertLogo($page, $source->getStore());

        // Add billing and shipping address
        $this->y = 692 - $this->_marginTop;
        $this->_insertCustomerAddress($page, $order);

        // Add sender address
        $this->y = 705 - $this->_marginTop;
        $this->_insertSenderAddessBar($page);

        // Add head
        $this->y = 572 - $this->_marginTop;
        $this->insertHeader($page, $order, $source);

        /* Add table head */
        // make sure that item table does not overlap heading
        if ($this->y > 575 - $this->_marginTop) {
            $this->y = 575 - $this->_marginTop;
        }

        $this->y = $this->y - 20;
    }

    /**
     * Inserts the customer address. The default address is the billing address.
     *
     * @param  Zend_Pdf_Page          $page  Current page object of Zend_Pdf
     * @param  Mage_Sales_Model_Order $order Order object
     *
     * @return void
     */
    protected function _insertCustomerAddress(&$page, $order)
    {
        //Insert billing address
        $originY = $this->y;
        $billing = $this->_formatAddress($order->getBillingAddress()->format('pdf'));
        $this->_setFontBold($page, 8);
        $page->drawText(Mage::helper('firegento_pdf')->__('Billing address'), $this->margin['left'], $this->y, $this->encoding);
        $this->Ln(12);
        $this->_setFontRegular($page, 8);
        foreach ($billing as $line) {
            $page->drawText(trim(strip_tags($line)), $this->margin['left'], $this->y, $this->encoding);
            $this->Ln(11);
        }

        //Insert shipping address
        $this->y = $originY;
        $shipping = $this->_formatAddress($order->getShippingAddress()->format('pdf'));
        $this->_setFontBold($page, 8);
        $page->drawText(Mage::helper('firegento_pdf')->__('Shipping address'), $this->margin['left'] + 150, $this->y, $this->encoding);
        $this->Ln(12);
        $this->_setFontRegular($page, 8);
        foreach ($shipping as $line) {
            $page->drawText(trim(strip_tags($line)), $this->margin['left'] + 150, $this->y, $this->encoding);
            $this->Ln(11);
        }
    }
}

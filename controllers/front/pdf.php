<?php
/**
 * Download del PDF di una gift card attiva: con la chiave del link (email, area clienti).
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmgiftcardPdfModuleFrontController extends ModuleFrontController
{
    /** @var Dotmgiftcard */
    public $module;

    public $ssl = true;

    public function init()
    {
        parent::init();

        $card = new DotmGiftCardCard((int) Tools::getValue('id'));
        if (!Validate::isLoadedObject($card)
            || !hash_equals($card->getKey(), (string) Tools::getValue('key'))
            || $card->status !== DotmGiftCardCard::STATUS_ACTIVE) {
            Tools::redirect($this->context->link->getPageLink('pagenotfound'));
        }

        $pdf = (new DotmGiftCardPdf($this->module))->render($card);
        if ($pdf === '') {
            Tools::redirect($this->context->link->getPageLink('pagenotfound'));
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . DotmGiftCardPdf::getFileName($card) . '"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex');
        echo $pdf;
        exit;
    }
}

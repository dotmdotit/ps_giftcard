<?php
/**
 * Area clienti → Le mie gift card: carte acquistate e ricevute, con saldo, stato e PDF.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmgiftcardAccountModuleFrontController extends ModuleFrontController
{
    /** @var Dotmgiftcard */
    public $module;

    public $auth = true;

    public $ssl = true;

    public function setMedia()
    {
        parent::setMedia();
        $this->registerStylesheet('dotmgiftcard-front', 'modules/' . $this->module->name . '/views/css/front.css', ['media' => 'all', 'priority' => 200]);
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();
        $page['meta']['title'] = $this->module->t('account_title');
        $page['meta']['robots'] = 'noindex';

        return $page;
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = $this->addMyAccountToBreadcrumb();
        $breadcrumb['links'][] = [
            'title' => $this->module->t('account_title'),
            'url' => $this->context->link->getModuleLink($this->module->name, 'account', [], true),
        ];

        return $breadcrumb;
    }

    public function initContent()
    {
        parent::initContent();
        $customer = $this->context->customer;
        $bought = [];
        $received = [];
        foreach (DotmGiftCardCard::getForCustomer($customer) as $card) {
            $row = $this->module->presentCard($card);
            if ((int) $card->id_customer === (int) $customer->id) {
                $bought[] = $row;
            } else {
                $received[] = $row;
            }
        }

        $this->context->smarty->assign([
            'dgc' => [
                't' => $this->module->getStrings(),
                'bought' => $bought,
                'received' => $received,
                'buy_url' => $this->module->getBuyUrl(),
                'balance_url' => $this->context->link->getModuleLink($this->module->name, 'balance', [], true),
                'css_vars' => $this->module->getCssVars(),
            ],
        ]);
        $this->setTemplate('module:dotmgiftcard/views/templates/front/account.tpl');
    }
}

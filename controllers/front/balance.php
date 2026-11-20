<?php
/**
 * Verifica del saldo: chiunque abbia il codice vede importo residuo e scadenza.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmgiftcardBalanceModuleFrontController extends ModuleFrontController
{
    /** @var Dotmgiftcard */
    public $module;

    public $ssl = true;

    protected $dgcError = '';

    protected $dgcResult;

    protected $dgcCode = '';

    public function setMedia()
    {
        parent::setMedia();
        $this->registerStylesheet('dotmgiftcard-front', 'modules/' . $this->module->name . '/views/css/front.css', ['media' => 'all', 'priority' => 200]);
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();
        $page['meta']['title'] = $this->module->t('balance_title');
        $page['meta']['robots'] = 'noindex';

        return $page;
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = ['title' => $this->module->t('buy_title'), 'url' => $this->module->getBuyUrl()];
        $breadcrumb['links'][] = [
            'title' => $this->module->t('balance_title'),
            'url' => $this->context->link->getModuleLink($this->module->name, 'balance', [], true),
        ];

        return $breadcrumb;
    }

    public function postProcess()
    {
        if (!Tools::isSubmit('dgc_check')) {
            return;
        }
        $m = $this->module;
        $this->dgcCode = Tools::substr(strtoupper(trim(strip_tags((string) Tools::getValue('code')))), 0, 64);
        if ((string) Tools::getValue('website') !== '') {
            // campo trappola compilato: risposta generica
            $this->dgcError = $m->t('balance_not_found');

            return;
        }
        if ($m->isBalanceRateLimited()) {
            $this->dgcError = $m->t('balance_rate_limited');

            return;
        }
        $card = DotmGiftCardCard::getByCode($this->dgcCode);
        if (!$card || $card->status === DotmGiftCardCard::STATUS_PENDING) {
            $this->dgcError = $m->t('balance_not_found');

            return;
        }
        $data = $m->presentCard($card);
        $this->dgcResult = [
            'code' => $data['code'],
            'amount' => $data['amount'],
            'balance' => $data['balance'],
            'state' => $data['state'],
            'state_label' => $data['state_label'],
            'expire' => $data['expire'],
            'partial' => (bool) $card->partial_use,
        ];
    }

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign([
            'dgc' => [
                't' => $this->module->getStrings(),
                'error' => $this->dgcError,
                'result' => $this->dgcResult,
                'code' => $this->dgcCode,
                'action' => $this->context->link->getModuleLink($this->module->name, 'balance', [], true),
                'buy_url' => $this->module->getBuyUrl(),
                'css_vars' => $this->module->getCssVars(),
            ],
        ]);
        $this->setTemplate('module:dotmgiftcard/views/templates/front/balance.tpl');
    }
}

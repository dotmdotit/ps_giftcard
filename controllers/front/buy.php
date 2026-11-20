<?php
/**
 * Pagina di acquisto: modello, modalità di consegna, importo, dedica e messaggio, anteprima dal vivo.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmgiftcardBuyModuleFrontController extends ModuleFrontController
{
    /** @var Dotmgiftcard */
    public $module;

    public $ssl = true;

    protected $dgcErrors = [];

    protected $dgcValues = [];

    public function setMedia()
    {
        parent::setMedia();
        $path = 'modules/' . $this->module->name . '/views/';
        $this->registerStylesheet('dotmgiftcard-front', $path . 'css/front.css', ['media' => 'all', 'priority' => 200]);
        $this->registerJavascript('dotmgiftcard-buy', $path . 'js/buy.js', ['position' => 'bottom', 'priority' => 200]);
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();
        $page['meta']['title'] = $this->module->t('buy_meta_title');
        $page['meta']['description'] = $this->module->t('buy_meta_description');

        return $page;
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = ['title' => $this->module->t('buy_title'), 'url' => $this->module->getBuyUrl()];

        return $breadcrumb;
    }

    public function postProcess()
    {
        if (!Tools::isSubmit('dgc_add')) {
            return;
        }
        $m = $this->module;
        $v = $this->readValues();
        $this->dgcValues = $v;

        if (!hash_equals(Tools::getToken(false), (string) Tools::getValue('token'))) {
            $this->dgcErrors[] = $m->t('error_token');

            return;
        }

        $deliveries = $m->getEnabledDeliveries();
        if (!in_array($v['delivery'], $deliveries, true)) {
            $this->dgcErrors[] = $m->t('error_delivery');
        }
        $physical = $v['delivery'] === DotmGiftCardCard::DELIVERY_POST;
        $allowed = array_column(DotmGiftCardProduct::getList((int) $this->context->language->id, true, $physical), 'id_product');
        if (!in_array($v['id_product'], $allowed, true)) {
            $this->dgcErrors[] = $m->t('error_amount');
        }
        if (!in_array($v['id_template'], array_column(DotmGiftCardTemplate::getList((int) $this->context->language->id), 'id'), true)) {
            $this->dgcErrors[] = $m->t('error_template');
        }

        foreach (['sender_name' => 'error_sender', 'recipient_name' => 'error_recipient'] as $field => $error) {
            if ($v[$field] !== '' && !Validate::isGenericName($v[$field])) {
                $this->dgcErrors[] = $m->t('error_name_chars');
            } elseif ($v[$field] === '' && ($field === 'sender_name' || $v['delivery'] === DotmGiftCardCard::DELIVERY_EMAIL)) {
                $this->dgcErrors[] = $m->t($error);
            }
        }

        if ($v['delivery'] === DotmGiftCardCard::DELIVERY_EMAIL) {
            if (!Validate::isEmail($v['recipient_email'])) {
                $this->dgcErrors[] = $m->t('error_email');
            } elseif (strtolower($v['recipient_email']) !== strtolower($v['recipient_email_confirm'])) {
                $this->dgcErrors[] = $m->t('error_email_confirm');
            }
            $today = date('Y-m-d');
            $max = date('Y-m-d', strtotime('+1 year'));
            if ($v['send_date'] === '') {
                $v['send_date'] = $today;
            }
            if (!Validate::isDate($v['send_date']) || $v['send_date'] < $today || $v['send_date'] > $max) {
                $this->dgcErrors[] = $m->t('error_date');
            }
        } else {
            $v['recipient_email'] = '';
            $v['send_date'] = '';
        }

        if (Tools::strlen($v['message']) > Dotmgiftcard::MESSAGE_LENGTH) {
            $this->dgcErrors[] = $m->t('error_message', ['n' => Dotmgiftcard::MESSAGE_LENGTH]);
        } elseif ($v['message'] !== '' && !Validate::isCleanHtml($v['message'])) {
            $this->dgcErrors[] = $m->t('error_message_chars');
        }
        if ($v['quantity'] < 1 || $v['quantity'] > Dotmgiftcard::MAX_QUANTITY) {
            $this->dgcErrors[] = $m->t('error_quantity', ['n' => Dotmgiftcard::MAX_QUANTITY]);
        }

        if ($this->dgcErrors) {
            return;
        }

        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart)) {
            if (!$cart->add()) {
                $this->dgcErrors[] = $m->t('error_cart');

                return;
            }
            $this->context->cookie->id_cart = (int) $cart->id;
            $this->context->cookie->write();
        }

        $result = $m->getManager()->addToCart($cart, $v['id_product'], [
            'id_template' => $v['id_template'],
            'delivery' => $v['delivery'],
            'sender_name' => $v['sender_name'],
            'recipient_name' => $v['recipient_name'],
            'recipient_email' => $v['recipient_email'],
            'message' => $v['message'],
            'send_date' => $v['send_date'] ?: null,
        ], $v['quantity']);

        if ($result !== true) {
            $this->dgcErrors[] = $m->t($result);

            return;
        }

        Tools::redirect($this->context->link->getPageLink('cart', true, null, ['action' => 'show']));
    }

    protected function readValues()
    {
        $clean = function ($key, $max) {
            $value = trim(strip_tags((string) Tools::getValue($key, '')));
            $value = preg_replace('/[ \t]+/', ' ', $value);

            return Tools::substr($value, 0, $max);
        };
        $message = trim(strip_tags(str_replace("\r\n", "\n", (string) Tools::getValue('message', ''))));
        $message = preg_replace("/\n{3,}/", "\n\n", $message);

        return [
            'id_product' => (int) Tools::getValue('id_product'),
            'id_template' => (int) Tools::getValue('id_template'),
            'delivery' => (string) Tools::getValue('delivery'),
            'sender_name' => $clean('sender_name', 64),
            'recipient_name' => $clean('recipient_name', 64),
            'recipient_email' => Tools::strtolower($clean('recipient_email', 255)),
            'recipient_email_confirm' => Tools::strtolower($clean('recipient_email_confirm', 255)),
            'send_date' => $clean('send_date', 10),
            'message' => Tools::substr($message, 0, Dotmgiftcard::MESSAGE_LENGTH + 50),
            'quantity' => (int) Tools::getValue('quantity', 1),
        ];
    }

    public function initContent()
    {
        parent::initContent();

        $m = $this->module;
        $idLang = (int) $this->context->language->id;
        $templates = DotmGiftCardTemplate::getList($idLang);
        $digital = DotmGiftCardProduct::getList($idLang, true, false);
        $physical = DotmGiftCardProduct::getList($idLang, true, true);
        $deliveries = $m->getEnabledDeliveries();
        if (!$physical) {
            $deliveries = array_values(array_diff($deliveries, [DotmGiftCardCard::DELIVERY_POST]));
        }
        if (!$digital) {
            $deliveries = array_values(array_intersect($deliveries, [DotmGiftCardCard::DELIVERY_POST]));
        }

        $customer = $this->context->customer;
        $values = $this->dgcValues ?: [
            'id_product' => (int) Tools::getValue('id_product'),
            'id_template' => (int) Tools::getValue('id_template'),
            'delivery' => '',
            'sender_name' => $customer->isLogged() ? trim($customer->firstname . ' ' . $customer->lastname) : '',
            'recipient_name' => '',
            'recipient_email' => '',
            'recipient_email_confirm' => '',
            'send_date' => date('Y-m-d'),
            'message' => '',
            'quantity' => 1,
        ];

        // valori predefiniti coerenti: modalità, taglio (dal link della scheda prodotto o il più basso) e modello
        if (!in_array($values['delivery'], $deliveries, true)) {
            $isPhysicalProduct = in_array($values['id_product'], array_column($physical, 'id_product'), true);
            $values['delivery'] = $isPhysicalProduct && in_array(DotmGiftCardCard::DELIVERY_POST, $deliveries, true)
                ? DotmGiftCardCard::DELIVERY_POST
                : (isset($deliveries[0]) ? $deliveries[0] : '');
        }
        $pool = $values['delivery'] === DotmGiftCardCard::DELIVERY_POST ? $physical : $digital;
        if ($pool && !in_array($values['id_product'], array_column($pool, 'id_product'), true)) {
            $values['id_product'] = $pool[0]['id_product'];
        }
        if ($templates && !in_array($values['id_template'], array_column($templates, 'id'), true)) {
            $values['id_template'] = $templates[0]['id'];
        }

        $occasions = [];
        foreach ($templates as $tpl) {
            if ($tpl['occasion'] !== '' && !in_array($tpl['occasion'], $occasions, true)) {
                $occasions[] = $tpl['occasion'];
            }
        }

        $deliveryOptions = [];
        foreach ($deliveries as $delivery) {
            $deliveryOptions[] = [
                'value' => $delivery,
                'label' => $m->t('delivery_' . $delivery),
                'help' => $m->t('delivery_' . $delivery . '_help'),
            ];
        }

        $this->context->smarty->assign([
            'dgc' => [
                't' => $m->getStrings(),
                'errors' => $this->dgcErrors,
                'available' => $templates && $deliveries && ($digital || $physical),
                'templates' => $templates,
                'occasions' => count($occasions) > 1 ? $occasions : [],
                'digital' => $digital,
                'physical' => $physical,
                'deliveries' => $deliveryOptions,
                'values' => $values,
                'action' => $m->getBuyUrl(),
                'token' => Tools::getToken(false),
                'today' => date('Y-m-d'),
                'max_date' => date('Y-m-d', strtotime('+1 year')),
                'message_length' => Dotmgiftcard::MESSAGE_LENGTH,
                'max_quantity' => Dotmgiftcard::MAX_QUANTITY,
                'shop_name' => Configuration::get('PS_SHOP_NAME'),
                'balance_url' => $this->context->link->getModuleLink($m->name, 'balance', [], true),
                'css_vars' => $m->getCssVars(),
            ],
        ]);

        $this->setTemplate('module:dotmgiftcard/views/templates/front/buy.tpl');
    }
}

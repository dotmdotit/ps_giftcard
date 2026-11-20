<?php
/**
 * Ordini → Gift card: carte emesse, saldo, invii, utilizzi, azioni (reinvio, annullamento, saldo)
 * e creazione manuale di gift card senza ordine.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'dotmgiftcard/dotmgiftcard.php';

class AdminDotmGiftCardCardController extends ModuleAdminController
{
    /** @var Dotmgiftcard */
    public $module;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'dotmgiftcard_card';
        $this->className = 'DotmGiftCardCard';
        $this->identifier = 'id_dotmgiftcard_card';
        $this->lang = false;
        $this->allow_export = true;
        $this->_defaultOrderBy = 'date_add';
        $this->_defaultOrderWay = 'DESC';

        parent::__construct();

        $this->_select = 'o.`reference`, cr.`reduction_amount` AS `cr_amount`, cr.`quantity` AS `cr_quantity`, cr.`active` AS `cr_active`,
            a.`id_dotmgiftcard_card` AS `balance`';
        $this->_join = '
            LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_order` = a.`id_order`
            LEFT JOIN `' . _DB_PREFIX_ . 'cart_rule` cr ON cr.`id_cart_rule` = a.`id_cart_rule`';

        $this->addRowAction('view');

        $deliveries = [];
        $statuses = [];
        foreach (DotmGiftCardCard::getDeliveries() as $d) {
            $deliveries[$d] = $this->module->t('delivery_' . $d, [], $this->adminLang());
        }
        foreach (DotmGiftCardCard::getStatuses() as $s) {
            $statuses[$s] = $this->module->t('state_' . $s, [], $this->adminLang());
        }

        $this->fields_list = [
            'id_dotmgiftcard_card' => ['title' => 'ID', 'align' => 'center', 'class' => 'fixed-width-xs'],
            'code' => ['title' => 'Codice', 'callback' => 'renderCode'],
            'amount' => ['title' => 'Importo', 'callback' => 'renderMoney', 'align' => 'text-right', 'search' => false],
            'balance' => ['title' => 'Saldo', 'callback' => 'renderBalance', 'align' => 'text-right', 'search' => false, 'orderby' => false],
            'delivery' => ['title' => 'Consegna', 'type' => 'select', 'list' => $deliveries, 'filter_key' => 'a!delivery', 'callback' => 'renderDelivery'],
            'recipient_name' => ['title' => 'Destinatario'],
            'recipient_email' => ['title' => 'Email destinatario'],
            'send_date' => ['title' => 'Data invio', 'type' => 'date'],
            'sent_at' => ['title' => 'Inviata il', 'type' => 'datetime'],
            'status' => ['title' => 'Stato', 'type' => 'select', 'list' => $statuses, 'filter_key' => 'a!status', 'callback' => 'renderStatus'],
            'reference' => ['title' => 'Ordine', 'filter_key' => 'o!reference'],
            'date_add' => ['title' => 'Creata il', 'type' => 'datetime', 'filter_key' => 'a!date_add'],
        ];
    }

    protected function adminLang()
    {
        return (int) $this->context->language->id;
    }

    public function renderCode($value)
    {
        return $value !== '' ? '<code style="white-space:nowrap;">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</code>' : '—';
    }

    public function renderMoney($value, $row)
    {
        return htmlspecialchars($this->module->getManager()->formatAmount($value, (int) $row['id_currency']), ENT_QUOTES, 'UTF-8');
    }

    public function renderBalance($value, $row)
    {
        $active = $row['status'] === DotmGiftCardCard::STATUS_ACTIVE && (int) $row['cr_active'] && (int) $row['cr_quantity'] > 0
            && (!$row['date_expire'] || strtotime($row['date_expire']) >= time());

        return $active ? $this->renderMoney($row['cr_amount'], $row) : '—';
    }

    public function renderDelivery($value)
    {
        return htmlspecialchars($this->module->t('delivery_' . $value, [], $this->adminLang()), ENT_QUOTES, 'UTF-8');
    }

    public function renderStatus($value, $row)
    {
        $classes = [
            DotmGiftCardCard::STATUS_PENDING => 'warning',
            DotmGiftCardCard::STATUS_ACTIVE => 'success',
            DotmGiftCardCard::STATUS_CANCELLED => 'danger',
        ];
        $label = $this->module->t('state_' . $value, [], $this->adminLang());
        $extra = '';
        if ($value === DotmGiftCardCard::STATUS_ACTIVE && $row['delivery'] === DotmGiftCardCard::DELIVERY_EMAIL && !$row['sent_at']) {
            $extra = ' <span class="label label-default">da inviare</span>';
        }

        return '<span class="label label-' . (isset($classes[$value]) ? $classes[$value] : 'default') . '">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>' . $extra;
    }

    public function initPageHeaderToolbar()
    {
        if (empty($this->display)) {
            $this->page_header_toolbar_btn['new'] = [
                'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,
                'desc' => 'Nuova gift card',
                'icon' => 'process-icon-new',
            ];
        }
        $this->page_header_toolbar_btn['config'] = [
            'href' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->module->name]),
            'desc' => 'Impostazioni',
            'icon' => 'process-icon-cogs',
        ];
        parent::initPageHeaderToolbar();
    }

    public function initToolbar()
    {
        parent::initToolbar();
        unset($this->toolbar_btn['new']);
    }

    /* ------------------------------------------------------------------
     * Nuova gift card (senza ordine)
     * ------------------------------------------------------------------ */

    public function renderForm()
    {
        if (Validate::isLoadedObject($this->loadObject(true))) {
            // le carte esistenti si gestiscono dal dettaglio
            Tools::redirectAdmin(self::$currentIndex . '&' . $this->identifier . '=' . (int) Tools::getValue($this->identifier) . '&view' . $this->table . '&token=' . $this->token);
        }
        $idLang = $this->adminLang();
        $templates = [];
        foreach (DotmGiftCardTemplate::getList($idLang) as $tpl) {
            $templates[] = ['id' => $tpl['id'], 'name' => $tpl['name']];
        }
        $languages = [];
        foreach (Language::getLanguages(true) as $lang) {
            $languages[] = ['id' => (int) $lang['id_lang'], 'name' => $lang['name']];
        }

        $this->fields_form = [
            'legend' => ['title' => 'Nuova gift card', 'icon' => 'icon-gift'],
            'description' => 'Per omaggi, rimborsi o vendite in negozio. La carta viene attivata subito; se indichi un\'email viene inviata al destinatario (oggi o nella data scelta), altrimenti trovi codice e PDF nel dettaglio.',
            'input' => [
                ['type' => 'text', 'label' => 'Importo', 'name' => 'amount', 'required' => true, 'class' => 'fixed-width-md'],
                [
                    'type' => 'select', 'label' => 'Valuta', 'name' => 'id_currency',
                    'options' => ['query' => Currency::getCurrencies(false, true), 'id' => 'id_currency', 'name' => 'name'],
                ],
                ['type' => 'text', 'label' => 'Validità (mesi)', 'name' => 'validity_months', 'class' => 'fixed-width-sm', 'desc' => '0 = senza scadenza.'],
                [
                    'type' => 'switch', 'label' => 'Uso parziale', 'name' => 'partial_use', 'is_bool' => true,
                    'values' => [['id' => 'pu_on', 'value' => 1, 'label' => 'Sì'], ['id' => 'pu_off', 'value' => 0, 'label' => 'No']],
                ],
                [
                    'type' => 'select', 'label' => 'Modello', 'name' => 'id_template',
                    'options' => ['query' => $templates, 'id' => 'id', 'name' => 'name'],
                ],
                [
                    'type' => 'select', 'label' => 'Lingua di email e PDF', 'name' => 'id_lang',
                    'options' => ['query' => $languages, 'id' => 'id', 'name' => 'name'],
                ],
                ['type' => 'text', 'label' => 'Da', 'name' => 'sender_name', 'required' => true, 'desc' => 'Ad esempio il nome del negozio.'],
                ['type' => 'text', 'label' => 'Per', 'name' => 'recipient_name'],
                ['type' => 'text', 'label' => 'Email destinatario', 'name' => 'recipient_email', 'desc' => 'Facoltativa. Se vuota la carta non viene inviata.'],
                ['type' => 'date', 'label' => 'Data di invio', 'name' => 'send_date', 'desc' => 'Vuota = subito.'],
                ['type' => 'textarea', 'label' => 'Messaggio', 'name' => 'message', 'rows' => 4, 'desc' => 'Massimo ' . Dotmgiftcard::MESSAGE_LENGTH . ' caratteri.'],
            ],
            'submit' => ['title' => 'Crea e attiva'],
        ];
        $this->fields_value = [
            'amount' => '',
            'id_currency' => (int) Configuration::get('PS_CURRENCY_DEFAULT'),
            'validity_months' => 12,
            'partial_use' => 1,
            'id_template' => DotmGiftCardTemplate::getDefaultId(),
            'id_lang' => (int) Configuration::get('PS_LANG_DEFAULT'),
            'sender_name' => (string) Configuration::get('PS_SHOP_NAME'),
            'recipient_name' => '',
            'recipient_email' => '',
            'send_date' => '',
            'message' => '',
        ];
        foreach (array_keys($this->fields_value) as $key) {
            if (Tools::getIsset($key)) {
                $this->fields_value[$key] = Tools::getValue($key);
            }
        }

        return parent::renderForm();
    }

    public function processSave()
    {
        if ((int) Tools::getValue($this->identifier)) {
            return false;
        }
        $data = [
            'amount' => (float) str_replace(',', '.', (string) Tools::getValue('amount')),
            'id_currency' => (int) Tools::getValue('id_currency'),
            'id_lang' => (int) Tools::getValue('id_lang'),
            'validity_months' => (int) Tools::getValue('validity_months'),
            'partial_use' => (bool) Tools::getValue('partial_use'),
            'id_template' => (int) Tools::getValue('id_template'),
            'sender_name' => trim(strip_tags((string) Tools::getValue('sender_name'))),
            'recipient_name' => trim(strip_tags((string) Tools::getValue('recipient_name'))),
            'recipient_email' => strtolower(trim((string) Tools::getValue('recipient_email'))),
            'send_date' => trim((string) Tools::getValue('send_date')),
            'message' => trim(strip_tags((string) Tools::getValue('message'))),
        ];
        if ($data['amount'] <= 0) {
            $this->errors[] = 'L\'importo deve essere maggiore di zero.';
        }
        if (!Validate::isLoadedObject(new Currency($data['id_currency']))) {
            $this->errors[] = 'Valuta non valida.';
        }
        if ($data['sender_name'] === '' || !Validate::isGenericName($data['sender_name'])
            || ($data['recipient_name'] !== '' && !Validate::isGenericName($data['recipient_name']))) {
            $this->errors[] = 'Controlla i nomi (il mittente è obbligatorio; non sono ammessi i caratteri < > = { } # ;).';
        }
        if ($data['recipient_email'] !== '' && !Validate::isEmail($data['recipient_email'])) {
            $this->errors[] = 'Email del destinatario non valida.';
        }
        if ($data['send_date'] !== '' && (!Validate::isDate($data['send_date']) || $data['send_date'] < date('Y-m-d'))) {
            $this->errors[] = 'La data di invio non può essere nel passato.';
        }
        if (Tools::strlen($data['message']) > Dotmgiftcard::MESSAGE_LENGTH) {
            $this->errors[] = 'Il messaggio supera i ' . Dotmgiftcard::MESSAGE_LENGTH . ' caratteri.';
        }
        if ($this->errors) {
            $this->display = 'add';

            return false;
        }

        $card = $this->module->getManager()->createManual($data, (int) $this->context->employee->id);
        if (!$card) {
            $this->errors[] = 'Impossibile creare la gift card.';
            $this->display = 'add';

            return false;
        }
        Tools::redirectAdmin(self::$currentIndex . '&' . $this->identifier . '=' . (int) $card->id . '&view' . $this->table . '&conf=3&token=' . $this->token);

        return true;
    }

    /* ------------------------------------------------------------------
     * Dettaglio e azioni
     * ------------------------------------------------------------------ */

    public function postProcess()
    {
        $action = (string) Tools::getValue('dgc_action');
        if ($action !== '') {
            $this->processCardAction($action);
        }

        return parent::postProcess();
    }

    protected function processCardAction($action)
    {
        $card = new DotmGiftCardCard((int) Tools::getValue($this->identifier));
        if (!Validate::isLoadedObject($card)) {
            $this->errors[] = 'Gift card non trovata.';

            return;
        }
        $manager = $this->module->getManager();
        $idEmployee = (int) $this->context->employee->id;

        switch ($action) {
            case 'pdf':
                if ($card->status !== DotmGiftCardCard::STATUS_ACTIVE) {
                    $this->errors[] = 'Il PDF è disponibile solo per le gift card attive.';

                    return;
                }
                $pdf = (new DotmGiftCardPdf($this->module))->render($card);
                if ($pdf === '') {
                    $this->errors[] = 'TCPDF non disponibile.';

                    return;
                }
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . DotmGiftCardPdf::getFileName($card) . '"');
                echo $pdf;
                exit;

            case 'send':
                if ($card->status !== DotmGiftCardCard::STATUS_ACTIVE || $card->delivery === DotmGiftCardCard::DELIVERY_POST) {
                    $this->errors[] = 'Questa gift card non si invia via email.';
                } elseif ($manager->deliver($card, true)) {
                    $this->confirmations[] = 'Gift card inviata.';
                } else {
                    $this->errors[] = 'Invio non riuscito: controlla l\'email e la configurazione della posta.';
                }
                break;

            case 'recipient':
                $email = strtolower(trim((string) Tools::getValue('recipient_email')));
                $date = trim((string) Tools::getValue('send_date'));
                if ($email !== '' && !Validate::isEmail($email)) {
                    $this->errors[] = 'Email non valida.';
                } elseif ($date !== '' && !Validate::isDate($date)) {
                    $this->errors[] = 'Data non valida.';
                } else {
                    $card->recipient_email = $email;
                    if ($email !== '' && $card->delivery !== DotmGiftCardCard::DELIVERY_EMAIL && $card->id_order == 0) {
                        $card->delivery = DotmGiftCardCard::DELIVERY_EMAIL;
                    }
                    $card->send_date = $date !== '' ? $date : null;
                    $card->update();
                    $card->log('edited', trim($email . ' ' . $date), $idEmployee);
                    $this->confirmations[] = 'Dati di invio aggiornati.';
                }
                break;

            case 'cancel':
                $manager->cancel($card, $idEmployee, 'Back office');
                $this->confirmations[] = 'Gift card annullata: il codice non è più utilizzabile.';
                break;

            case 'activate':
                if ($manager->activate($card, $idEmployee)) {
                    $this->confirmations[] = 'Gift card attivata.';
                } else {
                    $this->errors[] = 'Attivazione non riuscita.';
                }
                break;

            case 'balance':
                $cartRule = $card->getCartRule();
                $value = round((float) str_replace(',', '.', (string) Tools::getValue('balance')), 2);
                if (!$cartRule || $card->status !== DotmGiftCardCard::STATUS_ACTIVE) {
                    $this->errors[] = 'Il saldo si modifica solo sulle gift card attive.';
                } elseif ($value < 0) {
                    $this->errors[] = 'Il saldo non può essere negativo.';
                } else {
                    $before = $card->getBalance();
                    if ($value > 0) {
                        $cartRule->reduction_amount = $value;
                        $cartRule->quantity = 1;
                    } else {
                        $cartRule->quantity = 0;
                    }
                    $cartRule->update();
                    $card->log('balance', $manager->formatAmount($before, (int) $card->id_currency) . ' → ' . $manager->formatAmount($value, (int) $card->id_currency), $idEmployee);
                    $this->confirmations[] = 'Saldo aggiornato.';
                }
                break;

            case 'expire':
                $date = trim((string) Tools::getValue('date_expire'));
                $cartRule = $card->getCartRule();
                if ($date !== '' && !Validate::isDate($date)) {
                    $this->errors[] = 'Data non valida.';
                } elseif ($cartRule) {
                    $card->date_expire = $date !== '' ? $date . ' 23:59:59' : null;
                    $card->update();
                    $cartRule->date_to = $card->date_expire ?: '2099-12-31 23:59:59';
                    $cartRule->update();
                    $card->log('expire', $date !== '' ? $date : 'senza scadenza', $idEmployee);
                    $this->confirmations[] = 'Scadenza aggiornata.';
                }
                break;
        }
    }

    public function renderView()
    {
        /** @var DotmGiftCardCard $card */
        $card = $this->loadObject();
        if (!Validate::isLoadedObject($card)) {
            return '';
        }
        $card = new DotmGiftCardCard((int) $card->id);
        $manager = $this->module->getManager();
        $data = $this->module->presentCard($card, $this->adminLang());
        $link = $this->context->link;

        $order = new Order((int) $card->id_order);
        $customer = new Customer((int) $card->id_customer);
        $orderLink = function ($idOrder) use ($link) {
            return $link->getAdminLink('AdminOrders', true, ['orderId' => (int) $idOrder, 'route' => 'admin_orders_view'], ['id_order' => (int) $idOrder, 'vieworder' => 1]);
        };

        $usages = [];
        foreach ($card->getUsages() as $u) {
            $usages[] = [
                'date' => Tools::displayDate($u['date_add'], true),
                'reference' => $u['reference'] ?: '#' . (int) $u['id_order'],
                'link' => $orderLink($u['id_order']),
                'amount' => $manager->formatAmount($u['amount'], (int) $card->id_currency),
                'restored' => (bool) $u['restored'],
            ];
        }
        $actions = [
            'created' => 'Creata', 'activated' => 'Attivata', 'sent' => 'Inviata', 'send_failed' => 'Invio non riuscito',
            'used' => 'Usata', 'restored' => 'Riaccreditata', 'cancelled' => 'Annullata',
            'cancel_skipped' => 'Non annullata (già usata)', 'edited' => 'Dati di invio modificati',
            'balance' => 'Saldo modificato', 'expire' => 'Scadenza modificata',
        ];
        $log = [];
        foreach ($card->getLog() as $row) {
            $log[] = [
                'date' => Tools::displayDate($row['date_add'], true),
                'action' => isset($actions[$row['action']]) ? $actions[$row['action']] : $row['action'],
                'details' => $row['details'],
                'employee' => trim($row['firstname'] . ' ' . $row['lastname']),
            ];
        }
        $cartRule = $card->getCartRule();
        $self = self::$currentIndex . '&' . $this->identifier . '=' . (int) $card->id . '&view' . $this->table . '&token=' . $this->token;

        $this->context->smarty->assign([
            'dgc_card' => $data + [
                'status' => $card->status,
                'partial_use' => (bool) $card->partial_use,
                'balance_raw' => $card->getBalance(),
                'expire_raw' => $card->date_expire ? substr($card->date_expire, 0, 10) : '',
                'send_date_raw' => $card->send_date ?: '',
                'order_reference' => Validate::isLoadedObject($order) ? $order->reference : '',
                'order_link' => Validate::isLoadedObject($order) ? $orderLink($order->id) : '',
                'customer' => Validate::isLoadedObject($customer) ? trim($customer->firstname . ' ' . $customer->lastname) . ' <' . $customer->email . '>' : '',
                'cart_rule_link' => $cartRule ? $link->getAdminLink('AdminCartRules', true, [], ['id_cart_rule' => (int) $cartRule->id, 'updatecart_rule' => 1]) : '',
                'currency_sign' => (new Currency((int) $card->id_currency))->sign,
            ],
            'dgc_usages' => $usages,
            'dgc_log' => $log,
            'dgc_self' => $self,
            'dgc_back' => self::$currentIndex . '&token=' . $this->token,
        ]);

        return $this->context->smarty->fetch($this->module->getLocalPath() . 'views/templates/admin/card-view.tpl');
    }
}

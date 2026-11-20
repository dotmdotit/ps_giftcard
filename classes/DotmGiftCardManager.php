<?php
/**
 * Ciclo di vita delle gift card: aggiunta al carrello, emissione con l'ordine, attivazione al pagamento,
 * consegna, utilizzo (saldo residuo sullo stesso codice), annullamento e riaccredito.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmGiftCardManager
{
    /** caratteri dei codici: niente 0/O, 1/I/L per evitare errori di lettura */
    const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** @var Dotmgiftcard */
    protected $module;

    public function __construct(Dotmgiftcard $module)
    {
        $this->module = $module;
    }

    /* ------------------------------------------------------------------
     * Carrello
     * ------------------------------------------------------------------ */

    /**
     * Aggiunge al carrello una gift card personalizzata (una riga di carrello per personalizzazione).
     *
     * @param array $data id_template, delivery, sender_name, recipient_name, recipient_email, message, send_date (Y-m-d|null)
     *
     * @return true|string true oppure chiave del messaggio di errore
     */
    public function addToCart(Cart $cart, $idProduct, array $data, $quantity)
    {
        $idField = DotmGiftCardProduct::getCustomizationFieldId($idProduct);
        if (!$idField) {
            $idField = DotmGiftCardProduct::ensureCustomizationField($idProduct, $this->module->getFieldLabels());
        }
        if (!$idField) {
            return 'error_cart';
        }

        $db = Db::getInstance();
        if (!$db->insert('customization', [
            'id_product_attribute' => 0,
            'id_address_delivery' => (int) $cart->id_address_delivery,
            'id_cart' => (int) $cart->id,
            'id_product' => (int) $idProduct,
            'quantity' => 0,
            'in_cart' => 0,
        ])) {
            return 'error_cart';
        }
        $idCustomization = (int) $db->Insert_ID();

        $summary = $this->module->buildSummary($data, (int) $cart->id_lang);
        $db->insert('customized_data', [
            'id_customization' => $idCustomization,
            'type' => (int) Product::CUSTOMIZE_TEXTFIELD,
            'index' => $idField,
            'value' => pSQL($summary, true),
            'id_module' => (int) $this->module->id,
            'price' => 0,
            'weight' => 0,
        ]);
        $row = [
            'id_customization' => $idCustomization,
            'id_cart' => (int) $cart->id,
            'id_product' => (int) $idProduct,
            'id_template' => (int) $data['id_template'],
            'delivery' => pSQL($data['delivery']),
            'sender_name' => pSQL($data['sender_name']),
            'recipient_name' => pSQL($data['recipient_name']),
            'recipient_email' => pSQL($data['recipient_email']),
            'message' => pSQL($data['message']),
            'date_add' => date('Y-m-d H:i:s'),
        ];
        if ($data['send_date']) {
            // senza data la colonna resta NULL (niente null_values: trasformerebbe in NULL anche le stringhe vuote)
            $row['send_date'] = pSQL($data['send_date']);
        }
        $saved = $db->insert('dotmgiftcard_cart', $row);

        $result = $saved ? $cart->updateQty((int) $quantity, (int) $idProduct, 0, $idCustomization) : false;
        if ($result !== true && (int) $result !== 1) {
            $db->delete('customized_data', 'id_customization = ' . $idCustomization);
            $db->delete('customization', 'id_customization = ' . $idCustomization);
            $db->delete('dotmgiftcard_cart', 'id_customization = ' . $idCustomization);

            return 'error_cart';
        }

        return true;
    }

    /**
     * @return array|null personalizzazione salvata per una riga del carrello
     */
    public static function getCartPersonalization($idCustomization)
    {
        if (!(int) $idCustomization) {
            return null;
        }
        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'dotmgiftcard_cart` WHERE `id_customization` = ' . (int) $idCustomization
        );

        return $row ?: null;
    }

    /**
     * Righe del carrello che sono gift card.
     */
    public static function cartHasGiftCards(Cart $cart)
    {
        foreach ($cart->getProducts() as $product) {
            if (DotmGiftCardProduct::isGiftCard((int) $product['id_product'])) {
                return true;
            }
        }

        return false;
    }

    /* ------------------------------------------------------------------
     * Ordine: emissione, utilizzo, pagamento
     * ------------------------------------------------------------------ */

    /**
     * Crea le gift card (in attesa di pagamento) per le righe gift card dell'ordine.
     */
    public function createCardsForOrder(Order $order)
    {
        $customer = new Customer((int) $order->id_customer);
        foreach ($order->getProducts() as $line) {
            $idProduct = (int) $line['product_id'];
            $settings = DotmGiftCardProduct::getByProduct($idProduct);
            if (!$settings) {
                continue;
            }
            $idDetail = (int) $line['id_order_detail'];
            $missing = (int) $line['product_quantity'] - DotmGiftCardCard::countByOrderDetail($idDetail);
            if ($missing < 1) {
                continue;
            }
            $perso = self::getCartPersonalization(isset($line['id_customization']) ? (int) $line['id_customization'] : 0);
            $physical = !(new Product($idProduct))->is_virtual;

            for ($i = 0; $i < $missing; ++$i) {
                $card = new DotmGiftCardCard();
                $card->id_shop = (int) $order->id_shop;
                $card->id_order = (int) $order->id;
                $card->id_order_detail = $idDetail;
                $card->id_customer = (int) $order->id_customer;
                $card->id_product = $idProduct;
                $card->id_currency = (int) $order->id_currency;
                $card->id_lang = (int) $order->id_lang;
                $card->amount = round((float) $line['unit_price_tax_incl'], 2);
                $card->partial_use = (int) $settings->partial_use;
                $card->validity_months = (int) $settings->validity_months;
                $card->status = DotmGiftCardCard::STATUS_PENDING;
                if ($perso) {
                    $card->id_template = (int) $perso['id_template'];
                    $card->delivery = $perso['delivery'];
                    $card->sender_name = $perso['sender_name'];
                    $card->recipient_name = $perso['recipient_name'];
                    $card->recipient_email = $perso['recipient_email'];
                    $card->message = $perso['message'];
                    $card->send_date = $perso['send_date'] ?: null;
                } else {
                    // aggiunta al carrello senza personalizzazione (es. da un altro modulo): PDF all'acquirente
                    $card->id_template = DotmGiftCardTemplate::getDefaultId();
                    $card->delivery = DotmGiftCardCard::DELIVERY_PRINT;
                    $card->sender_name = trim($customer->firstname . ' ' . $customer->lastname);
                    $card->send_date = null;
                }
                if ($physical) {
                    $card->delivery = DotmGiftCardCard::DELIVERY_POST;
                }
                $card->send_date = $card->delivery === DotmGiftCardCard::DELIVERY_EMAIL ? $card->send_date : null;
                $card->sent_at = null;
                $card->date_expire = null;
                if ($card->add()) {
                    $card->log('created', 'Ordine ' . $order->reference);
                }
            }
        }
    }

    /**
     * Registra l'uso delle gift card come buono nell'ordine e lascia il saldo residuo sullo stesso codice.
     * PrestaShop ha già scalato la quantità del buono (passata a 0): qui la si riporta a 1 con il nuovo importo.
     */
    public function recordUsages(Order $order, $orderStateId)
    {
        if ((int) $orderStateId === (int) Configuration::get('PS_OS_ERROR') || (int) $orderStateId === (int) Configuration::get('PS_OS_CANCELED')) {
            return;
        }
        foreach ($order->getCartRules() as $row) {
            $card = DotmGiftCardCard::getByCartRule((int) $row['id_cart_rule']);
            if (!$card) {
                continue;
            }
            $already = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'dotmgiftcard_usage`
                WHERE `id_dotmgiftcard_card` = ' . (int) $card->id . ' AND `id_order` = ' . (int) $order->id
            );
            if ($already) {
                continue;
            }
            $used = (float) $row['value'];
            if ((int) $order->id_currency !== (int) $card->id_currency) {
                $used = Tools::convertPriceFull($used, new Currency((int) $order->id_currency), new Currency((int) $card->id_currency));
            }
            $used = round($used, 2);

            $cartRule = new CartRule((int) $card->id_cart_rule);
            $before = (float) $cartRule->reduction_amount;
            $remaining = round($before - $used, 2);

            Db::getInstance()->insert('dotmgiftcard_usage', [
                'id_dotmgiftcard_card' => (int) $card->id,
                'id_order' => (int) $order->id,
                'amount' => $used,
                'restored' => 0,
                'date_add' => date('Y-m-d H:i:s'),
            ]);
            $card->log('used', $order->reference . ': ' . $this->formatAmount($used, (int) $card->id_currency));

            if ($card->partial_use && $remaining >= 0.01) {
                $cartRule->reduction_amount = $remaining;
                $cartRule->quantity = 1;
            } else {
                $cartRule->quantity = 0;
            }
            $cartRule->update();
        }
    }

    /**
     * Registra le gift card usate come pagamento invece che come sconto: il totale dell'ordine
     * resta pieno (l'IVA si applica sul prezzo intero) e la gift card compare tra i pagamenti.
     */
    public function moveUsagesToPayment(Order $order)
    {
        $label = $this->module->getPaymentLabel((int) $order->id_lang);
        $moved = false;
        foreach ($order->getCartRules() as $row) {
            if (!DotmGiftCardCard::getByCartRule((int) $row['id_cart_rule'])) {
                continue;
            }
            $ti = (float) $row['value'];
            $te = (float) $row['value_tax_excl'];
            $order->total_discounts = max(0, $order->total_discounts - $ti);
            $order->total_discounts_tax_incl = max(0, $order->total_discounts_tax_incl - $ti);
            $order->total_discounts_tax_excl = max(0, $order->total_discounts_tax_excl - $te);
            $order->total_paid += $ti;
            $order->total_paid_tax_incl += $ti;
            $order->total_paid_tax_excl += $te;
            Db::getInstance()->delete('order_cart_rule', 'id_order_cart_rule = ' . (int) $row['id_order_cart_rule']);
            $order->update();
            $order->addOrderPayment($ti, $label);
            $moved = true;
        }
        if ($moved) {
            $order->updateOrderDetailTax();
        }
    }

    /**
     * Pagamento ricevuto: crea i buoni e consegna le gift card dell'ordine.
     */
    public function activateOrder(Order $order)
    {
        foreach (DotmGiftCardCard::getByOrder((int) $order->id) as $card) {
            if ($card->status === DotmGiftCardCard::STATUS_PENDING) {
                $this->activate($card);
            }
        }
    }

    /**
     * Ordine annullato o rimborsato: annulla le gift card acquistate non ancora usate
     * e (se previsto) riaccredita le gift card usate per pagarlo.
     *
     * @return array codici delle carte acquistate già usate, che non è stato possibile annullare
     */
    public function cancelOrder(Order $order)
    {
        $notCancelled = [];
        foreach (DotmGiftCardCard::getByOrder((int) $order->id) as $card) {
            if ($card->status === DotmGiftCardCard::STATUS_CANCELLED) {
                continue;
            }
            if ($card->hasUsages()) {
                $notCancelled[] = $card->code;
                $card->log('cancel_skipped', 'Ordine ' . $order->reference . ' annullato, carta già usata');
                continue;
            }
            $this->cancel($card, 0, 'Ordine ' . $order->reference . ' annullato');
        }

        if (Configuration::get(Dotmgiftcard::CFG_RESTORE_ON_CANCEL)) {
            $this->restoreOrderUsages($order);
        }

        return $notCancelled;
    }

    public function restoreOrderUsages(Order $order)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'dotmgiftcard_usage` WHERE `id_order` = ' . (int) $order->id . ' AND `restored` = 0'
        );
        foreach (is_array($rows) ? $rows : [] as $row) {
            $card = new DotmGiftCardCard((int) $row['id_dotmgiftcard_card']);
            $cartRule = $card->getCartRule();
            if (!$cartRule) {
                continue;
            }
            $amount = (float) $row['amount'];
            if ((int) $cartRule->quantity < 1) {
                $cartRule->reduction_amount = $card->partial_use ? $amount : (float) $card->amount;
                $cartRule->quantity = 1;
            } elseif ($card->partial_use) {
                $cartRule->reduction_amount = round((float) $cartRule->reduction_amount + $amount, 2);
            }
            if ($cartRule->update()) {
                Db::getInstance()->update('dotmgiftcard_usage', ['restored' => 1], 'id_dotmgiftcard_usage = ' . (int) $row['id_dotmgiftcard_usage']);
                $card->log('restored', $order->reference . ': ' . $this->formatAmount($amount, (int) $card->id_currency));
            }
        }
    }

    /* ------------------------------------------------------------------
     * Singola carta
     * ------------------------------------------------------------------ */

    /**
     * Crea il buono (o lo riattiva) e avvia la consegna.
     */
    public function activate(DotmGiftCardCard $card, $idEmployee = 0)
    {
        $cartRule = $card->getCartRule();
        if ($cartRule) {
            $cartRule->active = 1;
            $cartRule->update();
        } else {
            if (!$card->code) {
                $card->code = $this->generateCode();
            }
            $card->date_expire = $card->validity_months > 0
                ? date('Y-m-d 23:59:59', strtotime('+' . (int) $card->validity_months . ' months'))
                : null;
            $cartRule = $this->createCartRule($card);
            if (!$cartRule) {
                PrestaShopLogger::addLog('dotmgiftcard: buono non creato per la carta #' . (int) $card->id, 3, null, 'Order', (int) $card->id_order);

                return false;
            }
            $card->id_cart_rule = (int) $cartRule->id;
        }
        $card->status = DotmGiftCardCard::STATUS_ACTIVE;
        $card->update();
        $card->log('activated', $card->code, $idEmployee);

        if (!$card->sent_at) {
            $this->deliver($card);
        }

        return true;
    }

    public function cancel(DotmGiftCardCard $card, $idEmployee = 0, $details = '')
    {
        $cartRule = $card->getCartRule();
        if ($cartRule) {
            $cartRule->active = 0;
            $cartRule->update();
        }
        $card->status = DotmGiftCardCard::STATUS_CANCELLED;
        $card->update();
        $card->log('cancelled', $details, $idEmployee);

        return true;
    }

    /**
     * Consegna secondo la modalità scelta. Le carte via email con data futura restano in attesa del cron.
     */
    public function deliver(DotmGiftCardCard $card, $force = false)
    {
        if ($card->status !== DotmGiftCardCard::STATUS_ACTIVE) {
            return false;
        }
        $mailer = new DotmGiftCardMailer($this->module);
        switch ($card->delivery) {
            case DotmGiftCardCard::DELIVERY_EMAIL:
                if (!$force && $card->send_date && $card->send_date > date('Y-m-d')) {
                    return false;
                }
                $ok = $mailer->sendToRecipient($card);
                if ($ok) {
                    $mailer->notifyBuyerSent($card);
                }
                break;
            case DotmGiftCardCard::DELIVERY_PRINT:
                $ok = $mailer->sendToBuyer($card);
                break;
            default:
                // carta fisica: la spedisce il negozio, nessuna email
                return false;
        }
        if ($ok) {
            $card->sent_at = date('Y-m-d H:i:s');
            $card->update();
            $card->log('sent', $card->delivery === DotmGiftCardCard::DELIVERY_EMAIL ? $card->recipient_email : 'acquirente');
        } else {
            $card->log('send_failed', $card->delivery);
        }

        return $ok;
    }

    /**
     * Invia le gift card programmate per oggi o prima.
     *
     * @return int carte inviate
     */
    public function sendScheduled()
    {
        $sent = 0;
        foreach (DotmGiftCardCard::getDueForSending() as $card) {
            if ($this->deliver($card)) {
                ++$sent;
            }
        }
        Configuration::updateGlobalValue(Dotmgiftcard::CFG_LAST_RUN, date('Y-m-d H:i:s'));

        return $sent;
    }

    /**
     * Gift card creata dal back office, senza ordine (omaggi, rimborsi, vendite in negozio).
     *
     * @return DotmGiftCardCard|false
     */
    public function createManual(array $data, $idEmployee)
    {
        $card = new DotmGiftCardCard();
        $card->id_shop = (int) Context::getContext()->shop->id;
        $card->id_currency = (int) $data['id_currency'];
        $card->id_lang = (int) $data['id_lang'];
        $card->amount = round((float) $data['amount'], 2);
        $card->partial_use = !empty($data['partial_use']) ? 1 : 0;
        $card->validity_months = max(0, (int) $data['validity_months']);
        $card->id_template = (int) $data['id_template'] ?: DotmGiftCardTemplate::getDefaultId();
        $card->delivery = $data['recipient_email'] ? DotmGiftCardCard::DELIVERY_EMAIL : DotmGiftCardCard::DELIVERY_POST;
        $card->sender_name = $data['sender_name'];
        $card->recipient_name = $data['recipient_name'];
        $card->recipient_email = $data['recipient_email'];
        $card->message = $data['message'];
        $card->send_date = $card->delivery === DotmGiftCardCard::DELIVERY_EMAIL && $data['send_date'] ? $data['send_date'] : null;
        $card->sent_at = null;
        $card->date_expire = null;
        $card->status = DotmGiftCardCard::STATUS_PENDING;
        if (!$card->add()) {
            return false;
        }
        $card->log('created', 'Back office', $idEmployee);
        $this->activate($card, $idEmployee);

        return $card;
    }

    /* ------------------------------------------------------------------
     * Buono PrestaShop
     * ------------------------------------------------------------------ */

    /**
     * @return CartRule|false
     */
    protected function createCartRule(DotmGiftCardCard $card)
    {
        $cartRule = new CartRule();
        $names = [];
        foreach (Language::getLanguages(false) as $lang) {
            $names[(int) $lang['id_lang']] = Tools::substr($this->module->t('cart_rule_name', [], (int) $lang['id_lang']), 0, 254);
        }
        $cartRule->name = $names;
        $cartRule->description = 'dotmgiftcard #' . (int) $card->id;
        $cartRule->code = $card->code;
        $cartRule->id_customer = 0;
        $cartRule->date_from = date('Y-m-d H:i:s', time() - 60);
        $cartRule->date_to = $card->date_expire ?: '2099-12-31 23:59:59';
        $cartRule->quantity = 1;
        $cartRule->quantity_per_user = 9999;
        $cartRule->priority = 1;
        // il saldo residuo lo gestisce il modulo sullo stesso codice (PrestaShop ne creerebbe uno nuovo)
        $cartRule->partial_use = 0;
        $cartRule->minimum_amount = 0;
        $cartRule->minimum_amount_tax = 1;
        $cartRule->minimum_amount_currency = (int) $card->id_currency;
        $cartRule->minimum_amount_shipping = 0;
        $cartRule->free_shipping = 0;
        $cartRule->reduction_percent = 0;
        $cartRule->reduction_amount = round((float) $card->amount, 2);
        $cartRule->reduction_tax = 1;
        $cartRule->reduction_currency = (int) $card->id_currency;
        $cartRule->reduction_product = 0;
        $cartRule->highlight = 0;
        $cartRule->cart_rule_restriction = 0;
        $cartRule->active = 1;

        return $cartRule->add() ? $cartRule : false;
    }

    /**
     * Codice univoco: prefisso + gruppi di 4 caratteri (es. GC-7KQ9-M2XA-P4TD).
     */
    public function generateCode()
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) Configuration::get(Dotmgiftcard::CFG_PREFIX)));
        $length = max(8, min(20, (int) Configuration::get(Dotmgiftcard::CFG_CODE_LENGTH) ?: 12));
        $alphabet = self::CODE_ALPHABET;
        $max = strlen($alphabet) - 1;

        for ($attempt = 0; $attempt < 20; ++$attempt) {
            $raw = '';
            for ($i = 0; $i < $length; ++$i) {
                $raw .= $alphabet[random_int(0, $max)];
            }
            $code = ($prefix !== '' ? rtrim($prefix, '-') . '-' : '') . implode('-', str_split($raw, 4));
            if (!CartRule::getIdByCode($code)) {
                return $code;
            }
        }

        return $prefix . strtoupper(Tools::passwdGen(24, 'ALPHANUMERIC'));
    }

    public function formatAmount($amount, $idCurrency)
    {
        return self::formatPrice($amount, new Currency((int) $idCurrency));
    }

    /**
     * Prezzo formattato secondo la lingua corrente; senza il container di Symfony (CLI, alcuni cron)
     * ripiega su un formato semplice.
     */
    public static function formatPrice($amount, Currency $currency)
    {
        try {
            return Tools::getContextLocale(Context::getContext())->formatPrice((float) $amount, $currency->iso_code);
        } catch (Exception $e) {
            return number_format((float) $amount, 2, ',', '.') . ' ' . ($currency->sign ?: $currency->iso_code);
        }
    }
}

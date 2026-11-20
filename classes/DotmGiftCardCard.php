<?php
/**
 * Gift card emessa: una per ogni pezzo acquistato (o creata a mano dal back office).
 * Il valore spendibile è un buono sconto PrestaShop (CartRule) con lo stesso codice;
 * il saldo residuo resta sullo stesso codice.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmGiftCardCard extends ObjectModel
{
    const STATUS_PENDING = 'pending';     // ordine non ancora pagato
    const STATUS_ACTIVE = 'active';       // buono creato e utilizzabile
    const STATUS_CANCELLED = 'cancelled'; // annullata (ordine annullato o a mano)

    const DELIVERY_EMAIL = 'email';       // email al destinatario nella data scelta
    const DELIVERY_PRINT = 'print';       // email all'acquirente con il PDF da stampare
    const DELIVERY_POST = 'post';         // carta fisica spedita dal negozio

    public $id_shop;
    public $id_order = 0;
    public $id_order_detail = 0;
    public $id_customer = 0;
    public $id_product = 0;
    public $id_cart_rule = 0;
    public $id_template = 0;
    public $id_currency;
    public $id_lang;
    public $code = '';
    public $amount;
    public $partial_use = 1;
    public $validity_months = 12;
    public $delivery = self::DELIVERY_PRINT;
    public $sender_name = '';
    public $recipient_name = '';
    public $recipient_email = '';
    public $message = '';
    /** @var string|null Y-m-d, solo per l'invio via email */
    public $send_date;
    /** @var string|null data dell'ultimo invio riuscito */
    public $sent_at;
    public $status = self::STATUS_PENDING;
    public $date_expire;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'dotmgiftcard_card',
        'primary' => 'id_dotmgiftcard_card',
        'fields' => [
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_order' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'id_order_detail' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'id_cart_rule' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'id_template' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'id_currency' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_lang' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'code' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => 64],
            'amount' => ['type' => self::TYPE_FLOAT, 'validate' => 'isPrice', 'required' => true],
            'partial_use' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'validity_months' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'delivery' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 16],
            'sender_name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 128],
            'recipient_name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 128],
            'recipient_email' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 255],
            'message' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => 1000],
            'send_date' => ['type' => self::TYPE_DATE, 'validate' => 'isDateOrNull', 'allow_null' => true],
            'sent_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDateOrNull', 'allow_null' => true],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 16],
            'date_expire' => ['type' => self::TYPE_DATE, 'validate' => 'isDateOrNull', 'allow_null' => true],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    public static function getStatuses()
    {
        return [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_CANCELLED];
    }

    public static function getDeliveries()
    {
        return [self::DELIVERY_EMAIL, self::DELIVERY_PRINT, self::DELIVERY_POST];
    }

    /**
     * @return self[]
     */
    public static function getByOrder($idOrder)
    {
        return self::hydrateIds(Db::getInstance()->executeS(
            'SELECT `id_dotmgiftcard_card` FROM `' . _DB_PREFIX_ . 'dotmgiftcard_card`
            WHERE `id_order` = ' . (int) $idOrder . ' ORDER BY `id_dotmgiftcard_card` ASC'
        ));
    }

    public static function countByOrderDetail($idOrderDetail)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'dotmgiftcard_card` WHERE `id_order_detail` = ' . (int) $idOrderDetail
        );
    }

    /**
     * @return self|null
     */
    public static function getByCartRule($idCartRule)
    {
        $id = (int) Db::getInstance()->getValue(
            'SELECT `id_dotmgiftcard_card` FROM `' . _DB_PREFIX_ . 'dotmgiftcard_card` WHERE `id_cart_rule` = ' . (int) $idCartRule
        );

        return $id ? new self($id) : null;
    }

    /**
     * @return self|null
     */
    public static function getByCode($code)
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '' || !Validate::isCleanHtml($code)) {
            return null;
        }
        $id = (int) Db::getInstance()->getValue(
            'SELECT `id_dotmgiftcard_card` FROM `' . _DB_PREFIX_ . 'dotmgiftcard_card`
            WHERE `code` = \'' . pSQL($code) . '\' AND `id_cart_rule` > 0'
        );

        return $id ? new self($id) : null;
    }

    /**
     * Gift card comprate dal cliente o a lui destinate (stessa email).
     *
     * @return self[]
     */
    public static function getForCustomer(Customer $customer)
    {
        return self::hydrateIds(Db::getInstance()->executeS(
            'SELECT `id_dotmgiftcard_card` FROM `' . _DB_PREFIX_ . 'dotmgiftcard_card`
            WHERE `status` != \'' . pSQL(self::STATUS_PENDING) . '\'
            AND (`id_customer` = ' . (int) $customer->id . '
                OR (`recipient_email` = \'' . pSQL(strtolower($customer->email)) . '\' AND `sent_at` IS NOT NULL))
            ORDER BY `date_add` DESC'
        ));
    }

    /**
     * Gift card da inviare oggi (consegna via email, data raggiunta, non ancora inviate).
     *
     * @return self[]
     */
    public static function getDueForSending($limit = 50)
    {
        return self::hydrateIds(Db::getInstance()->executeS(
            'SELECT `id_dotmgiftcard_card` FROM `' . _DB_PREFIX_ . 'dotmgiftcard_card`
            WHERE `status` = \'' . pSQL(self::STATUS_ACTIVE) . '\'
            AND `delivery` = \'' . pSQL(self::DELIVERY_EMAIL) . '\'
            AND `sent_at` IS NULL
            AND (`send_date` IS NULL OR `send_date` <= \'' . pSQL(date('Y-m-d')) . '\')
            ORDER BY `send_date` ASC, `id_dotmgiftcard_card` ASC
            LIMIT ' . (int) $limit
        ));
    }

    protected static function hydrateIds($rows)
    {
        $cards = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $card = new self((int) $row['id_dotmgiftcard_card']);
            if (Validate::isLoadedObject($card)) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /**
     * @return CartRule|null
     */
    public function getCartRule()
    {
        if (!$this->id_cart_rule) {
            return null;
        }
        $cartRule = new CartRule((int) $this->id_cart_rule);

        return Validate::isLoadedObject($cartRule) ? $cartRule : null;
    }

    public function isExpired()
    {
        return $this->date_expire && strtotime($this->date_expire) < time();
    }

    /**
     * Saldo spendibile ora, nella valuta della carta.
     */
    public function getBalance()
    {
        if ($this->status !== self::STATUS_ACTIVE || $this->isExpired()) {
            return 0.0;
        }
        $cartRule = $this->getCartRule();
        if (!$cartRule || !$cartRule->active || (int) $cartRule->quantity < 1) {
            return 0.0;
        }

        return (float) $cartRule->reduction_amount;
    }

    /**
     * Stato leggibile: pending, active, used, expired, cancelled.
     */
    public function getState()
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return $this->status;
        }
        if ($this->isExpired()) {
            return 'expired';
        }

        return $this->getBalance() > 0 ? 'active' : 'used';
    }

    /**
     * @return array utilizzi negli ordini, dal più recente
     */
    public function getUsages()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT u.*, o.`reference`
            FROM `' . _DB_PREFIX_ . 'dotmgiftcard_usage` u
            LEFT JOIN `' . _DB_PREFIX_ . 'orders` o ON o.`id_order` = u.`id_order`
            WHERE u.`id_dotmgiftcard_card` = ' . (int) $this->id . '
            ORDER BY u.`date_add` DESC'
        );

        return is_array($rows) ? $rows : [];
    }

    public function hasUsages()
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'dotmgiftcard_usage`
            WHERE `id_dotmgiftcard_card` = ' . (int) $this->id . ' AND `restored` = 0'
        );
    }

    /**
     * Chiave per scaricare il PDF senza login (link nelle email).
     */
    public function getKey()
    {
        return substr(hash('sha256', $this->id . '|' . $this->code . '|' . $this->date_add . '|' . _COOKIE_KEY_), 0, 24);
    }

    /**
     * Registro delle azioni (invii, annullamenti, modifiche).
     */
    public function log($action, $details = '', $idEmployee = 0)
    {
        return Db::getInstance()->insert('dotmgiftcard_log', [
            'id_dotmgiftcard_card' => (int) $this->id,
            'id_employee' => (int) $idEmployee,
            'action' => pSQL(Tools::substr($action, 0, 32)),
            'details' => pSQL(Tools::substr($details, 0, 255)),
            'date_add' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getLog()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT l.*, e.`firstname`, e.`lastname`
            FROM `' . _DB_PREFIX_ . 'dotmgiftcard_log` l
            LEFT JOIN `' . _DB_PREFIX_ . 'employee` e ON e.`id_employee` = l.`id_employee`
            WHERE l.`id_dotmgiftcard_card` = ' . (int) $this->id . '
            ORDER BY l.`id_dotmgiftcard_log` DESC'
        );

        return is_array($rows) ? $rows : [];
    }
}

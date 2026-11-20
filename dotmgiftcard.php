<?php
/**
 * Gift card per PrestaShop: pagina di acquisto con modelli grafici, consegna via email in una data scelta,
 * PDF da stampare o carta fisica, codici con saldo residuo, verifica del saldo e gestione in back office.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/DotmGiftCardTemplate.php';
require_once __DIR__ . '/classes/DotmGiftCardProduct.php';
require_once __DIR__ . '/classes/DotmGiftCardCard.php';
require_once __DIR__ . '/classes/DotmGiftCardManager.php';
require_once __DIR__ . '/classes/DotmGiftCardMailer.php';
require_once __DIR__ . '/classes/DotmGiftCardPdf.php';

class Dotmgiftcard extends Module
{
    const CFG_PREFIX = 'DOTMGIFTCARD_PREFIX';
    const CFG_CODE_LENGTH = 'DOTMGIFTCARD_CODE_LENGTH';
    const CFG_COLOR = 'DOTMGIFTCARD_COLOR';
    const CFG_CATEGORY = 'DOTMGIFTCARD_CATEGORY';
    const CFG_DELIVERY_EMAIL = 'DOTMGIFTCARD_DELIVERY_EMAIL';
    const CFG_DELIVERY_PRINT = 'DOTMGIFTCARD_DELIVERY_PRINT';
    const CFG_DELIVERY_POST = 'DOTMGIFTCARD_DELIVERY_POST';
    const CFG_CRON_TOKEN = 'DOTMGIFTCARD_CRON_TOKEN';
    const CFG_VISIT_TRIGGER = 'DOTMGIFTCARD_VISIT_TRIGGER';
    const CFG_LAST_RUN = 'DOTMGIFTCARD_LAST_RUN';
    const CFG_BLOCK_VOUCHERS = 'DOTMGIFTCARD_BLOCK_VOUCHERS';
    const CFG_AS_PAYMENT = 'DOTMGIFTCARD_AS_PAYMENT';
    const CFG_CANCEL_STATES = 'DOTMGIFTCARD_CANCEL_STATES';
    const CFG_RESTORE_ON_CANCEL = 'DOTMGIFTCARD_RESTORE_ON_CANCEL';

    const DEFAULT_COLOR = '#2b2b2b';

    /** pezzi per singola aggiunta al carrello */
    const MAX_QUANTITY = 20;

    /** lunghezza massima del messaggio */
    const MESSAGE_LENGTH = 300;

    /** verifiche del saldo per IP in un'ora */
    const BALANCE_MAX_PER_HOUR = 15;

    /** @var array<string, array> */
    protected $strings = [];

    public function __construct()
    {
        $this->name = 'dotmgiftcard';
        $this->tab = 'pricing_promotion';
        $this->version = '1.0.0';
        $this->author = 'dotm.it';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.7.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = 'Gift card';
        $this->description = 'Vendita di gift card con modelli grafici, invio via email in una data scelta, PDF da stampare o carta fisica, saldo residuo sullo stesso codice e verifica del saldo.';
        $this->confirmUninstall = 'Disinstallando il modulo verranno cancellati modelli, storico e impostazioni delle gift card e i tagli mai venduti (quelli già ordinati restano disattivati). I codici già emessi restano validi come buoni sconto. Continuare?';
    }

    /* ------------------------------------------------------------------
     * Installazione
     * ------------------------------------------------------------------ */

    public function install()
    {
        Configuration::updateGlobalValue('PS_CUSTOMIZATION_FEATURE_ACTIVE', 1);

        return parent::install()
            && $this->installDb()
            && $this->installTabs()
            && Configuration::updateValue(self::CFG_PREFIX, 'GC')
            && Configuration::updateValue(self::CFG_CODE_LENGTH, 12)
            && Configuration::updateValue(self::CFG_COLOR, '')
            && Configuration::updateValue(self::CFG_DELIVERY_EMAIL, 1)
            && Configuration::updateValue(self::CFG_DELIVERY_PRINT, 1)
            && Configuration::updateValue(self::CFG_DELIVERY_POST, 1)
            && Configuration::updateGlobalValue(self::CFG_CRON_TOKEN, Tools::passwdGen(24, 'ALPHANUMERIC'))
            && Configuration::updateValue(self::CFG_VISIT_TRIGGER, 1)
            && Configuration::updateValue(self::CFG_BLOCK_VOUCHERS, 1)
            && Configuration::updateValue(self::CFG_AS_PAYMENT, 0)
            && Configuration::updateValue(self::CFG_RESTORE_ON_CANCEL, 1)
            && Configuration::updateValue(self::CFG_CANCEL_STATES, implode(',', array_filter([
                (int) Configuration::get('PS_OS_CANCELED'),
                (int) Configuration::get('PS_OS_REFUND'),
            ])))
            && $this->registerHook([
                'moduleRoutes',
                'actionFrontControllerSetMedia',
                'displayCustomerAccount',
                'displayCustomization',
                'actionValidateOrder',
                'actionOrderStatusPostUpdate',
                'displayAdminOrderMain',
                'actionObjectProductDeleteAfter',
            ])
            && $this->installDefaultTemplates()
            && $this->installDefaultCatalog();
    }

    public function uninstall()
    {
        $idCategory = (int) Configuration::get(self::CFG_CATEGORY);
        foreach ($this->getConfigKeys() as $key) {
            Configuration::deleteByName($key);
        }
        // tagli mai ordinati: eliminati; tagli presenti in ordini: restano nel catalogo disattivati (storico)
        $db = Db::getInstance();
        $ids = $db->executeS('SELECT `id_product` FROM `' . _DB_PREFIX_ . 'dotmgiftcard_product`');
        foreach (is_array($ids) ? $ids : [] as $row) {
            $idProduct = (int) $row['id_product'];
            $ordered = (bool) $db->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . 'order_detail` WHERE `product_id` = ' . $idProduct);
            $product = new Product($idProduct);
            if (!$ordered && Validate::isLoadedObject($product)) {
                $product->delete();
            } else {
                $db->update('product', ['active' => 0], 'id_product = ' . $idProduct);
                $db->update('product_shop', ['active' => 0], 'id_product = ' . $idProduct);
            }
        }
        if ($idCategory && $idCategory !== (int) Configuration::get('PS_HOME_CATEGORY')
            && !$db->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . 'category_product` WHERE `id_category` = ' . $idCategory)) {
            $category = new Category($idCategory);
            if (Validate::isLoadedObject($category) && !$category->getSubCategories((int) Configuration::get('PS_LANG_DEFAULT'), false)) {
                $category->delete();
            }
        }
        foreach (glob(DotmGiftCardTemplate::getImageDir() . '*.jpg') ?: [] as $file) {
            @unlink($file);
        }

        $db = Db::getInstance();
        foreach (['template', 'template_lang', 'product', 'card', 'usage', 'cart', 'log', 'lookup'] as $table) {
            $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'dotmgiftcard_' . $table . '`');
        }

        return $this->uninstallTabs() && parent::uninstall();
    }

    protected function getConfigKeys()
    {
        return [
            self::CFG_PREFIX, self::CFG_CODE_LENGTH, self::CFG_COLOR, self::CFG_CATEGORY,
            self::CFG_DELIVERY_EMAIL, self::CFG_DELIVERY_PRINT, self::CFG_DELIVERY_POST,
            self::CFG_CRON_TOKEN, self::CFG_VISIT_TRIGGER, self::CFG_LAST_RUN, self::CFG_BLOCK_VOUCHERS,
            self::CFG_AS_PAYMENT, self::CFG_CANCEL_STATES, self::CFG_RESTORE_ON_CANCEL,
        ];
    }

    protected function installDb()
    {
        $engine = _MYSQL_ENGINE_;
        $p = _DB_PREFIX_ . 'dotmgiftcard_';
        $queries = [
            'CREATE TABLE IF NOT EXISTS `' . $p . 'template` (
                `id_dotmgiftcard_template` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `image` VARCHAR(64) NOT NULL DEFAULT \'\',
                `text_color` VARCHAR(7) NOT NULL DEFAULT \'#ffffff\',
                `is_default` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `position` INT UNSIGNED NOT NULL DEFAULT 0,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_dotmgiftcard_template`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $p . 'template_lang` (
                `id_dotmgiftcard_template` INT UNSIGNED NOT NULL,
                `id_lang` INT UNSIGNED NOT NULL,
                `name` VARCHAR(128) NOT NULL DEFAULT \'\',
                `occasion` VARCHAR(64) NOT NULL DEFAULT \'\',
                PRIMARY KEY (`id_dotmgiftcard_template`, `id_lang`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $p . 'product` (
                `id_dotmgiftcard_product` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_product` INT UNSIGNED NOT NULL,
                `validity_months` INT UNSIGNED NOT NULL DEFAULT 12,
                `partial_use` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_dotmgiftcard_product`),
                UNIQUE KEY `id_product` (`id_product`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $p . 'card` (
                `id_dotmgiftcard_card` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
                `id_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_order_detail` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_customer` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_product` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_cart_rule` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_template` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_currency` INT UNSIGNED NOT NULL,
                `id_lang` INT UNSIGNED NOT NULL,
                `code` VARCHAR(64) NOT NULL DEFAULT \'\',
                `amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
                `partial_use` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `validity_months` INT UNSIGNED NOT NULL DEFAULT 12,
                `delivery` VARCHAR(16) NOT NULL DEFAULT \'print\',
                `sender_name` VARCHAR(128) NOT NULL DEFAULT \'\',
                `recipient_name` VARCHAR(128) NOT NULL DEFAULT \'\',
                `recipient_email` VARCHAR(255) NOT NULL DEFAULT \'\',
                `message` TEXT NULL,
                `send_date` DATE NULL,
                `sent_at` DATETIME NULL,
                `status` VARCHAR(16) NOT NULL DEFAULT \'pending\',
                `date_expire` DATETIME NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_dotmgiftcard_card`),
                KEY `id_order` (`id_order`),
                KEY `id_order_detail` (`id_order_detail`),
                KEY `id_customer` (`id_customer`),
                KEY `id_cart_rule` (`id_cart_rule`),
                KEY `code` (`code`),
                KEY `sending` (`status`, `delivery`, `send_date`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $p . 'usage` (
                `id_dotmgiftcard_usage` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_dotmgiftcard_card` INT UNSIGNED NOT NULL,
                `id_order` INT UNSIGNED NOT NULL,
                `amount` DECIMAL(20,6) NOT NULL DEFAULT 0,
                `restored` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_dotmgiftcard_usage`),
                KEY `id_dotmgiftcard_card` (`id_dotmgiftcard_card`),
                KEY `id_order` (`id_order`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $p . 'cart` (
                `id_customization` INT UNSIGNED NOT NULL,
                `id_cart` INT UNSIGNED NOT NULL,
                `id_product` INT UNSIGNED NOT NULL,
                `id_template` INT UNSIGNED NOT NULL DEFAULT 0,
                `delivery` VARCHAR(16) NOT NULL DEFAULT \'print\',
                `sender_name` VARCHAR(128) NOT NULL DEFAULT \'\',
                `recipient_name` VARCHAR(128) NOT NULL DEFAULT \'\',
                `recipient_email` VARCHAR(255) NOT NULL DEFAULT \'\',
                `message` TEXT NULL,
                `send_date` DATE NULL,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_customization`),
                KEY `id_cart` (`id_cart`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $p . 'log` (
                `id_dotmgiftcard_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_dotmgiftcard_card` INT UNSIGNED NOT NULL,
                `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
                `action` VARCHAR(32) NOT NULL,
                `details` VARCHAR(255) NOT NULL DEFAULT \'\',
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_dotmgiftcard_log`),
                KEY `id_dotmgiftcard_card` (`id_dotmgiftcard_card`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . $p . 'lookup` (
                `id_dotmgiftcard_lookup` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ip` VARCHAR(64) NOT NULL,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_dotmgiftcard_lookup`),
                KEY `ip` (`ip`, `date_add`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4',
        ];
        foreach ($queries as $sql) {
            if (!Db::getInstance()->execute($sql)) {
                return false;
            }
        }

        return true;
    }

    protected function getTabDefinitions()
    {
        return [
            'AdminDotmGiftCardCard' => ['parent' => 'AdminParentOrders', 'it' => 'Gift card', 'en' => 'Gift cards'],
            'AdminDotmGiftCardProduct' => ['parent' => 'AdminCatalog', 'it' => 'Gift card', 'en' => 'Gift cards'],
            'AdminDotmGiftCardTemplate' => ['parent' => 'AdminCatalog', 'it' => 'Modelli gift card', 'en' => 'Gift card designs'],
        ];
    }

    protected function installTabs()
    {
        foreach ($this->getTabDefinitions() as $class => $def) {
            if (Tab::getIdFromClassName($class)) {
                continue;
            }
            $tab = new Tab();
            $tab->active = 1;
            $tab->class_name = $class;
            $tab->module = $this->name;
            $tab->id_parent = (int) Tab::getIdFromClassName($def['parent']);
            $tab->name = [];
            foreach (Language::getLanguages(false) as $lang) {
                $tab->name[$lang['id_lang']] = $lang['iso_code'] === 'it' ? $def['it'] : $def['en'];
            }
            if (!$tab->add()) {
                return false;
            }
        }

        return true;
    }

    protected function uninstallTabs()
    {
        foreach (array_keys($this->getTabDefinitions()) as $class) {
            $idTab = (int) Tab::getIdFromClassName($class);
            if ($idTab) {
                (new Tab($idTab))->delete();
            }
        }

        return true;
    }

    /**
     * Modelli inclusi nel modulo (views/img/templates), copiati in img/dotmgiftcard/.
     */
    protected function installDefaultTemplates()
    {
        if (DotmGiftCardTemplate::getDefaultId(false)) {
            return true;
        }
        $defaults = [
            ['file' => 'classic', 'color' => '#ffffff', 'key' => 'tpl_classic', 'occasion' => 'occ_any'],
            ['file' => 'birthday', 'color' => '#ffffff', 'key' => 'tpl_birthday', 'occasion' => 'occ_birthday'],
            ['file' => 'christmas', 'color' => '#ffffff', 'key' => 'tpl_christmas', 'occasion' => 'occ_christmas'],
            ['file' => 'love', 'color' => '#5a2330', 'key' => 'tpl_love', 'occasion' => 'occ_love'],
            ['file' => 'minimal', 'color' => '#111111', 'key' => 'tpl_minimal', 'occasion' => 'occ_any'],
        ];
        foreach ($defaults as $i => $def) {
            $image = DotmGiftCardTemplate::storeImage($this->getLocalPath() . 'views/img/templates/' . $def['file'] . '.jpg');
            if (!$image) {
                continue;
            }
            $template = new DotmGiftCardTemplate();
            $template->image = $image;
            $template->text_color = $def['color'];
            $template->active = 1;
            $template->is_default = $i === 0 ? 1 : 0;
            $template->position = $i + 1;
            $template->name = [];
            $template->occasion = [];
            foreach (Language::getLanguages(false) as $lang) {
                $template->name[(int) $lang['id_lang']] = $this->t($def['key'], [], (int) $lang['id_lang']);
                $template->occasion[(int) $lang['id_lang']] = $this->t($def['occasion'], [], (int) $lang['id_lang']);
            }
            $template->add();
        }

        return true;
    }

    /**
     * Categoria "Gift card" e quattro tagli digitali pronti all'uso (25, 50, 100, 150).
     * Si possono modificare o eliminare da Catalogo → Gift card.
     */
    protected function installDefaultCatalog()
    {
        if (Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'dotmgiftcard_product`')) {
            return true;
        }
        $idCategory = $this->getCategoryId(true);
        $cover = '';
        $idTemplate = DotmGiftCardTemplate::getDefaultId(false);
        if ($idTemplate) {
            $cover = (new DotmGiftCardTemplate($idTemplate))->getImagePath();
        }
        foreach ([25, 50, 100, 150] as $amount) {
            $names = [];
            foreach (Language::getLanguages(false) as $lang) {
                $names[(int) $lang['id_lang']] = $this->t('product_name', ['amount' => $amount], (int) $lang['id_lang']);
            }
            DotmGiftCardProduct::saveProduct([
                'name' => $names,
                'amount' => $amount,
                'id_tax_rules_group' => 0,
                'physical' => false,
                'active' => true,
                'reference' => 'GIFTCARD-' . $amount,
                'validity_months' => 12,
                'partial_use' => true,
                'id_category' => $idCategory,
                'cover' => $cover,
                'field_labels' => $this->getFieldLabels(),
            ]);
        }

        return true;
    }

    /**
     * Categoria delle gift card (creata se manca).
     */
    public function getCategoryId($create = false)
    {
        $id = (int) Configuration::get(self::CFG_CATEGORY);
        if ($id && Validate::isLoadedObject(new Category($id))) {
            return $id;
        }
        if (!$create) {
            return (int) Configuration::get('PS_HOME_CATEGORY');
        }
        $category = new Category();
        $category->id_parent = (int) Configuration::get('PS_HOME_CATEGORY');
        $category->active = 1;
        $category->name = [];
        $category->link_rewrite = [];
        foreach (Language::getLanguages(false) as $lang) {
            $category->name[(int) $lang['id_lang']] = $this->t('category_name', [], (int) $lang['id_lang']);
            $category->link_rewrite[(int) $lang['id_lang']] = 'gift-card';
        }
        if (!$category->add()) {
            return (int) Configuration::get('PS_HOME_CATEGORY');
        }
        Configuration::updateValue(self::CFG_CATEGORY, (int) $category->id);

        return (int) $category->id;
    }

    /* ------------------------------------------------------------------
     * Testi (lang/<iso>.php; per le lingue senza file: inglese, poi italiano)
     * ------------------------------------------------------------------ */

    public function getStrings($idLang = null)
    {
        $iso = $idLang ? Language::getIsoById((int) $idLang) : $this->context->language->iso_code;
        $iso = preg_replace('/[^a-z]/', '', strtolower((string) $iso));
        if (!isset($this->strings[$iso])) {
            $strings = include __DIR__ . '/lang/it.php';
            if ($iso !== 'it') {
                $file = __DIR__ . '/lang/' . $iso . '.php';
                foreach (array_unique([__DIR__ . '/lang/en.php', is_file($file) ? $file : __DIR__ . '/lang/en.php']) as $path) {
                    $local = include $path;
                    $strings = array_merge($strings, is_array($local) ? $local : []);
                }
            }
            $this->strings[$iso] = $strings;
        }

        return $this->strings[$iso];
    }

    public function t($key, array $params = [], $idLang = null)
    {
        $strings = $this->getStrings($idLang);
        $text = isset($strings[$key]) ? $strings[$key] : $key;
        if ($params) {
            $replace = [];
            foreach ($params as $k => $v) {
                $replace['{' . $k . '}'] = $v;
            }
            $text = strtr($text, $replace);
        }

        return $text;
    }

    /**
     * Etichetta del campo di personalizzazione, per lingua.
     */
    public function getFieldLabels()
    {
        $labels = [];
        foreach (Language::getLanguages(false) as $lang) {
            $labels[(int) $lang['id_lang']] = $this->t('field_label', [], (int) $lang['id_lang']);
        }

        return $labels;
    }

    public function getPaymentLabel($idLang)
    {
        return $this->t('payment_label', [], (int) $idLang);
    }

    /* ------------------------------------------------------------------
     * Impostazioni e utilità
     * ------------------------------------------------------------------ */

    public function getManager()
    {
        return new DotmGiftCardManager($this);
    }

    /**
     * Colore dei pulsanti e delle email (#rrggbb); vuoto se non configurato, salvo $withDefault.
     */
    public function getColor($withDefault = false)
    {
        $color = strtolower(trim((string) Configuration::get(self::CFG_COLOR)));
        if (preg_match('/^#[0-9a-f]{6}$/', $color)) {
            return $color;
        }

        return $withDefault ? self::DEFAULT_COLOR : '';
    }

    public function getCssVars()
    {
        $color = $this->getColor();
        if ($color === '') {
            return '';
        }
        $dark = '#';
        foreach (str_split(substr($color, 1), 2) as $hex) {
            $dark .= sprintf('%02x', (int) round(hexdec($hex) * 0.75));
        }

        return '--dgc-accent:' . $color . ';--dgc-accent-dark:' . $dark . ';';
    }

    /**
     * Modalità di consegna attive. "Per posta" solo se esiste almeno una gift card fisica attiva.
     */
    public function getEnabledDeliveries()
    {
        $list = [];
        if (Configuration::get(self::CFG_DELIVERY_EMAIL)) {
            $list[] = DotmGiftCardCard::DELIVERY_EMAIL;
        }
        if (Configuration::get(self::CFG_DELIVERY_PRINT)) {
            $list[] = DotmGiftCardCard::DELIVERY_PRINT;
        }
        if (Configuration::get(self::CFG_DELIVERY_POST)
            && DotmGiftCardProduct::getList((int) $this->context->language->id, true, true)) {
            $list[] = DotmGiftCardCard::DELIVERY_POST;
        }

        return $list;
    }

    public function getCancelStates()
    {
        return array_filter(array_map('intval', explode(',', (string) Configuration::get(self::CFG_CANCEL_STATES))));
    }

    public function getBuyUrl(array $params = [])
    {
        return $this->context->link->getModuleLink($this->name, 'buy', $params, true);
    }

    public function getCronUrl()
    {
        return $this->context->link->getModuleLink($this->name, 'cron', ['token' => Configuration::getGlobalValue(self::CFG_CRON_TOKEN)], true);
    }

    /**
     * Riepilogo della personalizzazione mostrato in carrello, ordine e fattura.
     */
    public function buildSummary(array $data, $idLang)
    {
        $parts = [];
        if ($data['recipient_name'] !== '') {
            $parts[] = $this->t('sum_for', ['name' => $data['recipient_name']], $idLang);
        }
        if ($data['sender_name'] !== '') {
            $parts[] = $this->t('sum_from', ['name' => $data['sender_name']], $idLang);
        }
        switch ($data['delivery']) {
            case DotmGiftCardCard::DELIVERY_EMAIL:
                $parts[] = $data['send_date'] && $data['send_date'] > date('Y-m-d')
                    ? $this->t('sum_email_on', ['email' => $data['recipient_email'], 'date' => Tools::displayDate($data['send_date'])], $idLang)
                    : $this->t('sum_email_now', ['email' => $data['recipient_email']], $idLang);
                break;
            case DotmGiftCardCard::DELIVERY_POST:
                $parts[] = $this->t('sum_post', [], $idLang);
                break;
            default:
                $parts[] = $this->t('sum_print', [], $idLang);
        }
        $template = new DotmGiftCardTemplate((int) $data['id_template'], (int) $idLang);
        if (Validate::isLoadedObject($template)) {
            $parts[] = $this->t('sum_template', ['name' => $template->name], $idLang);
        }
        if ($data['message'] !== '') {
            $message = preg_replace('/\s+/', ' ', $data['message']);
            $parts[] = $this->t('sum_message', ['text' => Tools::strlen($message) > 80 ? Tools::substr($message, 0, 78) . '…' : $message], $idLang);
        }

        return implode(' · ', $parts);
    }

    /**
     * Limite anti-abuso della verifica del saldo.
     */
    public function isBalanceRateLimited($register = true)
    {
        $ip = Tools::substr((string) Tools::getRemoteAddr(), 0, 64);
        $db = Db::getInstance();
        $db->delete('dotmgiftcard_lookup', 'date_add < \'' . pSQL(date('Y-m-d H:i:s', time() - 3600)) . '\'');
        $count = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'dotmgiftcard_lookup` WHERE `ip` = \'' . pSQL($ip) . '\''
        );
        if ($count >= self::BALANCE_MAX_PER_HOUR) {
            return true;
        }
        if ($register) {
            $db->insert('dotmgiftcard_lookup', ['ip' => pSQL($ip), 'date_add' => date('Y-m-d H:i:s')]);
        }

        return false;
    }

    /**
     * Dati di una carta per i template (area clienti, verifica saldo, back office).
     */
    public function presentCard(DotmGiftCardCard $card, $idLang = null)
    {
        $idLang = $idLang ?: (int) $this->context->language->id;
        $manager = $this->getManager();
        $state = $card->getState();
        $template = new DotmGiftCardTemplate((int) $card->id_template, $idLang);

        return [
            'id' => (int) $card->id,
            'code' => $card->code,
            'amount' => $manager->formatAmount($card->amount, (int) $card->id_currency),
            'balance' => $manager->formatAmount($card->getBalance(), (int) $card->id_currency),
            'state' => $state,
            'state_label' => $this->t('state_' . $state, [], $idLang),
            'delivery' => $card->delivery,
            'delivery_label' => $this->t('delivery_' . $card->delivery, [], $idLang),
            'recipient_name' => $card->recipient_name,
            'recipient_email' => $card->recipient_email,
            'sender_name' => $card->sender_name,
            'message' => $card->message,
            'send_date' => $card->send_date ? Tools::displayDate($card->send_date) : '',
            'sent_at' => $card->sent_at ? Tools::displayDate($card->sent_at, true) : '',
            'expire' => $card->date_expire ? Tools::displayDate($card->date_expire) : '',
            'date' => Tools::displayDate($card->date_add),
            'image' => Validate::isLoadedObject($template) ? $template->getImageUrl() : '',
            'text_color' => Validate::isLoadedObject($template) ? $template->text_color : '#ffffff',
            'pdf_url' => $state === 'active'
                ? $this->context->link->getModuleLink($this->name, 'pdf', ['id' => (int) $card->id, 'key' => $card->getKey()], true)
                : '',
        ];
    }

    /* ------------------------------------------------------------------
     * Hook
     * ------------------------------------------------------------------ */

    public function hookModuleRoutes()
    {
        $params = ['fc' => 'module', 'module' => $this->name];

        return [
            'module-dotmgiftcard-buy' => ['controller' => 'buy', 'rule' => 'gift-card', 'keywords' => [], 'params' => $params],
            'module-dotmgiftcard-balance' => ['controller' => 'balance', 'rule' => 'gift-card/balance', 'keywords' => [], 'params' => $params],
        ];
    }

    public function hookActionFrontControllerSetMedia()
    {
        $controller = $this->context->controller;
        $page = isset($controller->php_self) ? $controller->php_self : '';

        // la scheda prodotto di una gift card porta alla pagina di acquisto con il taglio già scelto
        if ($page === 'product' && !Tools::getValue('ajax')) {
            $idProduct = (int) Tools::getValue('id_product');
            if ($idProduct && DotmGiftCardProduct::isGiftCard($idProduct)) {
                Tools::redirect($this->getBuyUrl(['id_product' => $idProduct]));
            }
        }

        if (in_array($page, ['cart', 'order'], true)) {
            $this->enforceVoucherRules();
        }

        if ($page === 'my-account') {
            $controller->registerStylesheet('dotmgiftcard-front', 'modules/' . $this->name . '/views/css/front.css', ['media' => 'all', 'priority' => 200]);
        }

        // invio programmato con le visite (se il cron non è configurato): al massimo una volta l'ora
        if (Configuration::get(self::CFG_VISIT_TRIGGER)) {
            $last = (string) Configuration::getGlobalValue(self::CFG_LAST_RUN);
            if (!$last || strtotime($last) < time() - 3600) {
                Configuration::updateGlobalValue(self::CFG_LAST_RUN, date('Y-m-d H:i:s'));
                try {
                    $this->getManager()->sendScheduled();
                } catch (Exception $e) {
                    PrestaShopLogger::addLog('dotmgiftcard: invio programmato - ' . $e->getMessage(), 3);
                }
            }
        }
    }

    /**
     * Carrelli con gift card: niente codici sconto (le gift card non si comprano con buoni o altre gift card).
     */
    protected function enforceVoucherRules()
    {
        $cart = $this->context->cart;
        if (!Configuration::get(self::CFG_BLOCK_VOUCHERS) || !Validate::isLoadedObject($cart)
            || !DotmGiftCardManager::cartHasGiftCards($cart)) {
            return;
        }
        $removed = false;
        foreach ($cart->getCartRules() as $rule) {
            if (!empty($rule['code'])) {
                $cart->removeCartRule((int) $rule['id_cart_rule']);
                $removed = true;
            }
        }
        if ($removed) {
            $this->context->controller->warning[] = $this->t('vouchers_removed');
        }
    }

    public function hookDisplayCustomerAccount()
    {
        $this->context->smarty->assign([
            'dgc_url' => $this->context->link->getModuleLink($this->name, 'account', [], true),
            'dgc_label' => $this->t('account_link'),
        ]);

        return $this->fetch('module:dotmgiftcard/views/templates/hook/account-link.tpl');
    }

    /**
     * Valore del campo di personalizzazione (salvato dal modulo con id_module).
     */
    public function hookDisplayCustomization($params)
    {
        $value = isset($params['customization']['value']) ? (string) $params['customization']['value'] : '';

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public function hookActionValidateOrder($params)
    {
        if (empty($params['order']) || !Validate::isLoadedObject($params['order'])) {
            return;
        }
        /** @var Order $order */
        $order = $params['order'];
        $state = isset($params['orderStatus']) && Validate::isLoadedObject($params['orderStatus']) ? (int) $params['orderStatus']->id : 0;
        $manager = $this->getManager();
        try {
            $manager->createCardsForOrder($order);
            $manager->recordUsages($order, $state);
            if (Configuration::get(self::CFG_AS_PAYMENT)) {
                $manager->moveUsagesToPayment($order);
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('dotmgiftcard: ordine ' . (int) $order->id . ' - ' . $e->getMessage(), 3, null, 'Order', (int) $order->id);
        }
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        $idOrder = isset($params['id_order']) ? (int) $params['id_order'] : 0;
        $state = isset($params['newOrderStatus']) ? $params['newOrderStatus'] : null;
        if (!$idOrder || !Validate::isLoadedObject($state)) {
            return;
        }
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return;
        }
        $manager = $this->getManager();
        try {
            if (in_array((int) $state->id, $this->getCancelStates(), true)) {
                $manager->cancelOrder($order);
            } elseif ($state->paid) {
                $manager->activateOrder($order);
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('dotmgiftcard: stato ordine ' . $idOrder . ' - ' . $e->getMessage(), 3, null, 'Order', $idOrder);
        }
    }

    public function hookActionObjectProductDeleteAfter($params)
    {
        if (!empty($params['object']->id)) {
            Db::getInstance()->delete('dotmgiftcard_product', 'id_product = ' . (int) $params['object']->id);
            DotmGiftCardProduct::resetLookup();
        }
    }

    public function hookDisplayAdminOrderMain($params)
    {
        $idOrder = isset($params['id_order']) ? (int) $params['id_order'] : 0;
        if (!$idOrder) {
            return '';
        }
        $link = $this->context->link;
        $idLang = (int) $this->context->language->id;
        $cardLink = function ($id) use ($link) {
            return $link->getAdminLink('AdminDotmGiftCardCard', true, [], ['id_dotmgiftcard_card' => (int) $id, 'viewdotmgiftcard_card' => 1]);
        };

        $bought = [];
        foreach (DotmGiftCardCard::getByOrder($idOrder) as $card) {
            $row = $this->presentCard($card, $idLang);
            $row['link'] = $cardLink($card->id);
            $row['state_label'] = $this->t('state_' . $row['state'], [], $idLang);
            $bought[] = $row;
        }

        $used = [];
        $rows = Db::getInstance()->executeS(
            'SELECT u.*, c.`code`, c.`id_currency` FROM `' . _DB_PREFIX_ . 'dotmgiftcard_usage` u
            INNER JOIN `' . _DB_PREFIX_ . 'dotmgiftcard_card` c ON c.`id_dotmgiftcard_card` = u.`id_dotmgiftcard_card`
            WHERE u.`id_order` = ' . (int) $idOrder
        );
        $manager = $this->getManager();
        foreach (is_array($rows) ? $rows : [] as $row) {
            $used[] = [
                'code' => $row['code'],
                'amount' => $manager->formatAmount($row['amount'], (int) $row['id_currency']),
                'restored' => (bool) $row['restored'],
                'link' => $cardLink($row['id_dotmgiftcard_card']),
            ];
        }

        if (!$bought && !$used) {
            return '';
        }
        $this->context->smarty->assign(['dgc_admin' => [
            'bought' => $bought,
            'used' => $used,
            'pending' => in_array('pending', array_column($bought, 'state'), true),
        ]]);

        return $this->display(__FILE__, 'views/templates/admin/order-main.tpl');
    }

    /* ------------------------------------------------------------------
     * Configurazione
     * ------------------------------------------------------------------ */

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submitDotmgiftcard')) {
            $output .= $this->saveConfig();
        } elseif (Tools::isSubmit('dgcRunCron')) {
            $sent = $this->getManager()->sendScheduled();
            $output .= $this->displayConfirmation('Invio programmato eseguito: ' . (int) $sent . ' gift card inviate.');
        } elseif (Tools::isSubmit('dgcNewToken')) {
            Configuration::updateGlobalValue(self::CFG_CRON_TOKEN, Tools::passwdGen(24, 'ALPHANUMERIC'));
            $output .= $this->displayConfirmation('Nuovo token generato: aggiorna il comando del cron.');
        }

        $link = $this->context->link;
        $e = function ($s) {
            return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        };
        $buy = $this->getBuyUrl();
        $balance = $link->getModuleLink($this->name, 'balance', [], true);
        $cron = $this->getCronUrl();
        $last = (string) Configuration::getGlobalValue(self::CFG_LAST_RUN);
        $self = $link->getAdminLink('AdminModules', true, [], ['configure' => $this->name]);

        $output .= '<div class="panel"><h3><i class="icon-gift"></i> Gift card</h3>'
            . '<p><strong>Pagina di acquisto:</strong> <a href="' . $e($buy) . '" target="_blank">' . $e($buy) . '</a> — collegala nel menu o nel footer.</p>'
            . '<p><strong>Verifica del saldo:</strong> <a href="' . $e($balance) . '" target="_blank">' . $e($balance) . '</a></p>'
            . '<p>Gestione: <a href="' . $e($link->getAdminLink('AdminDotmGiftCardProduct')) . '">Catalogo → Gift card</a> (tagli e prezzi) · '
            . '<a href="' . $e($link->getAdminLink('AdminDotmGiftCardTemplate')) . '">Catalogo → Modelli gift card</a> · '
            . '<a href="' . $e($link->getAdminLink('AdminDotmGiftCardCard')) . '">Ordini → Gift card</a> (carte emesse, invii, saldi).</p>'
            . '<hr><p><strong>Invio programmato</strong> (gift card via email con data futura). Comando consigliato, ogni giorno alle 8:</p>'
            . '<pre>0 8 * * * curl -s "' . $e($cron) . '" &gt;/dev/null</pre>'
            . '<p class="help-block">Ultima esecuzione: ' . ($last ? $e(Tools::displayDate($last, true)) : 'mai') . '.</p>'
            . '<form method="post" action="' . $e($self) . '" style="display:inline-block;margin-right:8px;"><button type="submit" name="dgcRunCron" class="btn btn-default"><i class="icon-envelope"></i> Esegui ora l\'invio</button></form>'
            . '<form method="post" action="' . $e($self) . '" style="display:inline-block;"><button type="submit" name="dgcNewToken" class="btn btn-default" onclick="return confirm(\'Il comando del cron attuale smetterà di funzionare. Continuare?\');"><i class="icon-refresh"></i> Nuovo token</button></form>'
            . '</div>';

        return $output . $this->renderForm();
    }

    protected function saveConfig()
    {
        $prefix = strtoupper(trim((string) Tools::getValue(self::CFG_PREFIX)));
        $length = (int) Tools::getValue(self::CFG_CODE_LENGTH);
        $color = strtolower(trim((string) Tools::getValue(self::CFG_COLOR)));
        $deliveries = [
            self::CFG_DELIVERY_EMAIL => (int) (bool) Tools::getValue(self::CFG_DELIVERY_EMAIL),
            self::CFG_DELIVERY_PRINT => (int) (bool) Tools::getValue(self::CFG_DELIVERY_PRINT),
            self::CFG_DELIVERY_POST => (int) (bool) Tools::getValue(self::CFG_DELIVERY_POST),
        ];

        if ($prefix !== '' && !preg_match('/^[A-Z0-9]{1,8}$/', $prefix)) {
            return $this->displayError('Prefisso non valido: da 1 a 8 lettere o cifre, oppure vuoto.');
        }
        if ($length < 8 || $length > 20) {
            return $this->displayError('La lunghezza del codice deve essere tra 8 e 20 caratteri.');
        }
        if ($color !== '' && !preg_match('/^#[0-9a-f]{6}$/', $color)) {
            return $this->displayError('Colore non valido: usa il formato #rrggbb oppure lascia vuoto.');
        }
        if (!array_sum($deliveries)) {
            return $this->displayError('Attiva almeno una modalità di consegna.');
        }

        Configuration::updateValue(self::CFG_PREFIX, $prefix);
        Configuration::updateValue(self::CFG_CODE_LENGTH, $length);
        Configuration::updateValue(self::CFG_COLOR, $color);
        foreach ($deliveries as $key => $value) {
            Configuration::updateValue($key, $value);
        }
        Configuration::updateValue(self::CFG_VISIT_TRIGGER, (int) (bool) Tools::getValue(self::CFG_VISIT_TRIGGER));
        Configuration::updateValue(self::CFG_BLOCK_VOUCHERS, (int) (bool) Tools::getValue(self::CFG_BLOCK_VOUCHERS));
        Configuration::updateValue(self::CFG_AS_PAYMENT, (int) (bool) Tools::getValue(self::CFG_AS_PAYMENT));
        Configuration::updateValue(self::CFG_RESTORE_ON_CANCEL, (int) (bool) Tools::getValue(self::CFG_RESTORE_ON_CANCEL));
        Configuration::updateValue(self::CFG_CANCEL_STATES, implode(',', array_filter(array_map('intval', (array) Tools::getValue(self::CFG_CANCEL_STATES, [])))));

        return $this->displayConfirmation('Impostazioni salvate.');
    }

    protected function renderForm()
    {
        $switch = function ($name, $label, $desc = '') {
            return [
                'type' => 'switch',
                'label' => $label,
                'name' => $name,
                'is_bool' => true,
                'desc' => $desc,
                'values' => [
                    ['id' => $name . '_on', 'value' => 1, 'label' => 'Sì'],
                    ['id' => $name . '_off', 'value' => 0, 'label' => 'No'],
                ],
            ];
        };

        $forms = [
            ['form' => [
                'legend' => ['title' => 'Codici e aspetto', 'icon' => 'icon-cogs'],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => 'Prefisso dei codici',
                        'name' => self::CFG_PREFIX,
                        'class' => 'fixed-width-md',
                        'desc' => 'Ad esempio GC → GC-7KQ9-M2XA-P4TD. Da 1 a 8 lettere o cifre, oppure vuoto.',
                    ],
                    [
                        'type' => 'text',
                        'label' => 'Lunghezza del codice',
                        'name' => self::CFG_CODE_LENGTH,
                        'class' => 'fixed-width-sm',
                        'desc' => 'Caratteri casuali esclusi prefisso e trattini (8–20). Senza 0/O e 1/I/L, per evitare errori di lettura.',
                    ],
                    [
                        'type' => 'color',
                        'label' => 'Colore pulsanti',
                        'name' => self::CFG_COLOR,
                        'desc' => 'Formato #rrggbb, ad esempio il colore principale del tema. Se vuoto: ' . self::DEFAULT_COLOR . '. Usato anche in email e PDF.',
                    ],
                ],
                'submit' => ['title' => 'Salva'],
            ]],
            ['form' => [
                'legend' => ['title' => 'Consegna', 'icon' => 'icon-envelope'],
                'input' => [
                    $switch(self::CFG_DELIVERY_EMAIL, 'Via email al destinatario', 'Il cliente indica email e data di invio: la gift card parte quel giorno, l\'acquirente riceve conferma.'),
                    $switch(self::CFG_DELIVERY_PRINT, 'Da stampare', 'L\'acquirente riceve subito la gift card in PDF da stampare o inoltrare.'),
                    $switch(self::CFG_DELIVERY_POST, 'Per posta', 'Carta fisica spedita dal negozio. Serve almeno una gift card "fisica" in Catalogo → Gift card.'),
                    $switch(self::CFG_VISIT_TRIGGER, 'Invio con le visite al sito', 'Se il cron non è configurato, le gift card programmate partono alla prima visita dell\'ora. Con il cron puoi disattivarlo.'),
                ],
                'submit' => ['title' => 'Salva'],
            ]],
            ['form' => [
                'legend' => ['title' => 'Ordini e pagamenti', 'icon' => 'icon-credit-card'],
                'input' => [
                    $switch(self::CFG_BLOCK_VOUCHERS, 'Niente codici sconto con le gift card', 'Se il carrello contiene gift card, i codici sconto (comprese altre gift card) vengono tolti.'),
                    $switch(self::CFG_AS_PAYMENT, 'Gift card come pagamento', 'L\'importo della gift card usata viene registrato come pagamento e non come sconto: il totale dell\'ordine e l\'IVA restano sul prezzo pieno (buoni multiuso). Verifica con il commercialista.'),
                    [
                        'type' => 'select',
                        'label' => 'Stati che annullano',
                        'name' => self::CFG_CANCEL_STATES . '[]',
                        'multiple' => true,
                        'size' => 8,
                        'options' => ['query' => OrderState::getOrderStates((int) $this->context->language->id), 'id' => 'id_order_state', 'name' => 'name'],
                        'desc' => 'Quando un ordine passa a uno di questi stati, le gift card acquistate e non ancora usate vengono annullate. Ctrl/Cmd per selezioni multiple.',
                    ],
                    $switch(self::CFG_RESTORE_ON_CANCEL, 'Riaccredita le gift card usate', 'Se un ordine pagato con una gift card viene annullato, l\'importo torna sul saldo della carta.'),
                ],
                'submit' => ['title' => 'Salva'],
            ]],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->submit_action = 'submitDotmgiftcard';
        $helper->fields_value = [
            self::CFG_PREFIX => (string) Configuration::get(self::CFG_PREFIX),
            self::CFG_CODE_LENGTH => (int) Configuration::get(self::CFG_CODE_LENGTH),
            self::CFG_COLOR => $this->getColor(),
            self::CFG_DELIVERY_EMAIL => (int) Configuration::get(self::CFG_DELIVERY_EMAIL),
            self::CFG_DELIVERY_PRINT => (int) Configuration::get(self::CFG_DELIVERY_PRINT),
            self::CFG_DELIVERY_POST => (int) Configuration::get(self::CFG_DELIVERY_POST),
            self::CFG_VISIT_TRIGGER => (int) Configuration::get(self::CFG_VISIT_TRIGGER),
            self::CFG_BLOCK_VOUCHERS => (int) Configuration::get(self::CFG_BLOCK_VOUCHERS),
            self::CFG_AS_PAYMENT => (int) Configuration::get(self::CFG_AS_PAYMENT),
            self::CFG_CANCEL_STATES . '[]' => $this->getCancelStates(),
            self::CFG_RESTORE_ON_CANCEL => (int) Configuration::get(self::CFG_RESTORE_ON_CANCEL),
        ];

        return $helper->generateForm($forms);
    }
}

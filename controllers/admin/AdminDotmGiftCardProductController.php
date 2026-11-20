<?php
/**
 * Catalogo → Gift card: i tagli in vendita (prodotti PrestaShop) con importo, imposte, validità e uso parziale.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'dotmgiftcard/dotmgiftcard.php';

class AdminDotmGiftCardProductController extends ModuleAdminController
{
    /** @var Dotmgiftcard */
    public $module;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'dotmgiftcard_product';
        $this->className = 'DotmGiftCardProduct';
        $this->identifier = 'id_dotmgiftcard_product';
        $this->lang = false;
        $this->list_no_link = false;

        parent::__construct();

        $idShop = (int) $this->context->shop->id;
        $idLang = (int) $this->context->language->id;
        $this->_select = 'pl.`name`, p.`reference`, p.`is_virtual`, ps.`active` AS `product_active`, a.`id_product` AS `amount`';
        $this->_join = '
            INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = a.`id_product`
            INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.`id_product` = a.`id_product` AND ps.`id_shop` = ' . $idShop . '
            LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.`id_product` = a.`id_product` AND pl.`id_lang` = ' . $idLang . ' AND pl.`id_shop` = ' . $idShop;
        $this->_defaultOrderBy = 'ps.price';
        $this->_defaultOrderWay = 'ASC';

        $this->addRowAction('edit');
        $this->addRowAction('product');
        $this->addRowAction('delete');

        $this->fields_list = [
            'id_product' => ['title' => 'ID prodotto', 'align' => 'center', 'class' => 'fixed-width-xs', 'filter_key' => 'a!id_product'],
            'name' => ['title' => 'Nome', 'filter_key' => 'pl!name'],
            'reference' => ['title' => 'Riferimento', 'filter_key' => 'p!reference'],
            'amount' => ['title' => 'Importo (IVA incl.)', 'callback' => 'renderAmount', 'search' => false, 'orderby' => false, 'align' => 'text-right'],
            'is_virtual' => ['title' => 'Tipo', 'callback' => 'renderType', 'type' => 'select', 'list' => [1 => 'Digitale', 0 => 'Fisica'], 'filter_key' => 'p!is_virtual'],
            'validity_months' => ['title' => 'Validità', 'callback' => 'renderValidity', 'align' => 'center'],
            'partial_use' => ['title' => 'Uso parziale', 'type' => 'bool', 'align' => 'center', 'class' => 'fixed-width-sm'],
            'product_active' => ['title' => 'Attiva', 'type' => 'bool', 'align' => 'center', 'class' => 'fixed-width-sm', 'filter_key' => 'ps!active'],
        ];
    }

    public function renderAmount($idProduct)
    {
        $price = Product::getPriceStatic((int) $idProduct, true, 0, 2, null, false, false);

        return DotmGiftCardManager::formatPrice($price, Currency::getDefaultCurrency());
    }

    public function renderType($isVirtual)
    {
        return (int) $isVirtual ? 'Digitale' : '<span class="label label-info">Fisica</span>';
    }

    public function renderValidity($months)
    {
        return (int) $months > 0 ? (int) $months . ' mesi' : 'Senza scadenza';
    }

    public function displayProductLink($token, $id)
    {
        $settings = new DotmGiftCardProduct((int) $id);
        $href = $this->context->link->getAdminLink('AdminProducts', true, ['id_product' => (int) $settings->id_product, 'productId' => (int) $settings->id_product], ['id_product' => (int) $settings->id_product, 'updateproduct' => 1]);

        return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" title="Scheda prodotto"><i class="icon-external-link"></i> Scheda prodotto</a>';
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

    public function renderList()
    {
        $info = '<div class="alert alert-info">Ogni riga è un taglio in vendita (un prodotto del catalogo). Il cliente lo sceglie nella '
            . '<a href="' . htmlspecialchars($this->module->getBuyUrl(), ENT_QUOTES, 'UTF-8') . '" target="_blank">pagina di acquisto</a>; '
            . 'la scheda prodotto rimanda lì. Per immagini, SEO e descrizione usa «Scheda prodotto».</div>';

        return $info . parent::renderList();
    }

    public function renderForm()
    {
        /** @var DotmGiftCardProduct $obj */
        $obj = $this->loadObject(true);
        $product = Validate::isLoadedObject($obj) ? new Product((int) $obj->id_product) : null;

        $taxes = [['id_tax_rules_group' => 0, 'name' => 'Nessuna imposta (consigliato per i buoni multiuso)']];
        foreach (TaxRulesGroup::getTaxRulesGroups(true) as $group) {
            $taxes[] = ['id_tax_rules_group' => (int) $group['id_tax_rules_group'], 'name' => $group['name']];
        }
        $switch = function ($name, $label, $desc = '') {
            return [
                'type' => 'switch', 'label' => $label, 'name' => $name, 'is_bool' => true, 'desc' => $desc,
                'values' => [['id' => $name . '_on', 'value' => 1, 'label' => 'Sì'], ['id' => $name . '_off', 'value' => 0, 'label' => 'No']],
            ];
        };

        $this->fields_form = [
            'legend' => ['title' => 'Gift card', 'icon' => 'icon-gift'],
            'input' => [
                ['type' => 'text', 'label' => 'Nome', 'name' => 'name', 'lang' => true, 'required' => true, 'desc' => 'Compare in carrello, ordine e fattura. Esempio: Gift card 50.'],
                [
                    'type' => 'text', 'label' => 'Importo', 'name' => 'amount', 'required' => true, 'class' => 'fixed-width-md',
                    'suffix' => Currency::getDefaultCurrency()->sign,
                    'desc' => 'Prezzo finale (IVA inclusa) e valore del buono. Nelle altre valute viene convertito come gli altri prezzi.',
                ],
                [
                    'type' => 'select', 'label' => 'Imposte', 'name' => 'id_tax_rules_group',
                    'options' => ['query' => $taxes, 'id' => 'id_tax_rules_group', 'name' => 'name'],
                    'desc' => 'Le gift card spendibili su tutto il catalogo (buoni multiuso) di norma si vendono senza IVA: l\'imposta si applica quando vengono usate.',
                ],
                $switch('physical', 'Carta fisica', 'Sì: spedita per posta (prodotto non virtuale, con spese di spedizione). No: digitale (email o PDF).'),
                ['type' => 'text', 'label' => 'Validità (mesi)', 'name' => 'validity_months', 'class' => 'fixed-width-sm', 'desc' => 'Dalla data di attivazione (pagamento). 0 = senza scadenza.'],
                $switch('partial_use', 'Uso parziale', 'Sì: il saldo non speso resta sullo stesso codice per gli ordini successivi. No: si usa una volta sola.'),
                ['type' => 'text', 'label' => 'Riferimento', 'name' => 'reference', 'class' => 'fixed-width-lg'],
                $switch('active', 'Attiva', 'Visibile nella pagina di acquisto.'),
            ],
            'submit' => ['title' => 'Salva'],
        ];

        $names = [];
        foreach (Language::getLanguages(false) as $lang) {
            $names[(int) $lang['id_lang']] = $product ? (string) $product->name[(int) $lang['id_lang']] : '';
        }
        $this->fields_value = [
            'name' => $names,
            'amount' => $product ? Tools::ps_round(Product::getPriceStatic((int) $product->id, true, 0, 6, null, false, false), 2) : '',
            'id_tax_rules_group' => $product ? (int) $product->id_tax_rules_group : 0,
            'physical' => $product ? (int) !$product->is_virtual : 0,
            'validity_months' => Validate::isLoadedObject($obj) ? (int) $obj->validity_months : 12,
            'partial_use' => Validate::isLoadedObject($obj) ? (int) $obj->partial_use : 1,
            'reference' => $product ? $product->reference : '',
            'active' => $product ? (int) $product->active : 1,
        ];

        return parent::renderForm();
    }

    public function processSave()
    {
        /** @var DotmGiftCardProduct $obj */
        $obj = $this->loadObject(true);
        $idProduct = Validate::isLoadedObject($obj) ? (int) $obj->id_product : 0;
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $names = [];
        foreach (Language::getLanguages(false) as $lang) {
            $names[(int) $lang['id_lang']] = trim((string) Tools::getValue('name_' . (int) $lang['id_lang']));
        }
        if ($names[$defaultLang] === '') {
            $this->errors[] = 'Inserisci il nome almeno nella lingua predefinita.';
        }
        foreach ($names as $idLang => $name) {
            if ($name === '') {
                $names[$idLang] = $names[$defaultLang];
            } elseif (!Validate::isCatalogName($name)) {
                $this->errors[] = 'Nome non valido: ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            }
        }
        $amount = (float) str_replace(',', '.', (string) Tools::getValue('amount'));
        if ($amount <= 0) {
            $this->errors[] = 'L\'importo deve essere maggiore di zero.';
        }
        $reference = trim((string) Tools::getValue('reference'));
        if ($reference !== '' && !Validate::isReference($reference)) {
            $this->errors[] = 'Riferimento non valido.';
        }
        if ($this->errors) {
            $this->display = 'edit';

            return false;
        }

        $cover = '';
        if (!$idProduct) {
            $idTemplate = DotmGiftCardTemplate::getDefaultId();
            $cover = $idTemplate ? (new DotmGiftCardTemplate($idTemplate))->getImagePath() : '';
        }
        $result = DotmGiftCardProduct::saveProduct([
            'name' => $names,
            'amount' => $amount,
            'id_tax_rules_group' => (int) Tools::getValue('id_tax_rules_group'),
            'physical' => (bool) Tools::getValue('physical'),
            'active' => (bool) Tools::getValue('active'),
            'reference' => $reference,
            'validity_months' => (int) Tools::getValue('validity_months'),
            'partial_use' => (bool) Tools::getValue('partial_use'),
            'id_category' => $this->module->getCategoryId(true),
            'cover' => $cover,
            'field_labels' => $this->module->getFieldLabels(),
        ], $idProduct);

        if (!$result) {
            $this->errors[] = 'Impossibile salvare il prodotto.';
            $this->display = 'edit';

            return false;
        }
        Tools::redirectAdmin(self::$currentIndex . '&conf=' . ($idProduct ? 4 : 3) . '&token=' . $this->token);

        return true;
    }

    public function processDelete()
    {
        /** @var DotmGiftCardProduct $obj */
        $obj = $this->loadObject();
        if (!Validate::isLoadedObject($obj)) {
            return false;
        }
        $product = new Product((int) $obj->id_product);
        if (Validate::isLoadedObject($product)) {
            $product->delete();
        }
        $obj->delete();
        Tools::redirectAdmin(self::$currentIndex . '&conf=1&token=' . $this->token);

        return true;
    }
}

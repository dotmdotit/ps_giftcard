<?php
/**
 * Impostazioni gift card di un prodotto PrestaShop (validità, uso parziale).
 * Il prodotto vero e proprio (nome, prezzo, imposte, immagini) resta un normale prodotto del catalogo.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmGiftCardProduct extends ObjectModel
{
    public $id_product;
    /** @var int mesi di validità del buono dalla data di attivazione; 0 = senza scadenza */
    public $validity_months = 12;
    /** @var bool il saldo non usato resta disponibile sullo stesso codice */
    public $partial_use = 1;
    public $date_add;
    public $date_upd;

    /** @var array<int, int> cache id_product => id */
    protected static $cache = [];

    public static $definition = [
        'table' => 'dotmgiftcard_product',
        'primary' => 'id_dotmgiftcard_product',
        'fields' => [
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'validity_months' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'partial_use' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    public static function getIdByProduct($idProduct)
    {
        $idProduct = (int) $idProduct;
        if (!isset(self::$cache[$idProduct])) {
            self::$cache[$idProduct] = (int) Db::getInstance()->getValue(
                'SELECT `id_dotmgiftcard_product` FROM `' . _DB_PREFIX_ . 'dotmgiftcard_product` WHERE `id_product` = ' . $idProduct
            );
        }

        return self::$cache[$idProduct];
    }

    /**
     * @return self|null
     */
    public static function getByProduct($idProduct)
    {
        $id = self::getIdByProduct($idProduct);

        return $id ? new self($id) : null;
    }

    public static function isGiftCard($idProduct)
    {
        return self::getIdByProduct($idProduct) > 0;
    }

    public static function resetLookup()
    {
        self::$cache = [];
    }

    /**
     * Gift card del catalogo con prezzo (IVA inclusa) nella valuta corrente.
     *
     * @param bool|null $physical null = tutte, true = solo fisiche, false = solo digitali
     *
     * @return array [{id_product, name, amount, amount_label, physical, validity_months, partial_use, active}]
     */
    public static function getList($idLang, $activeOnly = true, $physical = null)
    {
        $context = Context::getContext();
        $rows = Db::getInstance()->executeS(
            'SELECT g.*, p.`is_virtual`, ps.`active`, pl.`name`
            FROM `' . _DB_PREFIX_ . 'dotmgiftcard_product` g
            INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = g.`id_product`
            INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.`id_product` = g.`id_product` AND ps.`id_shop` = ' . (int) $context->shop->id . '
            LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.`id_product` = g.`id_product`
                AND pl.`id_lang` = ' . (int) $idLang . ' AND pl.`id_shop` = ' . (int) $context->shop->id . '
            ' . ($activeOnly ? 'WHERE ps.`active` = 1' : '')
        );

        $list = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $isPhysical = !(int) $row['is_virtual'];
            if ($physical !== null && $physical !== $isPhysical) {
                continue;
            }
            $amount = (float) Product::getPriceStatic((int) $row['id_product'], true, 0, 6, null, false, false);
            $list[] = [
                'id_product' => (int) $row['id_product'],
                'name' => (string) $row['name'],
                'amount' => $amount,
                'amount_label' => DotmGiftCardManager::formatPrice($amount, $context->currency),
                'physical' => $isPhysical,
                'validity_months' => (int) $row['validity_months'],
                'partial_use' => (bool) $row['partial_use'],
                'active' => (bool) $row['active'],
            ];
        }
        usort($list, function ($a, $b) {
            return $a['amount'] == $b['amount'] ? $a['id_product'] - $b['id_product'] : ($a['amount'] < $b['amount'] ? -1 : 1);
        });

        return $list;
    }

    /**
     * Campo di personalizzazione "di modulo" (nascosto nella scheda prodotto, obbligatorio):
     * impedisce di aggiungere la gift card al carrello senza passare dalla pagina di acquisto
     * e mostra il riepilogo della personalizzazione in carrello, ordine e fattura.
     *
     * @return int id_customization_field
     */
    public static function ensureCustomizationField($idProduct, array $labels)
    {
        $db = Db::getInstance();
        $deletedCol = self::hasColumn('customization_field', 'is_deleted');
        $id = (int) $db->getValue(
            'SELECT `id_customization_field` FROM `' . _DB_PREFIX_ . 'customization_field`
            WHERE `id_product` = ' . (int) $idProduct . ' AND `type` = ' . (int) Product::CUSTOMIZE_TEXTFIELD . ' AND `is_module` = 1'
            . ($deletedCol ? ' AND `is_deleted` = 0' : '') . '
            ORDER BY `id_customization_field` ASC'
        );

        if (!$id) {
            $field = new CustomizationField();
            $field->id_product = (int) $idProduct;
            $field->type = Product::CUSTOMIZE_TEXTFIELD;
            $field->required = 1;
            $field->is_module = 1;
            if (property_exists($field, 'is_deleted')) {
                $field->is_deleted = 0;
            }
            $field->name = $labels;
            if (!$field->add()) {
                return 0;
            }
            $id = (int) $field->id;
        }

        $db->update('product', ['customizable' => 2, 'text_fields' => 1], 'id_product = ' . (int) $idProduct);
        $db->update('product_shop', ['customizable' => 2, 'text_fields' => 1], 'id_product = ' . (int) $idProduct);
        Configuration::updateGlobalValue('PS_CUSTOMIZATION_FEATURE_ACTIVE', 1);

        return $id;
    }

    public static function getCustomizationFieldId($idProduct)
    {
        $deletedCol = self::hasColumn('customization_field', 'is_deleted');

        return (int) Db::getInstance()->getValue(
            'SELECT `id_customization_field` FROM `' . _DB_PREFIX_ . 'customization_field`
            WHERE `id_product` = ' . (int) $idProduct . ' AND `type` = ' . (int) Product::CUSTOMIZE_TEXTFIELD . ' AND `is_module` = 1'
            . ($deletedCol ? ' AND `is_deleted` = 0' : '') . '
            ORDER BY `id_customization_field` ASC'
        );
    }

    public static function hasColumn($table, $column)
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (!isset($cache[$key])) {
            $cache[$key] = (bool) Db::getInstance()->executeS(
                'SHOW COLUMNS FROM `' . _DB_PREFIX_ . bqSQL($table) . '` LIKE \'' . pSQL($column) . '\''
            );
        }

        return $cache[$key];
    }

    /**
     * Crea o aggiorna il prodotto PrestaShop di una gift card.
     *
     * @param array $data name (array per lingua), amount (IVA inclusa), id_tax_rules_group, physical, active,
     *                    reference, validity_months, partial_use, id_category, cover (percorso immagine), field_labels
     *
     * @return int|false id_product
     */
    public static function saveProduct(array $data, $idProduct = 0)
    {
        $product = new Product((int) $idProduct ?: null);
        $isNew = !Validate::isLoadedObject($product);
        $idCategory = (int) $data['id_category'] ?: (int) Configuration::get('PS_HOME_CATEGORY');

        $product->name = $data['name'];
        $linkRewrite = [];
        foreach ($data['name'] as $idLang => $name) {
            $linkRewrite[$idLang] = Tools::str2url($name) ?: 'gift-card';
        }
        $product->link_rewrite = $linkRewrite;
        $product->id_tax_rules_group = (int) $data['id_tax_rules_group'];
        $product->price = self::priceWithoutTax((float) $data['amount'], (int) $data['id_tax_rules_group']);
        $product->wholesale_price = 0;
        $product->is_virtual = empty($data['physical']) ? 1 : 0;
        $product->active = !empty($data['active']) ? 1 : 0;
        $product->reference = Tools::substr((string) $data['reference'], 0, 64);
        $product->customizable = 2;
        $product->text_fields = 1;
        $product->uploadable_files = 0;
        $product->available_for_order = 1;
        $product->show_price = 1;
        $product->minimal_quantity = 1;
        if ($isNew) {
            $product->id_category_default = $idCategory;
            $product->visibility = 'both';
            $product->condition = 'new';
            $product->out_of_stock = 1;
        }

        if ($isNew ? !$product->add() : !$product->update()) {
            return false;
        }
        if ($isNew) {
            $product->addToCategories([$idCategory]);
            StockAvailable::setProductOutOfStock((int) $product->id, 1);
            if (!empty($data['cover'])) {
                self::setCoverImage((int) $product->id, $data['cover']);
            }
        }
        if (property_exists($product, 'product_type') && method_exists($product, 'getProductType')) {
            // PrestaShop 8+: tipo prodotto coerente con "virtuale"
            Db::getInstance()->update('product', ['product_type' => $product->is_virtual ? 'virtual' : 'standard'], 'id_product = ' . (int) $product->id);
        }

        if (!self::ensureCustomizationField((int) $product->id, $data['field_labels'])) {
            return false;
        }

        $settings = self::getByProduct((int) $product->id) ?: new self();
        $settings->id_product = (int) $product->id;
        $settings->validity_months = max(0, (int) $data['validity_months']);
        $settings->partial_use = !empty($data['partial_use']) ? 1 : 0;
        $settings->save();
        self::resetLookup();

        return (int) $product->id;
    }

    /**
     * Prezzo senza imposte tale che, con la regola fiscale indicata e il paese predefinito, il prezzo finale sia $amount.
     */
    public static function priceWithoutTax($amount, $idTaxRulesGroup)
    {
        if ($idTaxRulesGroup <= 0) {
            return round($amount, 6);
        }
        $address = Address::initialize(null);
        $calculator = TaxManagerFactory::getManager($address, (int) $idTaxRulesGroup)->getTaxCalculator();

        return round($calculator->removeTaxes($amount), 6);
    }

    /**
     * Copertina del prodotto a partire da un'immagine del modello.
     */
    public static function setCoverImage($idProduct, $source)
    {
        if (!is_file($source)) {
            return false;
        }
        $image = new Image();
        $image->id_product = (int) $idProduct;
        $image->position = Image::getHighestPosition((int) $idProduct) + 1;
        $image->cover = Image::getCover((int) $idProduct) ? 0 : 1;
        if (!$image->add()) {
            return false;
        }
        $base = $image->getPathForCreation();
        $ok = ImageManager::resize($source, $base . '.jpg');
        foreach (ImageType::getImagesTypes('products') as $type) {
            $ok = ImageManager::resize($source, $base . '-' . stripslashes($type['name']) . '.jpg', (int) $type['width'], (int) $type['height']) && $ok;
        }
        if (!$ok) {
            $image->delete();

            return false;
        }

        return true;
    }
}

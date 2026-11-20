<?php
/**
 * Modello grafico di una gift card: immagine di sfondo (formato carta di credito), colore del testo,
 * nome e occasione tradotti.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmGiftCardTemplate extends ObjectModel
{
    /** dimensioni a cui vengono portate le immagini caricate (rapporto ISO/IEC 7810 ID-1) */
    const IMAGE_WIDTH = 1200;
    const IMAGE_HEIGHT = 756;

    /** @var string nome del file in img/dotmgiftcard/ */
    public $image;
    public $text_color = '#ffffff';
    public $is_default = 0;
    public $active = 1;
    public $position = 0;
    public $date_add;
    public $date_upd;

    /** @var string|array */
    public $name;
    /** @var string|array */
    public $occasion;

    public static $definition = [
        'table' => 'dotmgiftcard_template',
        'primary' => 'id_dotmgiftcard_template',
        'multilang' => true,
        'fields' => [
            'image' => ['type' => self::TYPE_STRING, 'validate' => 'isFileName', 'size' => 64],
            'text_color' => ['type' => self::TYPE_STRING, 'validate' => 'isColor', 'size' => 7],
            'is_default' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'name' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'size' => 128, 'required' => true],
            'occasion' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'size' => 64],
        ],
    ];

    public static function getImageDir()
    {
        return _PS_IMG_DIR_ . 'dotmgiftcard/';
    }

    public static function getImageBaseUrl()
    {
        // URL assoluto (serve anche nelle email e nel cron, dove getMediaLink può omettere il protocollo)
        $link = Context::getContext()->link ?: new Link();

        return $link->getBaseLink(null, true) . 'img/dotmgiftcard/';
    }

    public function getImagePath()
    {
        $path = self::getImageDir() . $this->image;

        return $this->image && is_file($path) ? $path : '';
    }

    public function getImageUrl()
    {
        return $this->getImagePath() ? self::getImageBaseUrl() . $this->image . '?v=' . strtotime((string) $this->date_upd) : '';
    }

    public function add($autoDate = true, $nullValues = false)
    {
        if (!$this->position) {
            $this->position = 1 + (int) Db::getInstance()->getValue('SELECT MAX(`position`) FROM `' . _DB_PREFIX_ . 'dotmgiftcard_template`');
        }
        $ok = parent::add($autoDate, $nullValues);
        if ($ok && ($this->is_default || !self::getDefaultId(false))) {
            $this->makeDefault();
        }

        return $ok;
    }

    public function update($nullValues = false)
    {
        $ok = parent::update($nullValues);
        if ($ok && $this->is_default) {
            $this->makeDefault();
        }

        return $ok;
    }

    public function delete()
    {
        $image = $this->getImagePath();
        $wasDefault = (bool) $this->is_default;
        if (!parent::delete()) {
            return false;
        }
        if ($image) {
            @unlink($image);
        }
        if ($wasDefault) {
            $next = (int) Db::getInstance()->getValue(
                'SELECT `id_dotmgiftcard_template` FROM `' . _DB_PREFIX_ . 'dotmgiftcard_template` ORDER BY `active` DESC, `position` ASC'
            );
            if ($next) {
                (new self($next))->makeDefault();
            }
        }

        return true;
    }

    /**
     * Un solo modello predefinito: usato per la copertina dei prodotti e preselezionato nella pagina di acquisto.
     */
    public function makeDefault()
    {
        $db = Db::getInstance();
        $table = _DB_PREFIX_ . 'dotmgiftcard_template';
        $this->is_default = 1;

        return $db->execute('UPDATE `' . $table . '` SET `is_default` = 0 WHERE `id_dotmgiftcard_template` != ' . (int) $this->id)
            && $db->execute('UPDATE `' . $table . '` SET `is_default` = 1, `active` = 1 WHERE `id_dotmgiftcard_template` = ' . (int) $this->id);
    }

    public static function getDefaultId($activeOnly = true)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT `id_dotmgiftcard_template` FROM `' . _DB_PREFIX_ . 'dotmgiftcard_template`
            ' . ($activeOnly ? 'WHERE `active` = 1' : '') . '
            ORDER BY `is_default` DESC, `position` ASC'
        );
    }

    /**
     * @return array [{id, name, occasion, image, color, is_default}]
     */
    public static function getList($idLang, $activeOnly = true)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT t.*, tl.`name`, tl.`occasion`
            FROM `' . _DB_PREFIX_ . 'dotmgiftcard_template` t
            LEFT JOIN `' . _DB_PREFIX_ . 'dotmgiftcard_template_lang` tl
                ON tl.`id_dotmgiftcard_template` = t.`id_dotmgiftcard_template` AND tl.`id_lang` = ' . (int) $idLang . '
            ' . ($activeOnly ? 'WHERE t.`active` = 1' : '') . '
            ORDER BY t.`is_default` DESC, t.`position` ASC, t.`id_dotmgiftcard_template` ASC'
        );
        $base = self::getImageBaseUrl();
        $list = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!$row['image'] || !is_file(self::getImageDir() . $row['image'])) {
                continue;
            }
            $list[] = [
                'id' => (int) $row['id_dotmgiftcard_template'],
                'name' => (string) $row['name'],
                'occasion' => trim((string) $row['occasion']),
                'image' => $base . $row['image'] . '?v=' . strtotime((string) $row['date_upd']),
                'color' => $row['text_color'] ?: '#ffffff',
                'is_default' => (bool) $row['is_default'],
            ];
        }

        return $list;
    }

    /**
     * Ritaglia al centro e ridimensiona un'immagine (JPEG, PNG, WebP o GIF) nel formato della carta,
     * salvandola come JPEG nella cartella dei modelli.
     *
     * @return string|false nome del file creato
     */
    public static function storeImage($source)
    {
        if (!function_exists('imagecreatetruecolor') || !is_file($source)) {
            return false;
        }
        $info = @getimagesize($source);
        if (!$info || $info[0] < 50 || $info[1] < 50) {
            return false;
        }
        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                $src = @imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $src = @imagecreatefrompng($source);
                break;
            case IMAGETYPE_GIF:
                $src = @imagecreatefromgif($source);
                break;
            default:
                $src = defined('IMAGETYPE_WEBP') && $info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')
                    ? @imagecreatefromwebp($source)
                    : false;
        }
        if (!$src) {
            return false;
        }

        $dir = self::getImageDir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $ratio = self::IMAGE_WIDTH / self::IMAGE_HEIGHT;
        if ($w / $h > $ratio) {
            $cropH = $h;
            $cropW = (int) round($h * $ratio);
        } else {
            $cropW = $w;
            $cropH = (int) round($w / $ratio);
        }
        $x = (int) floor(($w - $cropW) / 2);
        $y = (int) floor(($h - $cropH) / 2);

        $dst = imagecreatetruecolor(self::IMAGE_WIDTH, self::IMAGE_HEIGHT);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, $x, $y, self::IMAGE_WIDTH, self::IMAGE_HEIGHT, $cropW, $cropH);

        $name = 'tpl-' . strtolower(Tools::passwdGen(12, 'ALPHANUMERIC')) . '.jpg';
        $ok = imagejpeg($dst, $dir . $name, 90);
        imagedestroy($src);
        imagedestroy($dst);

        return $ok ? $name : false;
    }
}

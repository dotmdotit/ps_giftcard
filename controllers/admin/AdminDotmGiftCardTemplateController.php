<?php
/**
 * Catalogo → Modelli gift card: immagini di sfondo, colore del testo, nome e occasione.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'dotmgiftcard/dotmgiftcard.php';

class AdminDotmGiftCardTemplateController extends ModuleAdminController
{
    /** @var Dotmgiftcard */
    public $module;

    /** @var string nome del file caricato in questa richiesta */
    protected $dgcUploaded = '';

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'dotmgiftcard_template';
        $this->className = 'DotmGiftCardTemplate';
        $this->identifier = 'id_dotmgiftcard_template';
        $this->lang = true;
        $this->_defaultOrderBy = 'position';
        $this->_defaultOrderWay = 'ASC';

        parent::__construct();

        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->bulk_actions = [
            'delete' => ['text' => 'Elimina selezionati', 'confirm' => 'Eliminare i modelli selezionati?', 'icon' => 'icon-trash'],
        ];

        $this->fields_list = [
            'id_dotmgiftcard_template' => ['title' => 'ID', 'align' => 'center', 'class' => 'fixed-width-xs'],
            'image' => ['title' => 'Immagine', 'callback' => 'renderThumb', 'search' => false, 'orderby' => false],
            'name' => ['title' => 'Nome', 'filter_key' => 'b!name'],
            'occasion' => ['title' => 'Occasione', 'filter_key' => 'b!occasion'],
            'text_color' => ['title' => 'Testo', 'callback' => 'renderColor', 'search' => false, 'orderby' => false, 'align' => 'center'],
            'position' => ['title' => 'Ordine', 'align' => 'center', 'class' => 'fixed-width-sm'],
            'is_default' => ['title' => 'Predefinito', 'type' => 'bool', 'align' => 'center', 'class' => 'fixed-width-sm'],
            'active' => ['title' => 'Attivo', 'active' => 'status', 'type' => 'bool', 'align' => 'center', 'class' => 'fixed-width-sm'],
        ];
    }

    public function renderThumb($value)
    {
        if (!$value || !is_file(DotmGiftCardTemplate::getImageDir() . $value)) {
            return '—';
        }

        return '<img src="' . htmlspecialchars(DotmGiftCardTemplate::getImageBaseUrl() . $value, ENT_QUOTES, 'UTF-8') . '" alt="" style="width:120px;border-radius:6px;">';
    }

    public function renderColor($value)
    {
        $value = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        return '<span style="display:inline-block;width:22px;height:22px;border-radius:50%;border:1px solid #ccc;background:' . $value . ';" title="' . $value . '"></span>';
    }

    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_btn['config'] = [
            'href' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->module->name]),
            'desc' => 'Impostazioni',
            'icon' => 'process-icon-cogs',
        ];
        parent::initPageHeaderToolbar();
    }

    public function renderForm()
    {
        /** @var DotmGiftCardTemplate $obj */
        $obj = $this->loadObject(true);
        $current = Validate::isLoadedObject($obj) && $obj->getImageUrl()
            ? '<img src="' . htmlspecialchars($obj->getImageUrl(), ENT_QUOTES, 'UTF-8') . '" alt="" style="width:320px;max-width:100%;border-radius:10px;">'
            : '';

        $this->fields_form = [
            'legend' => ['title' => 'Modello gift card', 'icon' => 'icon-picture'],
            'input' => [
                ['type' => 'text', 'label' => 'Nome', 'name' => 'name', 'lang' => true, 'required' => true, 'desc' => 'Mostrato sotto l\'anteprima nella pagina di acquisto e nel riepilogo del carrello.'],
                ['type' => 'text', 'label' => 'Occasione', 'name' => 'occasion', 'lang' => true, 'desc' => 'Ad esempio Compleanno, Natale. I modelli con la stessa occasione si filtrano insieme nella pagina di acquisto.'],
                [
                    'type' => 'file',
                    'label' => 'Immagine',
                    'name' => 'image_file',
                    'required' => !Validate::isLoadedObject($obj),
                    'image' => $current ?: false,
                    'display_image' => true,
                    'desc' => 'JPEG, PNG o WebP, almeno 1200×756 px (formato carta di credito). Viene ritagliata al centro. Lascia libero l\'angolo in basso a sinistra: lì compare l\'importo; in alto a sinistra il nome del negozio.',
                ],
                ['type' => 'color', 'label' => 'Colore del testo', 'name' => 'text_color', 'desc' => 'Colore di nome del negozio e importo sopra l\'immagine (#rrggbb).'],
                ['type' => 'text', 'label' => 'Ordine', 'name' => 'position', 'class' => 'fixed-width-sm', 'desc' => 'I modelli sono mostrati in ordine crescente.'],
                [
                    'type' => 'switch', 'label' => 'Predefinito', 'name' => 'is_default', 'is_bool' => true,
                    'desc' => 'Preselezionato nella pagina di acquisto e usato per le gift card senza modello.',
                    'values' => [['id' => 'def_on', 'value' => 1, 'label' => 'Sì'], ['id' => 'def_off', 'value' => 0, 'label' => 'No']],
                ],
                [
                    'type' => 'switch', 'label' => 'Attivo', 'name' => 'active', 'is_bool' => true,
                    'values' => [['id' => 'act_on', 'value' => 1, 'label' => 'Sì'], ['id' => 'act_off', 'value' => 0, 'label' => 'No']],
                ],
            ],
            'submit' => ['title' => 'Salva'],
        ];
        if (!Validate::isLoadedObject($obj)) {
            $this->fields_value = ['text_color' => '#ffffff', 'active' => 1, 'position' => 0];
        }

        return parent::renderForm();
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitAdd' . $this->table)) {
            $color = strtolower(trim((string) Tools::getValue('text_color')));
            if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
                $_POST['text_color'] = '#ffffff';
            }
            $hasFile = isset($_FILES['image_file']) && is_uploaded_file($_FILES['image_file']['tmp_name']);
            if ($hasFile) {
                if ((int) $_FILES['image_file']['size'] > 10 * 1024 * 1024) {
                    $this->errors[] = 'L\'immagine supera i 10 MB.';
                } else {
                    $stored = DotmGiftCardTemplate::storeImage($_FILES['image_file']['tmp_name']);
                    if (!$stored) {
                        $this->errors[] = 'Immagine non valida: usa un file JPEG, PNG o WebP.';
                    } else {
                        $this->dgcUploaded = $stored;
                    }
                }
            } elseif (!(int) Tools::getValue($this->identifier)) {
                $this->errors[] = 'Carica l\'immagine del modello.';
            }
            if ($this->errors) {
                $this->display = 'edit';

                return false;
            }
        }

        return parent::postProcess();
    }

    protected function afterAdd($object)
    {
        return $this->attachUploadedImage($object);
    }

    protected function afterUpdate($object)
    {
        return $this->attachUploadedImage($object);
    }

    protected function attachUploadedImage($object)
    {
        if ($this->dgcUploaded === '') {
            return true;
        }
        $old = $object->getImagePath();
        $object->image = $this->dgcUploaded;
        if ($object->update()) {
            if ($old && basename($old) !== $this->dgcUploaded) {
                @unlink($old);
            }

            return true;
        }

        return false;
    }
}

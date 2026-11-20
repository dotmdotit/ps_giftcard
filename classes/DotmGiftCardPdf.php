<?php
/**
 * PDF stampabile della gift card (A4): carta con importo, dedica, messaggio, codice e istruzioni.
 * Usa TCPDF, già incluso in PrestaShop; nessuna estensione PHP aggiuntiva.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmGiftCardPdf
{
    /** @var Dotmgiftcard */
    protected $module;

    public function __construct(Dotmgiftcard $module)
    {
        $this->module = $module;
    }

    public static function getFileName(DotmGiftCardCard $card)
    {
        return 'gift-card-' . preg_replace('/[^A-Za-z0-9\-]/', '', $card->code ?: (string) $card->id) . '.pdf';
    }

    /**
     * @return string contenuto del PDF ('' se TCPDF non è disponibile)
     */
    public function render(DotmGiftCardCard $card)
    {
        if (!class_exists('TCPDF')) {
            return '';
        }
        $m = $this->module;
        $idLang = (int) $card->id_lang;
        $shopName = (string) Configuration::get('PS_SHOP_NAME', null, null, (int) $card->id_shop);
        $template = new DotmGiftCardTemplate((int) $card->id_template);
        if (!Validate::isLoadedObject($template) || !$template->getImagePath()) {
            $template = new DotmGiftCardTemplate(DotmGiftCardTemplate::getDefaultId(false));
        }
        $textColor = $this->rgb($template->text_color ?: '#ffffff');
        $accent = $this->rgb($m->getColor(true));
        $amount = (new DotmGiftCardManager($m))->formatAmount($card->amount, (int) $card->id_currency);
        $font = 'dejavusans';

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator($shopName);
        $pdf->SetAuthor($shopName);
        $pdf->SetTitle('Gift card ' . $card->code);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 18, 20);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();

        // intestazione
        $pdf->SetTextColor(102, 102, 102);
        $pdf->SetFont($font, 'B', 10);
        $pdf->Cell(0, 6, Tools::strtoupper($shopName), 0, 1, 'L');
        $pdf->SetTextColor($accent[0], $accent[1], $accent[2]);
        $pdf->SetFont($font, 'B', 24);
        $pdf->Cell(0, 13, $m->t('pdf_title', [], $idLang), 0, 1, 'L');
        $pdf->Ln(3);

        // carta
        $x = 20;
        $y = $pdf->GetY();
        $w = 170;
        $h = round($w * DotmGiftCardTemplate::IMAGE_HEIGHT / DotmGiftCardTemplate::IMAGE_WIDTH, 2);
        if ($template->getImagePath()) {
            $pdf->Image($template->getImagePath(), $x, $y, $w, $h, 'JPG', '', '', true, 200);
        } else {
            $pdf->Rect($x, $y, $w, $h, 'F', [], [34, 34, 34]);
        }
        $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
        $pdf->SetFont($font, 'B', 12);
        $pdf->SetXY($x + 9, $y + 8);
        $pdf->Cell($w - 18, 7, $shopName, 0, 0, 'L');
        $pdf->SetFont($font, '', 10);
        $pdf->SetXY($x + 9, $y + $h - 30);
        $pdf->Cell($w - 18, 6, Tools::strtoupper($m->t('card_label', [], $idLang)), 0, 0, 'L');
        $pdf->SetFont($font, 'B', 32);
        $pdf->SetXY($x + 8, $y + $h - 23);
        $pdf->Cell($w - 18, 15, $amount, 0, 0, 'L');
        $pdf->SetY($y + $h + 8);

        // dedica e messaggio
        $pdf->SetTextColor(34, 34, 34);
        if ($card->recipient_name !== '') {
            $pdf->SetFont($font, 'B', 14);
            $pdf->Cell(0, 8, $m->t('pdf_for', ['name' => $card->recipient_name], $idLang), 0, 1, 'L');
        }
        if ($card->sender_name !== '') {
            $pdf->SetFont($font, '', 11);
            $pdf->SetTextColor(102, 102, 102);
            $pdf->Cell(0, 7, $m->t('pdf_from', ['name' => $card->sender_name], $idLang), 0, 1, 'L');
        }
        if (trim((string) $card->message) !== '') {
            $pdf->Ln(2);
            $pdf->SetTextColor(34, 34, 34);
            $pdf->SetFont($font, 'I', 11);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->setCellPaddings(4, 3, 4, 3);
            $pdf->MultiCell(0, 6, trim((string) $card->message), 0, 'L', true, 1, '', '', true, 0, false, true, 0, 'T', false);
            $pdf->setCellPaddings(0, 0, 0, 0);
        }
        $pdf->Ln(6);

        // codice
        $boxY = $pdf->GetY();
        $pdf->SetDrawColor($accent[0], $accent[1], $accent[2]);
        $pdf->SetLineWidth(0.6);
        $pdf->RoundedRect(20, $boxY, 170, 26, 3, '1111', 'D');
        $pdf->SetXY(26, $boxY + 3);
        $pdf->SetTextColor(102, 102, 102);
        $pdf->SetFont($font, '', 9);
        $pdf->Cell(158, 5, Tools::strtoupper($m->t('code_label', [], $idLang)), 0, 1, 'C');
        $pdf->SetX(26);
        $pdf->SetTextColor(17, 17, 17);
        $pdf->SetFont('courier', 'B', 22);
        $pdf->Cell(158, 13, $card->code, 0, 1, 'C');
        $pdf->SetY($boxY + 32);

        // istruzioni
        $pdf->SetFont($font, '', 10);
        $pdf->SetTextColor(68, 68, 68);
        $shopUrl = Context::getContext()->link->getPageLink('index', true, $idLang, null, false, (int) $card->id_shop);
        $lines = [
            $m->t('pdf_amount', ['amount' => $amount], $idLang),
            $card->date_expire
                ? $m->t('valid_until', ['date' => Tools::displayDate($card->date_expire)], $idLang)
                : $m->t('no_expiry', [], $idLang),
            $m->t('pdf_how', ['url' => $shopUrl], $idLang),
            $m->t($card->partial_use ? 'usage_partial' : 'usage_single', [], $idLang),
            $m->t('pdf_balance', ['url' => Context::getContext()->link->getModuleLink($m->name, 'balance', [], true, $idLang, (int) $card->id_shop)], $idLang),
        ];
        foreach ($lines as $line) {
            $pdf->MultiCell(0, 5.5, $line, 0, 'L', false, 1);
        }

        return (string) $pdf->Output(self::getFileName($card), 'S');
    }

    protected function rgb($hex)
    {
        $hex = ltrim((string) $hex, '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [34, 34, 34];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}

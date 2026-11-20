<?php
/**
 * Email delle gift card: al destinatario, all'acquirente (stampa a casa) e conferma di consegna all'acquirente.
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmGiftCardMailer
{
    /** @var Dotmgiftcard */
    protected $module;

    public function __construct(Dotmgiftcard $module)
    {
        $this->module = $module;
    }

    public function sendToRecipient(DotmGiftCardCard $card)
    {
        if (!Validate::isEmail($card->recipient_email)) {
            return false;
        }
        $idLang = $this->getMailLang((int) $card->id_lang);
        $subject = $card->sender_name !== ''
            ? $this->module->t('mail_subject_recipient_from', ['sender' => $card->sender_name], $idLang)
            : $this->module->t('mail_subject_recipient', [], $idLang);

        return $this->send($card, $idLang, 'dotmgiftcard_recipient', $subject, $card->recipient_email, $card->recipient_name, true);
    }

    public function sendToBuyer(DotmGiftCardCard $card)
    {
        $customer = new Customer((int) $card->id_customer);
        if (!Validate::isLoadedObject($customer) || !Validate::isEmail($customer->email)) {
            return false;
        }
        $idLang = $this->getMailLang((int) $card->id_lang);

        return $this->send(
            $card,
            $idLang,
            'dotmgiftcard_buyer',
            $this->module->t('mail_subject_buyer', [], $idLang),
            $customer->email,
            trim($customer->firstname . ' ' . $customer->lastname),
            true
        );
    }

    public function notifyBuyerSent(DotmGiftCardCard $card)
    {
        $customer = new Customer((int) $card->id_customer);
        if (!Validate::isLoadedObject($customer) || !Validate::isEmail($customer->email)
            || strtolower($customer->email) === strtolower($card->recipient_email)) {
            return false;
        }
        $idLang = $this->getMailLang((int) $card->id_lang);

        return $this->send(
            $card,
            $idLang,
            'dotmgiftcard_sent',
            $this->module->t('mail_subject_sent', ['recipient' => $card->recipient_name ?: $card->recipient_email], $idLang),
            $customer->email,
            trim($customer->firstname . ' ' . $customer->lastname),
            false
        );
    }

    /**
     * Lingua per le email: quella della carta se il modulo ha i template, altrimenti inglese o italiano.
     */
    protected function getMailLang($idLang)
    {
        foreach ([(int) $idLang, (int) Language::getIdByIso('en'), (int) Language::getIdByIso('it')] as $id) {
            $iso = $id ? Language::getIsoById($id) : '';
            if ($iso && is_file($this->module->getLocalPath() . 'mails/' . $iso . '/dotmgiftcard_recipient.html')) {
                return $id;
            }
        }

        return (int) $idLang;
    }

    protected function send(DotmGiftCardCard $card, $idLang, $template, $subject, $to, $toName, $attachPdf)
    {
        $attachment = null;
        if ($attachPdf) {
            $pdf = (new DotmGiftCardPdf($this->module))->render($card);
            if ($pdf !== '') {
                $attachment = [
                    'content' => $pdf,
                    'name' => DotmGiftCardPdf::getFileName($card),
                    'mime' => 'application/pdf',
                ];
            }
        }

        $ok = Mail::Send(
            (int) $idLang,
            $template,
            $subject,
            $this->getVars($card, (int) $idLang),
            $to,
            $toName ?: null,
            null,
            null,
            $attachment,
            null,
            $this->module->getLocalPath() . 'mails/',
            false,
            (int) $card->id_shop
        );
        if (!$ok) {
            PrestaShopLogger::addLog('dotmgiftcard: email ' . $template . ' non inviata (carta #' . (int) $card->id . ')', 3, null, 'Order', (int) $card->id_order);
        }

        return (bool) $ok;
    }

    public function getVars(DotmGiftCardCard $card, $idLang)
    {
        $m = $this->module;
        $manager = new DotmGiftCardManager($m);
        $template = new DotmGiftCardTemplate((int) $card->id_template);
        if (!Validate::isLoadedObject($template) || !$template->getImagePath()) {
            $template = new DotmGiftCardTemplate(DotmGiftCardTemplate::getDefaultId(false));
        }
        $customer = new Customer((int) $card->id_customer);
        $buyer = Validate::isLoadedObject($customer) ? trim($customer->firstname . ' ' . $customer->lastname) : '';
        $sender = $card->sender_name !== '' ? $card->sender_name : $buyer;
        $message = trim((string) $card->message);
        $expire = $card->date_expire
            ? $m->t('valid_until', ['date' => Tools::displayDate($card->date_expire)], $idLang)
            : $m->t('no_expiry', [], $idLang);
        $usage = $m->t($card->partial_use ? 'usage_partial' : 'usage_single', [], $idLang);
        $e = function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        };
        $link = Context::getContext()->link;

        return [
            '{gc_code}' => $card->code,
            '{gc_amount}' => $manager->formatAmount($card->amount, (int) $card->id_currency),
            '{gc_expire}' => $expire,
            '{gc_usage}' => $usage,
            '{gc_sender}' => $sender,
            '{gc_sender_html}' => $e($sender),
            '{gc_recipient}' => $card->recipient_name,
            '{gc_recipient_html}' => $e($card->recipient_name),
            '{gc_recipient_email}' => $card->recipient_email,
            '{gc_buyer_html}' => $e($buyer),
            '{gc_message_txt}' => $message !== '' ? $message : '—',
            '{gc_message_html}' => $message !== ''
                ? '<p style="margin:0 0 18px;padding:14px 18px;border-left:4px solid ' . $m->getColor(true) . ';background:#F6F6F6;font-style:italic;">' . nl2br($e($message)) . '</p>'
                : '',
            '{gc_image}' => $template->getImageUrl(),
            '{gc_text_color}' => $template->text_color ?: '#ffffff',
            '{gc_color}' => $m->getColor(true),
            '{gc_shop_link}' => $link->getPageLink('index', true, (int) $idLang, null, false, (int) $card->id_shop),
            '{gc_balance_url}' => $link->getModuleLink($m->name, 'balance', [], true, (int) $idLang, (int) $card->id_shop),
            '{gc_pdf_url}' => $link->getModuleLink($m->name, 'pdf', ['id' => (int) $card->id, 'key' => $card->getKey()], true, (int) $idLang, (int) $card->id_shop),
            '{gc_send_date}' => $card->send_date ? Tools::displayDate($card->send_date) : '',
        ];
    }
}

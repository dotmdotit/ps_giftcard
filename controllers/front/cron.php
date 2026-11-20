<?php
/**
 * Invio programmato delle gift card via email: da chiamare ogni giorno con il token del modulo.
 * Esempio: 0 8 * * * curl -s "https://negozio.it/module/dotmgiftcard/cron?token=..."
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class DotmgiftcardCronModuleFrontController extends ModuleFrontController
{
    /** @var Dotmgiftcard */
    public $module;

    public function init()
    {
        parent::init();

        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');
        $token = (string) Configuration::getGlobalValue(Dotmgiftcard::CFG_CRON_TOKEN);
        if ($token === '' || !hash_equals($token, (string) Tools::getValue('token'))) {
            http_response_code(403);
            echo "invalid token\n";
            exit;
        }

        $sent = $this->module->getManager()->sendScheduled();
        echo 'ok: ' . (int) $sent . " sent\n";
        exit;
    }
}

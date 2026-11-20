<?php
/**
 * Testi del modulo (italiano, lingua di riferimento).
 * Segnaposto: {name}, {email}, {date}, {amount}, {n}, {url}, {sender}, {recipient}, {text}
 *
 * @author dotm.it
 * @license MIT (vedi LICENSE)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

return [
    // pagina di acquisto
    'buy_title' => 'Gift card',
    'buy_meta_title' => 'Regala una gift card',
    'buy_meta_description' => 'Scegli il modello, l’importo e come consegnarla: via email in una data a tua scelta, da stampare o per posta.',
    'buy_kicker' => 'Regala una gift card',
    'buy_intro' => 'Scegli il modello e l’importo, scrivi una dedica e decidi come consegnarla: via email nel giorno che preferisci, da stampare o per posta.',
    'buy_unavailable' => 'Le gift card non sono disponibili al momento.',
    'buy_note' => 'Il codice viene creato quando il pagamento è confermato.',
    'buy_link' => 'Regala una gift card',
    'step_template' => 'Scegli il modello',
    'step_delivery' => 'Come la consegniamo',
    'step_amount' => 'Scegli l’importo',
    'step_message' => 'Dedica',
    'filter_occasion' => 'Filtra per occasione',
    'filter_all' => 'Tutti',
    'amounts_digital' => 'Gift card digitale',
    'amounts_physical' => 'Carta fisica (spedizione per posta)',
    'field_recipient' => 'Per (nome del destinatario)',
    'field_sender' => 'Da (il tuo nome)',
    'field_email' => 'Email del destinatario',
    'field_email_confirm' => 'Ripeti l’email del destinatario',
    'field_date' => 'Data di invio',
    'field_date_help' => 'La gift card arriva la mattina del giorno scelto.',
    'field_message' => 'Messaggio',
    'optional' => 'facoltativo',
    'field_quantity' => 'Quantità',
    'btn_add' => 'Aggiungi al carrello',
    'preview' => 'Anteprima',
    'preview_for' => 'Per',
    'preview_from' => 'Da',
    'card_label' => 'Gift card',

    // modalità di consegna
    'delivery_email' => 'Via email al destinatario',
    'delivery_email_help' => 'Arriva al destinatario nel giorno che scegli, con la tua dedica e il PDF da stampare.',
    'delivery_print' => 'Da stampare',
    'delivery_print_help' => 'Ricevi tu la gift card in PDF subito dopo il pagamento, da stampare o inoltrare.',
    'delivery_post' => 'Per posta',
    'delivery_post_help' => 'Spediamo una carta regalo fisica all’indirizzo di consegna dell’ordine.',

    // errori
    'error_token' => 'La sessione è scaduta: ricarica la pagina e riprova.',
    'error_delivery' => 'Scegli come consegnare la gift card.',
    'error_amount' => 'Scegli un importo.',
    'error_template' => 'Scegli un modello.',
    'error_sender' => 'Inserisci il tuo nome.',
    'error_recipient' => 'Inserisci il nome del destinatario.',
    'error_name_chars' => 'I nomi non possono contenere i caratteri < > = { } # ;',
    'error_email' => 'Inserisci un’email valida per il destinatario.',
    'error_email_confirm' => 'Le due email del destinatario non coincidono.',
    'error_date' => 'Scegli una data di invio tra oggi e un anno.',
    'error_message' => 'Il messaggio può contenere al massimo {n} caratteri.',
    'error_message_chars' => 'Il messaggio contiene caratteri non ammessi.',
    'error_quantity' => 'Puoi aggiungere da 1 a {n} gift card alla volta.',
    'error_cart' => 'Non è stato possibile aggiungere la gift card al carrello. Riprova.',
    'vouchers_removed' => 'I codici sconto non si possono usare negli ordini che contengono gift card: li abbiamo rimossi dal carrello.',

    // riepilogo nel carrello, ordine e fattura
    'field_label' => 'Gift card',
    'sum_for' => 'Per: {name}',
    'sum_from' => 'Da: {name}',
    'sum_email_now' => 'Via email a {email} dopo il pagamento',
    'sum_email_on' => 'Via email a {email} il {date}',
    'sum_print' => 'PDF da stampare inviato a chi acquista',
    'sum_post' => 'Carta fisica per posta',
    'sum_template' => 'Modello: {name}',
    'sum_message' => 'Messaggio: “{text}”',

    // verifica saldo
    'balance_title' => 'Saldo gift card',
    'balance_intro' => 'Inserisci il codice della gift card per vedere il saldo disponibile e la scadenza.',
    'balance_link' => 'Verifica il saldo di una gift card',
    'balance_not_found' => 'Codice non trovato. Controlla di averlo scritto correttamente.',
    'balance_rate_limited' => 'Troppe verifiche in poco tempo. Riprova tra un’ora.',
    'btn_check' => 'Verifica il saldo',
    'code_label' => 'Codice',
    'col_balance' => 'Saldo disponibile',
    'col_amount' => 'Valore iniziale',
    'col_state' => 'Stato',
    'col_expire' => 'Scadenza',
    'no_expiry_short' => 'Nessuna',

    // area clienti
    'account_link' => 'Le mie gift card',
    'account_title' => 'Le mie gift card',
    'account_empty' => 'Non hai ancora gift card.',
    'account_bought' => 'Gift card acquistate',
    'account_received' => 'Gift card ricevute',
    'sent_on' => 'inviata il',
    'scheduled_on' => 'invio previsto il',
    'btn_pdf' => 'Scarica PDF',

    // stati
    'state_pending' => 'In attesa di pagamento',
    'state_active' => 'Attiva',
    'state_used' => 'Usata',
    'state_expired' => 'Scaduta',
    'state_cancelled' => 'Annullata',

    // uso del codice
    'valid_until' => 'Valida fino al {date}.',
    'no_expiry' => 'Senza scadenza.',
    'usage_partial' => 'Puoi usarla in più ordini: il saldo non speso resta disponibile sullo stesso codice.',
    'usage_single' => 'Si usa in un solo ordine: l’eventuale importo non speso non resta disponibile.',

    // email
    'mail_subject_recipient' => 'Hai ricevuto una gift card',
    'mail_subject_recipient_from' => '{sender} ti ha regalato una gift card',
    'mail_subject_buyer' => 'La tua gift card è pronta',
    'mail_subject_sent' => 'La gift card per {recipient} è stata consegnata',

    // PDF
    'pdf_title' => 'Gift card',
    'pdf_for' => 'Per {name}',
    'pdf_from' => 'Da {name}',
    'pdf_amount' => 'Valore: {amount}.',
    'pdf_how' => 'Come usarla: scegli i prodotti su {url} e inserisci il codice nel carrello, nel campo dei codici sconto.',
    'pdf_balance' => 'Saldo e scadenza: {url}',

    // catalogo e buoni
    'category_name' => 'Gift card',
    'product_name' => 'Gift card {amount}',
    'cart_rule_name' => 'Gift card',
    'payment_label' => 'Gift card',
    'tpl_classic' => 'Classica',
    'tpl_birthday' => 'Compleanno',
    'tpl_christmas' => 'Natale',
    'tpl_love' => 'Con amore',
    'tpl_minimal' => 'Essenziale',
    'occ_any' => 'Per ogni occasione',
    'occ_birthday' => 'Compleanno',
    'occ_christmas' => 'Natale',
    'occ_love' => 'Amore',
];

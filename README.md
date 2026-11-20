<p align="center"><img src="logo.svg" width="96" alt="dotmgiftcard"></p>

# dotmgiftcard — Gift card per PrestaShop

Modulo PrestaShop per vendere **gift card**. Il cliente sceglie un modello grafico e l'importo, scrive una dedica e decide come consegnarla: **via email al destinatario nel giorno scelto**, **in PDF da stampare** oppure **per posta**. Ogni gift card ha un codice da usare nel carrello. Il **saldo non speso resta sullo stesso codice**, e chiunque può verificarlo online.

> *English summary: PrestaShop gift card module. Customers pick a design and an amount, write a message and choose delivery: by email to the recipient on a chosen date, printable PDF, or physical card by post. Each card is a discount code whose unspent balance stays on the same code; a public page shows balance and expiry. Back office: amounts, designs, issued cards with balance, usage history, resend, cancel, manual cards. Front office and emails in Italian and English; back office in Italian.*

## Funzionalità

- **Pagina di acquisto `/gift-card`** con anteprima dal vivo della carta:
  - **modelli grafici** filtrabili per occasione (compleanno, Natale…); cinque sono inclusi e se ne possono caricare altri;
  - **modalità di consegna**:
    - **via email** al destinatario in una data scelta (fino a un anno), con la conferma dell'indirizzo;
    - **da stampare**: l'acquirente riceve subito il PDF;
    - **per posta**: carta fisica con spedizione;
  - **importi** a tagli fissi; dedica «Per», «Da» e messaggio fino a 300 caratteri; quantità.
- **Riepilogo nel carrello, nell'ordine e in fattura** (destinatario, mittente, consegna, modello, messaggio). Ogni gift card personalizzata è una riga separata.
- **Codice emesso al pagamento**: per esempio `GC-7KQ9-M2XA-P4TD`, senza caratteri ambigui (0/O, 1/I/L). Viene creato quando l'ordine passa a uno stato «pagato». È un normale buono sconto PrestaShop, valido per tutti i clienti.
- **Saldo residuo sullo stesso codice** (uso parziale): una carta da 50 € usata per 30 € resta valida per 20 €, con lo stesso codice. Si può impostare anche l'uso singolo.
- **Validità** in mesi per ogni taglio, oppure senza scadenza.
- **Email** (con il PDF in allegato):
  - al destinatario;
  - all'acquirente, per le carte da stampare;
  - conferma di consegna all'acquirente.
- **PDF A4** generato con TCPDF (già incluso in PrestaShop), senza estensioni PHP aggiuntive. Contiene carta, importo, dedica, codice e istruzioni.
- **Verifica del saldo `/gift-card/balance`**: inserendo il codice si vedono saldo, valore iniziale, stato e scadenza. Protezioni: limite di 15 verifiche per IP all'ora e un campo trappola nascosto.
- **Area clienti → Le mie gift card**: carte acquistate e ricevute, con saldo, stato, invio e PDF.
- **Invio programmato**:
  - con un cron giornaliero (URL con token);
  - in alternativa, con le visite al sito (al massimo una volta l'ora).
- **Annullamento automatico**: quando l'ordine passa a uno stato configurato (annullato, rimborsato):
  - le gift card acquistate e non usate vengono disattivate;
  - le gift card usate per pagare quell'ordine vengono **riaccreditate**.
- **Niente codici sconto negli ordini con gift card** (attivabile): non si comprano gift card con buoni o con altre gift card.
- **Gift card come pagamento** (attivabile): l'importo usato viene registrato come pagamento e non come sconto. Il totale e l'IVA restano sul prezzo pieno, come si fa per i buoni multiuso.
- **Back office**:
  - *Catalogo → Gift card*: tagli con importo, imposte, tipo digitale o fisico, validità e uso parziale;
  - *Catalogo → Modelli gift card*: immagine (ritagliata in automatico nel formato carta), colore del testo, occasione, modello predefinito;
  - *Ordini → Gift card*: tutte le carte emesse con saldo, consegna e stato, ed esportazione CSV. Nel dettaglio di ogni carta:
    - utilizzi negli ordini e registro delle azioni;
    - PDF, invio o reinvio, annullamento e riattivazione;
    - modifica di saldo, scadenza, email e data di invio;
  - **gift card manuali**, senza ordine: omaggi, rimborsi, vendite in negozio;
  - nella pagina dell'ordine, un riquadro con le gift card acquistate o usate in quell'ordine.
- **Aspetto neutro**, adatto a qualsiasi tema: colori grigi, un solo colore configurabile per pulsanti, email e PDF, variabili CSS ridefinibili dal tema.

## Requisiti

- PrestaShop **1.7.7** o successivo (provato su 9.1)
- PHP 7.2 o successivo, con l'estensione GD (per ritagliare le immagini dei modelli)
- URL amichevoli consigliati. Senza, le pagine restano raggiungibili su `index.php?fc=module&module=dotmgiftcard&controller=buy` (e `controller=balance`).

## Installazione

1. Scarica la repo (*Code → Download ZIP*, oppure una release).
2. La cartella del modulo **deve chiamarsi `dotmgiftcard`**. Lo ZIP di GitHub contiene la cartella `ps_giftcard-main`: rinominala e ricrea lo ZIP.
   ```sh
   unzip ps_giftcard-main.zip && mv ps_giftcard-main dotmgiftcard
   zip -r dotmgiftcard.zip dotmgiftcard -x '*.git*' -x '*/CLAUDE.md'
   ```
3. In back office: *Moduli → Gestione moduli → Carica un modulo* e scegli `dotmgiftcard.zip`.
   In alternativa copia la cartella in `modules/dotmgiftcard/` sul server e installa il modulo dal Gestore moduli.
4. Apri *Configura* e controlla le impostazioni (vedi sotto).
5. Svuota la cache in *Parametri avanzati → Prestazioni*, così le rotte `/gift-card` vengono registrate.

L'installazione:
- crea le tabelle `ps_dotmgiftcard_*`, la categoria «Gift card», cinque modelli e quattro tagli digitali (25, 50, 100, 150) pronti all'uso;
- attiva la funzione «personalizzazioni» di PrestaShop;
- aggiunge le voci di menu *Catalogo → Gift card*, *Catalogo → Modelli gift card* e *Ordini → Gift card*.

## Configurazione

| Impostazione | Predefinito | Note |
|---|---|---|
| Prefisso dei codici | `GC` | Da 1 a 8 lettere o cifre, oppure vuoto. |
| Lunghezza del codice | 12 | Caratteri casuali (8–20), esclusi prefisso e trattini. |
| Colore pulsanti | vuoto (`#2b2b2b`) | Formato `#rrggbb`. Usato anche in email e PDF. |
| Via email al destinatario / Da stampare / Per posta | sì | «Per posta» compare solo se esiste almeno un taglio fisico attivo. |
| Invio con le visite al sito | sì | Alternativa al cron: le carte programmate partono alla prima visita dell'ora. |
| Niente codici sconto con le gift card | sì | Se il carrello contiene gift card, i codici sconto vengono rimossi con un avviso. |
| Gift card come pagamento | no | Vedi «Note fiscali». |
| Stati che annullano | Annullato, Rimborsato | Annullano le gift card acquistate e non usate. |
| Riaccredita le gift card usate | sì | Se un ordine pagato con una gift card viene annullato, l'importo torna sulla carta. |

Ogni **taglio** (*Catalogo → Gift card*) ha:
- importo finale, cioè il valore del buono;
- regola fiscale;
- tipo digitale o fisico;
- validità in mesi (0 = senza scadenza);
- uso parziale o singolo.

## Dopo l'installazione

- **Collega la pagina** `https://tuonegozio.it/gift-card` nel menu o nel footer. La scheda prodotto di ogni taglio rimanda già lì, con l'importo selezionato.
- **Configura il cron** (consigliato) con il comando che trovi nella configurazione del modulo, ad esempio:
  ```
  0 8 * * * curl -s "https://tuonegozio.it/module/dotmgiftcard/cron?token=…" >/dev/null
  ```
- Controlla che il negozio invii le email (*Parametri avanzati → Email*).
- Fai una prova completa: acquisto con consegna via email nella data odierna, pagamento, ricezione dell'email con PDF, uso parziale del codice e verifica del saldo.

## Personalizzazione

Il modulo non contiene nulla di specifico per un negozio: tutto passa dalla configurazione o dagli override del tema.

- **Modelli**: carica le tue immagini in *Catalogo → Modelli gift card* (almeno 1200×756 px). In basso a sinistra compaiono l'importo e la scritta «Gift card», in alto a sinistra il nome del negozio: lascia libere queste zone e scegli un colore del testo che si legga.
- **Template**: copia i file da `views/templates/` in `themes/<tuo-tema>/modules/dotmgiftcard/views/templates/` e modificali lì.
- **Stile**: il CSS usa solo variabili `--dgc-*` (`--dgc-accent`, `--dgc-ink`, `--dgc-muted`, `--dgc-line`, `--dgc-soft`, `--dgc-card`, `--dgc-radius`, `--dgc-font-display`, …) definite su `.dgc`. Ridefiniscile nel CSS del tema, ad esempio:
  ```css
  .dgc { --dgc-font-display: "Playfair Display", serif; --dgc-radius: 4px; }
  ```
- **Testi**: sono in `lang/it.php` (riferimento) e `lang/en.php`. Per un'altra lingua aggiungi `lang/<iso>.php` con le stesse chiavi: basta tradurre quelle che vuoi, le mancanti arrivano dall'inglese. Le lingue senza file usano l'inglese.
- **Email**: in `mails/<iso>/`:
  - `dotmgiftcard_recipient.{html,txt}`: al destinatario;
  - `dotmgiftcard_buyer.{html,txt}`: carta da stampare;
  - `dotmgiftcard_sent.{html,txt}`: conferma di consegna.

  Le lingue senza cartella usano l'inglese. Variabili principali: `{gc_code}`, `{gc_amount}`, `{gc_expire}`, `{gc_sender}`, `{gc_recipient}`, `{gc_message_html}`, `{gc_image}`, `{gc_color}`, `{gc_balance_url}`, `{gc_pdf_url}`.

## Struttura

```
dotmgiftcard.php                        classe del modulo: installazione, configurazione, testi, hook
classes/DotmGiftCardTemplate.php        modelli grafici (tabelle ps_dotmgiftcard_template e _lang)
classes/DotmGiftCardProduct.php         tagli: impostazioni gift card dei prodotti, creazione dei prodotti
classes/DotmGiftCardCard.php            gift card emesse, saldo, utilizzi, registro
classes/DotmGiftCardManager.php         carrello, emissione, attivazione, consegna, utilizzo, annullamento
classes/DotmGiftCardMailer.php          email al destinatario e all'acquirente
classes/DotmGiftCardPdf.php             PDF stampabile (TCPDF)
controllers/front/buy.php               pagina di acquisto /gift-card
controllers/front/balance.php           verifica del saldo /gift-card/balance
controllers/front/account.php           area clienti → Le mie gift card
controllers/front/pdf.php               download del PDF (link con chiave)
controllers/front/cron.php              invio programmato
controllers/admin/                      Catalogo → Gift card e Modelli, Ordini → Gift card
lang/                                   testi (it, en)
mails/                                  email (it, en)
views/templates/{front,hook,admin}/     template Smarty
views/css/front.css, views/js/buy.js    stile e anteprima dal vivo
views/img/templates/                    immagini dei modelli inclusi
upgrade/                                script di aggiornamento tra versioni
```

Tabelle:
- `ps_dotmgiftcard_template` (+ `_lang`): modelli;
- `ps_dotmgiftcard_product`: tagli;
- `ps_dotmgiftcard_cart`: personalizzazioni nel carrello;
- `ps_dotmgiftcard_card`: carte emesse;
- `ps_dotmgiftcard_usage`: utilizzi;
- `ps_dotmgiftcard_log`: registro;
- `ps_dotmgiftcard_lookup`: limite delle verifiche.

Le immagini dei modelli sono in `img/dotmgiftcard/`, quindi sopravvivono agli aggiornamenti del modulo.

## Aggiornamento e disinstallazione

- Per aggiornare, sostituisci i file del modulo e avvia l'aggiornamento dal Gestore moduli: gli script in `upgrade/` aggiornano database e configurazione. Dopo modifiche ai template svuota la cache.
- **Attenzione: disinstallare il modulo cancella modelli, storico delle gift card emesse e impostazioni.** In dettaglio:
  - i codici già emessi restano validi come buoni sconto (*Catalogo → Sconti*), ma il saldo residuo e lo storico non sono più gestiti;
  - i tagli mai venduti vengono eliminati, quelli presenti in ordini restano nel catalogo disattivati.

  Prima di disinstallare in produzione esporta l'elenco da *Ordini → Gift card* (CSV) e fai un backup delle tabelle `ps_dotmgiftcard_*`. Per reinstallare senza perdere dati usa *Reimposta* solo dopo un backup.

## Note fiscali

Una gift card spendibile su tutto il catalogo è di solito un **buono multiuso**: la vendita del buono non è soggetta a IVA, che si applica quando il buono viene usato. Per questo:
- i tagli predefiniti non hanno imposte;
- l'opzione «Gift card come pagamento» registra l'uso della carta come pagamento, con l'ordine e l'IVA sul prezzo pieno.

Verifica con il tuo commercialista il trattamento corretto per il tuo caso: buoni monouso, regimi particolari, altri paesi.

## Licenza

Rilasciato con licenza [MIT](LICENSE).

## Autore

Sviluppato da [dotm.it](https://dotm.it).

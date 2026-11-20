{assign var=c value=$dgc_card}
<div class="row">
  <div class="col-lg-7">
    <div class="panel">
      <div class="panel-heading"><i class="icon-gift"></i> Gift card {if $c.code}<code>{$c.code|escape:'html':'UTF-8'}</code>{else}#{$c.id|intval}{/if}</div>
      <div class="row">
        <div class="col-sm-5">
          {if $c.image}
            <div style="position:relative;border-radius:10px;overflow:hidden;aspect-ratio:1200/756;background:#222 url('{$c.image|escape:'html':'UTF-8'}') center/cover;color:{$c.text_color|escape:'html':'UTF-8'};">
              <span style="position:absolute;left:12px;bottom:8px;font-size:22px;font-weight:800;">{$c.amount|escape:'html':'UTF-8'}</span>
            </div>
          {/if}
        </div>
        <div class="col-sm-7">
          <dl class="dl-horizontal" style="margin:0;">
            <dt>Stato</dt><dd><span class="label label-{if $c.state == 'active'}success{elseif $c.state == 'pending'}warning{elseif $c.state == 'cancelled' || $c.state == 'expired'}danger{else}default{/if}">{$c.state_label|escape:'html':'UTF-8'}</span></dd>
            <dt>Importo</dt><dd>{$c.amount|escape:'html':'UTF-8'}</dd>
            <dt>Saldo</dt><dd><strong>{$c.balance|escape:'html':'UTF-8'}</strong></dd>
            <dt>Uso</dt><dd>{if $c.partial_use}Parziale (saldo sullo stesso codice){else}Una sola volta{/if}</dd>
            <dt>Scadenza</dt><dd>{if $c.expire}{$c.expire|escape:'html':'UTF-8'}{else}Nessuna{/if}</dd>
            <dt>Consegna</dt><dd>{$c.delivery_label|escape:'html':'UTF-8'}</dd>
            {if $c.delivery == 'email'}
              <dt>Invio</dt><dd>{if $c.sent_at}Inviata il {$c.sent_at|escape:'html':'UTF-8'}{elseif $c.send_date}Previsto il {$c.send_date|escape:'html':'UTF-8'}{else}Non ancora inviata{/if}</dd>
            {/if}
            <dt>Ordine</dt><dd>{if $c.order_link}<a href="{$c.order_link|escape:'html':'UTF-8'}">{$c.order_reference|escape:'html':'UTF-8'}</a>{else}— (creata dal back office){/if}</dd>
            {if $c.customer}<dt>Acquirente</dt><dd>{$c.customer|escape:'html':'UTF-8'}</dd>{/if}
            <dt>Creata il</dt><dd>{$c.date|escape:'html':'UTF-8'}</dd>
            {if $c.cart_rule_link}<dt>Buono</dt><dd><a href="{$c.cart_rule_link|escape:'html':'UTF-8'}">Apri la regola carrello</a></dd>{/if}
          </dl>
        </div>
      </div>
      <hr>
      <dl class="dl-horizontal" style="margin:0;">
        <dt>Da</dt><dd>{$c.sender_name|escape:'html':'UTF-8'|default:'—'}</dd>
        <dt>Per</dt><dd>{$c.recipient_name|escape:'html':'UTF-8'|default:'—'}</dd>
        <dt>Email</dt><dd>{$c.recipient_email|escape:'html':'UTF-8'|default:'—'}</dd>
        <dt>Messaggio</dt><dd style="white-space:pre-line;">{$c.message|escape:'html':'UTF-8'|default:'—'}</dd>
      </dl>
    </div>

    <div class="panel">
      <div class="panel-heading"><i class="icon-shopping-cart"></i> Utilizzi</div>
      {if $dgc_usages}
        <table class="table">
          <thead><tr><th>Data</th><th>Ordine</th><th class="text-right">Importo</th><th></th></tr></thead>
          <tbody>
            {foreach $dgc_usages as $u}
              <tr>
                <td>{$u.date|escape:'html':'UTF-8'}</td>
                <td><a href="{$u.link|escape:'html':'UTF-8'}">{$u.reference|escape:'html':'UTF-8'}</a></td>
                <td class="text-right">{$u.amount|escape:'html':'UTF-8'}</td>
                <td>{if $u.restored}<span class="label label-info">riaccreditato</span>{/if}</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      {else}
        <p class="text-muted">Non ancora usata.</p>
      {/if}
    </div>

    <div class="panel">
      <div class="panel-heading"><i class="icon-time"></i> Registro</div>
      {if $dgc_log}
        <table class="table">
          <thead><tr><th>Data</th><th>Azione</th><th>Dettagli</th><th>Utente</th></tr></thead>
          <tbody>
            {foreach $dgc_log as $l}
              <tr><td>{$l.date|escape:'html':'UTF-8'}</td><td>{$l.action|escape:'html':'UTF-8'}</td><td>{$l.details|escape:'html':'UTF-8'}</td><td>{$l.employee|escape:'html':'UTF-8'|default:'—'}</td></tr>
            {/foreach}
          </tbody>
        </table>
      {else}
        <p class="text-muted">Nessuna voce.</p>
      {/if}
    </div>
  </div>

  <div class="col-lg-5">
    <div class="panel">
      <div class="panel-heading"><i class="icon-cogs"></i> Azioni</div>
      {if $c.status == 'active'}
        <p>
          <a class="btn btn-default" href="{$dgc_self|escape:'html':'UTF-8'}&amp;dgc_action=pdf" target="_blank"><i class="icon-file-text"></i> PDF</a>
          {if $c.delivery != 'post'}
            <a class="btn btn-default" href="{$dgc_self|escape:'html':'UTF-8'}&amp;dgc_action=send" onclick="return confirm('Inviare ora la gift card {if $c.delivery == 'email'}a {$c.recipient_email|escape:'javascript':'UTF-8'}{else}all\'acquirente{/if}?');"><i class="icon-envelope"></i> {if $c.sent_at}Invia di nuovo{else}Invia ora{/if}</a>
          {/if}
          <a class="btn btn-default" href="{$dgc_self|escape:'html':'UTF-8'}&amp;dgc_action=cancel" onclick="return confirm('Annullare la gift card? Il codice non sarà più utilizzabile.');"><i class="icon-ban"></i> Annulla</a>
        </p>
        <hr>
        <form method="post" action="{$dgc_self|escape:'html':'UTF-8'}" class="form-inline" style="margin-bottom:12px;">
          <input type="hidden" name="dgc_action" value="balance">
          <label>Saldo</label>
          <div class="input-group" style="width:160px;">
            <input type="text" name="balance" class="form-control" value="{$c.balance_raw|string_format:'%.2f'}">
            <span class="input-group-addon">{$c.currency_sign|escape:'html':'UTF-8'}</span>
          </div>
          <button type="submit" class="btn btn-default">Aggiorna</button>
        </form>
        <form method="post" action="{$dgc_self|escape:'html':'UTF-8'}" class="form-inline" style="margin-bottom:12px;">
          <input type="hidden" name="dgc_action" value="expire">
          <label>Scadenza</label>
          <input type="date" name="date_expire" class="form-control" value="{$c.expire_raw|escape:'html':'UTF-8'}">
          <button type="submit" class="btn btn-default">Aggiorna</button>
          <p class="help-block">Vuota = senza scadenza.</p>
        </form>
      {elseif $c.status == 'pending'}
        <p class="text-muted">In attesa del pagamento dell'ordine: il codice viene creato quando l'ordine passa a uno stato «pagato».</p>
        <a class="btn btn-default" href="{$dgc_self|escape:'html':'UTF-8'}&amp;dgc_action=activate" onclick="return confirm('Attivare la gift card senza attendere il pagamento?');"><i class="icon-check"></i> Attiva ora</a>
      {else}
        <p class="text-muted">Gift card annullata.</p>
        <a class="btn btn-default" href="{$dgc_self|escape:'html':'UTF-8'}&amp;dgc_action=activate" onclick="return confirm('Riattivare la gift card?');"><i class="icon-undo"></i> Riattiva</a>
      {/if}

      {if $c.delivery != 'post' || !$c.order_reference}
        <hr>
        <form method="post" action="{$dgc_self|escape:'html':'UTF-8'}">
          <input type="hidden" name="dgc_action" value="recipient">
          <div class="form-group">
            <label>Email destinatario</label>
            <input type="email" name="recipient_email" class="form-control" value="{$c.recipient_email|escape:'html':'UTF-8'}">
          </div>
          <div class="form-group">
            <label>Data di invio</label>
            <input type="date" name="send_date" class="form-control" value="{$c.send_date_raw|escape:'html':'UTF-8'}">
            <p class="help-block">Per le carte via email non ancora inviate: la carta parte in quella data.</p>
          </div>
          <button type="submit" class="btn btn-default">Salva dati di invio</button>
        </form>
      {/if}
    </div>
    <a class="btn btn-default" href="{$dgc_back|escape:'html':'UTF-8'}"><i class="icon-arrow-left"></i> Torna all'elenco</a>
  </div>
</div>

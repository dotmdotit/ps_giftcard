<div class="card mt-2" id="dotmgiftcard-admin-order">
  <div class="card-header">
    <h3 class="card-header-title"><i class="material-icons">card_giftcard</i> Gift card</h3>
  </div>
  <div class="card-body">
    {if $dgc_admin.bought}
      <p class="mb-1"><strong>Acquistate in questo ordine</strong></p>
      <table class="table table-sm mb-3">
        <thead><tr><th>Codice</th><th>Importo</th><th>Saldo</th><th>Consegna</th><th>Destinatario</th><th>Stato</th><th></th></tr></thead>
        <tbody>
          {foreach $dgc_admin.bought as $c}
            <tr>
              <td><code>{if $c.code}{$c.code|escape:'html':'UTF-8'}{else}—{/if}</code></td>
              <td>{$c.amount|escape:'html':'UTF-8'}</td>
              <td>{if $c.state == 'active'}{$c.balance|escape:'html':'UTF-8'}{else}—{/if}</td>
              <td>
                {$c.delivery_label|escape:'html':'UTF-8'}
                {if $c.delivery == 'email'}<br><small class="text-muted">{$c.recipient_email|escape:'html':'UTF-8'} · {if $c.sent_at}inviata il {$c.sent_at|escape:'html':'UTF-8'}{elseif $c.send_date}invio il {$c.send_date|escape:'html':'UTF-8'}{else}non inviata{/if}</small>{/if}
              </td>
              <td>{$c.recipient_name|escape:'html':'UTF-8'}</td>
              <td><span class="badge badge-{if $c.state == 'active'}success{elseif $c.state == 'pending'}warning{elseif $c.state == 'cancelled'}danger{else}secondary{/if}">{$c.state_label|escape:'html':'UTF-8'}</span></td>
              <td class="text-right"><a href="{$c.link|escape:'html':'UTF-8'}" class="btn btn-sm btn-outline-secondary">Dettagli</a></td>
            </tr>
          {/foreach}
        </tbody>
      </table>
      {if $dgc_admin.pending}
        <p class="text-muted small mb-3">I codici vengono creati e inviati quando l'ordine passa a uno stato «pagato».</p>
      {/if}
    {/if}
    {if $dgc_admin.used}
      <p class="mb-1"><strong>Usate per pagare questo ordine</strong></p>
      <ul class="mb-0">
        {foreach $dgc_admin.used as $u}
          <li><a href="{$u.link|escape:'html':'UTF-8'}"><code>{$u.code|escape:'html':'UTF-8'}</code></a> · {$u.amount|escape:'html':'UTF-8'}{if $u.restored} · <span class="badge badge-info">riaccreditata</span>{/if}</li>
        {/foreach}
      </ul>
    {/if}
  </div>
</div>

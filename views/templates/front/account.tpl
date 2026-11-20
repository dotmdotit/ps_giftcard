{extends file='customer/page.tpl'}

{block name='page_title'}
  {$dgc.t.account_title|escape:'html':'UTF-8'}
{/block}

{block name='page_content'}
<div class="dgc" id="dotmgiftcard-account"{if $dgc.css_vars} style="{$dgc.css_vars|escape:'html':'UTF-8'}"{/if}>
  {if !$dgc.bought && !$dgc.received}
    <div class="dgc-card">
      <p>{$dgc.t.account_empty|escape:'html':'UTF-8'}</p>
      <p><a class="dgc-btn" href="{$dgc.buy_url|escape:'html':'UTF-8'}">{$dgc.t.buy_link|escape:'html':'UTF-8'}</a></p>
    </div>
  {/if}

  {foreach ['received', 'bought'] as $group}
    {if $dgc.$group}
      <section class="dgc-card">
        <h2 class="dgc-title">{if $group == 'received'}{$dgc.t.account_received|escape:'html':'UTF-8'}{else}{$dgc.t.account_bought|escape:'html':'UTF-8'}{/if}</h2>
        <ul class="dgc-list">
          {foreach $dgc.$group as $c}
            <li class="dgc-item">
              <span class="dgc-thumb" style="{if $c.image}background-image:url('{$c.image|escape:'html':'UTF-8'}');{/if}color:{$c.text_color|escape:'html':'UTF-8'}">{$c.amount|escape:'html':'UTF-8'}</span>
              <div class="dgc-item-info">
                <strong>{if $c.state != 'pending'}<span class="dgc-code">{$c.code|escape:'html':'UTF-8'}</span>{else}{$c.amount|escape:'html':'UTF-8'}{/if}</strong>
                <span>
                  {if $group == 'bought' && $c.recipient_name}{$dgc.t.preview_for|escape:'html':'UTF-8'} {$c.recipient_name|escape:'html':'UTF-8'} · {/if}
                  {if $group == 'received' && $c.sender_name}{$dgc.t.preview_from|escape:'html':'UTF-8'} {$c.sender_name|escape:'html':'UTF-8'} · {/if}
                  {$c.delivery_label|escape:'html':'UTF-8'}
                  {if $c.delivery == 'email' && $group == 'bought'}
                    · {if $c.sent_at}{$dgc.t.sent_on|escape:'html':'UTF-8'} {$c.sent_at|escape:'html':'UTF-8'}{elseif $c.send_date}{$dgc.t.scheduled_on|escape:'html':'UTF-8'} {$c.send_date|escape:'html':'UTF-8'}{/if}
                  {/if}
                </span>
                <span>
                  <span class="dgc-badge dgc-badge-{$c.state|escape:'html':'UTF-8'}">{$c.state_label|escape:'html':'UTF-8'}</span>
                  {if $c.state == 'active'} {$dgc.t.col_balance|escape:'html':'UTF-8'}: <strong>{$c.balance|escape:'html':'UTF-8'}</strong>{/if}
                  {if $c.expire && ($c.state == 'active' || $c.state == 'expired')} · {$dgc.t.col_expire|escape:'html':'UTF-8'}: {$c.expire|escape:'html':'UTF-8'}{/if}
                </span>
              </div>
              {if $c.pdf_url}
                <a class="dgc-btn dgc-btn-ghost dgc-btn-sm" href="{$c.pdf_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$dgc.t.btn_pdf|escape:'html':'UTF-8'}</a>
              {/if}
            </li>
          {/foreach}
        </ul>
      </section>
    {/if}
  {/foreach}

  <p class="dgc-small dgc-muted">
    <a class="dgc-link" href="{$dgc.buy_url|escape:'html':'UTF-8'}">{$dgc.t.buy_link|escape:'html':'UTF-8'}</a>
    · <a class="dgc-link" href="{$dgc.balance_url|escape:'html':'UTF-8'}">{$dgc.t.balance_link|escape:'html':'UTF-8'}</a>
  </p>
</div>
{/block}

{extends file='page.tpl'}

{block name='page_title'}
  {$dgc.t.buy_title|escape:'html':'UTF-8'}
{/block}

{block name='page_content'}
<div class="dgc" id="dotmgiftcard"{if $dgc.css_vars} style="{$dgc.css_vars|escape:'html':'UTF-8'}"{/if}>

  <div class="dgc-intro">
    <span class="dgc-kicker">{$dgc.t.buy_kicker|escape:'html':'UTF-8'}</span>
    <p>{$dgc.t.buy_intro|escape:'html':'UTF-8'}</p>
  </div>

  {if $dgc.errors}
    <div class="dgc-alert dgc-alert-error" role="alert">
      {foreach $dgc.errors as $err}<p>{$err|escape:'html':'UTF-8'}</p>{/foreach}
    </div>
  {/if}

  {if !$dgc.available}
    <div class="dgc-card dgc-card-narrow"><p>{$dgc.t.buy_unavailable|escape:'html':'UTF-8'}</p></div>
  {else}
  <form method="post" action="{$dgc.action|escape:'html':'UTF-8'}" class="dgc-buy" id="dgc-buy" novalidate>
    <input type="hidden" name="token" value="{$dgc.token|escape:'html':'UTF-8'}">
    <div class="dgc-layout">
      <div class="dgc-main">

        {* 1. modello *}
        <section class="dgc-card dgc-step">
          <h2 class="dgc-title"><span class="dgc-step-n">1</span>{$dgc.t.step_template|escape:'html':'UTF-8'}</h2>
          {if $dgc.occasions}
            <div class="dgc-chips" role="group" aria-label="{$dgc.t.filter_occasion|escape:'html':'UTF-8'}" hidden data-dgc-filters>
              <button type="button" class="dgc-chip is-active" data-occasion="">{$dgc.t.filter_all|escape:'html':'UTF-8'}</button>
              {foreach $dgc.occasions as $occ}
                <button type="button" class="dgc-chip" data-occasion="{$occ|escape:'html':'UTF-8'}">{$occ|escape:'html':'UTF-8'}</button>
              {/foreach}
            </div>
          {/if}
          <div class="dgc-templates">
            {foreach $dgc.templates as $tpl}
              <label class="dgc-tpl" data-occasion="{$tpl.occasion|escape:'html':'UTF-8'}">
                <input type="radio" name="id_template" value="{$tpl.id|intval}"
                  data-image="{$tpl.image|escape:'html':'UTF-8'}" data-color="{$tpl.color|escape:'html':'UTF-8'}"
                  {if $tpl.id == $dgc.values.id_template}checked{/if}>
                <span class="dgc-tpl-img" style="background-image:url('{$tpl.image|escape:'html':'UTF-8'}')"></span>
                <span class="dgc-tpl-name">{$tpl.name|escape:'html':'UTF-8'}</span>
              </label>
            {/foreach}
          </div>
        </section>

        {* 2. consegna *}
        <section class="dgc-card dgc-step">
          <h2 class="dgc-title"><span class="dgc-step-n">2</span>{$dgc.t.step_delivery|escape:'html':'UTF-8'}</h2>
          <div class="dgc-options">
            {foreach $dgc.deliveries as $d}
              <label class="dgc-option">
                <input type="radio" name="delivery" value="{$d.value|escape:'html':'UTF-8'}" {if $d.value == $dgc.values.delivery}checked{/if}>
                <span>
                  <strong>{$d.label|escape:'html':'UTF-8'}</strong>
                  <small>{$d.help|escape:'html':'UTF-8'}</small>
                </span>
              </label>
            {/foreach}
          </div>
        </section>

        {* 3. importo *}
        <section class="dgc-card dgc-step">
          <h2 class="dgc-title"><span class="dgc-step-n">3</span>{$dgc.t.step_amount|escape:'html':'UTF-8'}</h2>
          {if $dgc.digital}
            <div class="dgc-amounts" data-kind="digital">
              {if $dgc.physical}<p class="dgc-small dgc-muted" data-dgc-nojs>{$dgc.t.amounts_digital|escape:'html':'UTF-8'}</p>{/if}
              {foreach $dgc.digital as $p}
                <label class="dgc-amount">
                  <input type="radio" name="id_product" value="{$p.id_product|intval}" data-amount="{$p.amount_label|escape:'html':'UTF-8'}" {if $p.id_product == $dgc.values.id_product}checked{/if}>
                  <span>{$p.amount_label|escape:'html':'UTF-8'}</span>
                </label>
              {/foreach}
            </div>
          {/if}
          {if $dgc.physical}
            <div class="dgc-amounts" data-kind="physical">
              {if $dgc.digital}<p class="dgc-small dgc-muted" data-dgc-nojs>{$dgc.t.amounts_physical|escape:'html':'UTF-8'}</p>{/if}
              {foreach $dgc.physical as $p}
                <label class="dgc-amount">
                  <input type="radio" name="id_product" value="{$p.id_product|intval}" data-amount="{$p.amount_label|escape:'html':'UTF-8'}" {if $p.id_product == $dgc.values.id_product}checked{/if}>
                  <span>{$p.amount_label|escape:'html':'UTF-8'}</span>
                </label>
              {/foreach}
            </div>
          {/if}
        </section>

        {* 4. dedica *}
        <section class="dgc-card dgc-step">
          <h2 class="dgc-title"><span class="dgc-step-n">4</span>{$dgc.t.step_message|escape:'html':'UTF-8'}</h2>
          <div class="dgc-grid">
            <label class="dgc-field">
              <span>{$dgc.t.field_recipient|escape:'html':'UTF-8'}</span>
              <input type="text" name="recipient_name" maxlength="64" value="{$dgc.values.recipient_name|escape:'html':'UTF-8'}" autocomplete="off" data-dgc-preview="recipient">
            </label>
            <label class="dgc-field">
              <span>{$dgc.t.field_sender|escape:'html':'UTF-8'}</span>
              <input type="text" name="sender_name" maxlength="64" value="{$dgc.values.sender_name|escape:'html':'UTF-8'}" required data-dgc-preview="sender">
            </label>
          </div>
          <div class="dgc-grid" data-show-for="email">
            <label class="dgc-field">
              <span>{$dgc.t.field_email|escape:'html':'UTF-8'}</span>
              <input type="email" name="recipient_email" maxlength="255" value="{$dgc.values.recipient_email|escape:'html':'UTF-8'}" autocomplete="off">
            </label>
            <label class="dgc-field">
              <span>{$dgc.t.field_email_confirm|escape:'html':'UTF-8'}</span>
              <input type="email" name="recipient_email_confirm" maxlength="255" value="{$dgc.values.recipient_email_confirm|escape:'html':'UTF-8'}" autocomplete="off">
            </label>
            <label class="dgc-field">
              <span>{$dgc.t.field_date|escape:'html':'UTF-8'}</span>
              <input type="date" name="send_date" min="{$dgc.today|escape:'html':'UTF-8'}" max="{$dgc.max_date|escape:'html':'UTF-8'}" value="{$dgc.values.send_date|escape:'html':'UTF-8'}">
              <small class="dgc-muted">{$dgc.t.field_date_help|escape:'html':'UTF-8'}</small>
            </label>
          </div>
          <label class="dgc-field">
            <span>{$dgc.t.field_message|escape:'html':'UTF-8'} <em class="dgc-muted">({$dgc.t.optional|escape:'html':'UTF-8'})</em></span>
            <textarea name="message" rows="4" maxlength="{$dgc.message_length|intval}" data-dgc-preview="message">{$dgc.values.message|escape:'html':'UTF-8'}</textarea>
            <small class="dgc-muted dgc-counter" data-dgc-counter data-max="{$dgc.message_length|intval}"></small>
          </label>
        </section>

        <div class="dgc-actions">
          <label class="dgc-qty">
            <span>{$dgc.t.field_quantity|escape:'html':'UTF-8'}</span>
            <input type="number" name="quantity" min="1" max="{$dgc.max_quantity|intval}" value="{$dgc.values.quantity|intval}">
          </label>
          <button type="submit" name="dgc_add" value="1" class="dgc-btn dgc-btn-confirm">{$dgc.t.btn_add|escape:'html':'UTF-8'}</button>
        </div>
        <p class="dgc-small dgc-muted">{$dgc.t.buy_note|escape:'html':'UTF-8'} <a class="dgc-link" href="{$dgc.balance_url|escape:'html':'UTF-8'}">{$dgc.t.balance_link|escape:'html':'UTF-8'}</a></p>
      </div>

      {* anteprima *}
      <aside class="dgc-aside" aria-label="{$dgc.t.preview|escape:'html':'UTF-8'}">
        <div class="dgc-aside-inner">
          <span class="dgc-kicker">{$dgc.t.preview|escape:'html':'UTF-8'}</span>
          <div class="dgc-giftcard" data-dgc-card>
            <span class="dgc-giftcard-shop">{$dgc.shop_name|escape:'html':'UTF-8'}</span>
            <span class="dgc-giftcard-label">{$dgc.t.card_label|escape:'html':'UTF-8'}</span>
            <span class="dgc-giftcard-amount" data-dgc-out="amount"></span>
          </div>
          <div class="dgc-dedication">
            <p class="dgc-dedication-to" data-dgc-out="recipient" data-prefix="{$dgc.t.preview_for|escape:'html':'UTF-8'}"></p>
            <p class="dgc-dedication-msg" data-dgc-out="message"></p>
            <p class="dgc-dedication-from" data-dgc-out="sender" data-prefix="{$dgc.t.preview_from|escape:'html':'UTF-8'}"></p>
          </div>
        </div>
      </aside>
    </div>
  </form>
  {/if}
</div>
{/block}

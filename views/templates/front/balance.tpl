{extends file='page.tpl'}

{block name='page_title'}
  {$dgc.t.balance_title|escape:'html':'UTF-8'}
{/block}

{block name='page_content'}
<div class="dgc" id="dotmgiftcard-balance"{if $dgc.css_vars} style="{$dgc.css_vars|escape:'html':'UTF-8'}"{/if}>
  <div class="dgc-card dgc-card-narrow">
    <p class="dgc-muted">{$dgc.t.balance_intro|escape:'html':'UTF-8'}</p>

    {if $dgc.error}
      <div class="dgc-alert dgc-alert-error" role="alert"><p>{$dgc.error|escape:'html':'UTF-8'}</p></div>
    {/if}

    <form method="post" action="{$dgc.action|escape:'html':'UTF-8'}" class="dgc-form">
      <label class="dgc-field">
        <span>{$dgc.t.code_label|escape:'html':'UTF-8'}</span>
        <input type="text" name="code" value="{$dgc.code|escape:'html':'UTF-8'}" required autocomplete="off" spellcheck="false" class="dgc-code-input" placeholder="GC-XXXX-XXXX-XXXX">
      </label>
      <div class="dgc-hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      <button type="submit" name="dgc_check" value="1" class="dgc-btn">{$dgc.t.btn_check|escape:'html':'UTF-8'}</button>
    </form>

    {if $dgc.result}
      <dl class="dgc-data dgc-result">
        <dt>{$dgc.t.code_label|escape:'html':'UTF-8'}</dt><dd class="dgc-code">{$dgc.result.code|escape:'html':'UTF-8'}</dd>
        <dt>{$dgc.t.col_balance|escape:'html':'UTF-8'}</dt><dd class="dgc-balance">{$dgc.result.balance|escape:'html':'UTF-8'}</dd>
        <dt>{$dgc.t.col_amount|escape:'html':'UTF-8'}</dt><dd>{$dgc.result.amount|escape:'html':'UTF-8'}</dd>
        <dt>{$dgc.t.col_state|escape:'html':'UTF-8'}</dt><dd><span class="dgc-badge dgc-badge-{$dgc.result.state|escape:'html':'UTF-8'}">{$dgc.result.state_label|escape:'html':'UTF-8'}</span></dd>
        <dt>{$dgc.t.col_expire|escape:'html':'UTF-8'}</dt><dd>{if $dgc.result.expire}{$dgc.result.expire|escape:'html':'UTF-8'}{else}{$dgc.t.no_expiry_short|escape:'html':'UTF-8'}{/if}</dd>
      </dl>
      <p class="dgc-small dgc-muted">{if $dgc.result.partial}{$dgc.t.usage_partial|escape:'html':'UTF-8'}{else}{$dgc.t.usage_single|escape:'html':'UTF-8'}{/if}</p>
    {/if}
  </div>
  <p class="dgc-center"><a class="dgc-link" href="{$dgc.buy_url|escape:'html':'UTF-8'}">{$dgc.t.buy_link|escape:'html':'UTF-8'}</a></p>
</div>
{/block}

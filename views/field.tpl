{if isset($zei_offer_product)}
    Zei offer :
    <select name="zei_offer" id="zei_offer">
        <option value="0"></option>
        {foreach from=$zei_offer_list key=key item=name}
        <option value="{$key}"{if $key == $zei_offer_product} selected{/if}>{$name}</option>
        {/foreach}
    </select>
{/if}
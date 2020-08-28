<div class="crm-block crm-form-block">
    {if $smarty.get.state eq 'done'}
        <div class="help">
            {ts}Volunteer Import completed Successfully.{/ts}<br/>
        </div>
    {else}
        <p>{ts}Running this will import all volunteers as contacts from Yee Hong to CiviCRM.{/ts}</p>
        <table class="form-layout-compressed">
            {foreach from=$importFields item=field}
                <tr>
                    <td class="label">{$form.$field.html}</td>
                    <td>{$form.$field.label}</td>
                </tr>
            {/foreach}
        </table>
        <div class="crm-submit-buttons">
            {include file="CRM/common/formButtons.tpl"}
        </div>
    {/if}
</div>
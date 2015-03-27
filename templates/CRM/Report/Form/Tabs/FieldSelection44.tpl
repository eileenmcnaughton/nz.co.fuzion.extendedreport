<div id="report-tab-col-groups" class="civireport-criteria">
  {if $componentName eq 'Grant'}
    <h3>{ts}Include these Statistics{/ts}</h3>
  {else}
    <h3>Display Columns</h3>
  {/if}
  {foreach from=$colGroups item=grpFields key=dnc}
    {assign  var="count" value="0"}
    {* Wrap custom field sets in collapsed accordion pane. *}
      {if $grpFields.use_accordian_for_field_selection}
      <div class="crm-accordion-wrapper crm-accordion collapsed">
      <div class="crm-accordion-header">
        {$grpFields.group_title}
      </div><!-- /.crm-accordion-header -->
      <div class="crm-accordion-body">
    {/if}
    <table class="criteria-group">
      <tr class="crm-report crm-report-criteria-field crm-report-criteria-field-{$dnc}">
        {foreach from=$grpFields.fields item=title key=field}
        {assign var="count" value=`$count+1`}
        <td width="25%">{$form.fields.$field.html}</td>
        {if $count is div by 4}
      </tr><tr class="crm-report crm-report-criteria-field crm-report-criteria-field_{$dnc}">
        {/if}
        {/foreach}
        {if $count is not div by 4}
          <td colspan="4 - ($count % 4)"></td>
        {/if}
      </tr>
    </table>
      {if $grpFields.use_accordian_for_field_selection}
      </div><!-- /.crm-accordion-body -->
      </div><!-- /.crm-accordion-wrapper -->
    {/if}
  {/foreach}
</div>

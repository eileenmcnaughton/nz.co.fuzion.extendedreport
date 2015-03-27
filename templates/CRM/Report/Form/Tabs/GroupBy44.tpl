{* unchanged from 4.4 section *}
<div id="group-by-elements" class="civireport-criteria" >
  <h3>Group by Columns</h3>
  {assign  var="count" value="0"}
  <table class="report-layout">
    <tr class="crm-report crm-report-criteria-groupby">
      {foreach from=$groupByElements item=gbElem key=dnc}
      {assign var="count" value=`$count+1`}
      <td width="25%" {if $form.fields.$gbElem}"{/if}>
      {$form.group_bys[$gbElem].html}
      {if $form.group_bys_freq[$gbElem].html}:<br>
        &nbsp;&nbsp;{$form.group_bys_freq[$gbElem].label}&nbsp;{$form.group_bys_freq[$gbElem].html}
      {/if}
      </td>
      {if $count is div by 4}
        </tr><tr class="crm-report crm-report-criteria-groupby">
      {/if}
      {/foreach}
      {if $count is not div by 4}
        <td colspan="4 - ($count % 4)"></td>
      {/if}
    </tr>
  </table>
</div>

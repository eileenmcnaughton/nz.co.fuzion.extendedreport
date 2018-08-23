<table>
  {crmAPI var='result' entity='ReportTemplate' action='getrows' instance_id=$blockVariables.id options=$apiOptions contact_id=$contactID}
  <tr>
    {assign var='metadata' value=$result.metadata}
    {assign var='reportLabels' value=$metadata.labels}
    {foreach from=$reportLabels item=header}
      <th>{$header|escape}</th>
    {/foreach}
  </tr>
  {foreach from=$result.values item=reportinstance}
    <tr>
    {foreach from=$reportinstance key=reportKey item=reportvalue}
      {if $reportLabels.$reportKey}
      <td>{$reportvalue|escape:purify}</td>
      {/if}
    {/foreach}
    </tr>
  {/foreach}
</table>

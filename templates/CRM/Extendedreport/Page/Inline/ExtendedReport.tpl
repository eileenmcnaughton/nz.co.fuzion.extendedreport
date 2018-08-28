<table>
  {php}
    $this->assign("apiOptions", array('metadata' => array("labels")));
  {/php}
  {crmAPI var='result' entity='ReportTemplate' action='getrows' instance_id=$block.report_id options=$apiOptions contact_id=$contactId}
  <tr>
    {assign var='reportLabels' value=$result.metadata.labels}
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

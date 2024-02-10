<table>
  {assign var=apiOptions value=['metadata' => ['labels']]}
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
              {if array_key_exists($reportKey, $reportLabels) && $reportLabels.$reportKey}
                <td>{$reportvalue|escape:purify}</td>
              {/if}
          {/foreach}
      </tr>
    {/foreach}
</table>

<div id="report-tab-set-aggregate" class="civireport-criteria">
  <table class="report-layout">
    <tr class="crm-report crm-report-criteria-aggregate">
      <td>
        <div id='crm-custom_fields'>
          <label>{ts}Select Row Fields{/ts}</label>
            {$form.aggregate_row_headers.html}
        </div>
      </td>
      <td>
        <label>{ts}Select Column Header{/ts}</label>
          {$form.aggregate_column_headers.html}
      </td>
    </tr>
    <tr>
      <td>{$form.delete_null.label}  {$form.delete_null.html}</td>
    </tr>
  </table>
</div>

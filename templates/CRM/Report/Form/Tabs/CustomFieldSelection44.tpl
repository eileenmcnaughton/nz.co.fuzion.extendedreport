<div id='crm-custom_tables'>
  <table>
    <tr>
      <td>

        <div id='crm-custom_fields'>
          <label>{ts}Custom Fields(s){/ts}</label>
          {$form.custom_fields.html}
          {literal}
            <script type="text/javascript">
              cj("select#custom_fields").crmasmSelect({
                addItemTarget: 'bottom',
                animate: false,
                highlight: true,
                sortable: true,
                respectParents: true
              });
            </script>
          {/literal}
        </div>
      </td>

      <td>
        {if $form.templates}
          <div id='crm-templates'>
            <label>{ts}Select Print template{/ts}</label>
            {$form.templates.html}
          </div>
        {/if}
      </td>
    </tr>
  </table>
</div>

<div id='crm-container'>
    {foreach from=$rows item=row key=rowid}{assign var=count value=$count+1}
      <div {if $count is div by 3}style="page-break-after: always" {/if}>
        <table border=1 cellspacing=0 cellpadding=0
               style='width:98%;margin-bottom:12px;'>

          <tr style=''>
            <td class='td-personal-details' style='width:40%;vertical-align:top'>
              <table class='tbl-personal-details' border=0 style='width:95%;'>
                <tr>
                  <td>
                    <strong>Name</strong>
                  </td>
                  <td>
                      {$row.civicrm_contact_civicrm_contact_first_name|escape}{if $row.civicrm_contact_civicrm_contact_nick_name|escape}   '{$row.civicrm_contact_civicrm_contact_nick_name}' {/if} {$row.civicrm_contact_civicrm_contact_last_name|escape}
                  </td>
                </tr>
                <tr>
                  <td>
                    City
                  </td>
                  <td>
                      {$row.civicrm_address_address_city|escape}
                  </td>
                </tr>
                <tr>
                  <td>
                    Email
                  </td>
                  <td>
                      {$row.civicrm_email_email_email|escape}
                  </td>
                </tr>
                <tr>
                  <td colspan=3>
                    <table style="border-top-style:solid; width:100%">
                      <tr>
                        <td><p>Latest Activity</p></td>
                      </tr>
                      <tr>
                        <td>
                            {$row.civicrm_activity_activity_activity_type_id|escape}
                        </td>
                        <td>{$row.civicrm_activity_activity_activity_date_time|date_format}</td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
            <td style='width:10%;'>
              <p style='text-align:center'><b>Response</b></p>

              <p></p>

              <p></p>

              <p style='text-align:center'><b>Yes</b></p>

              <p></p>

              <p style='text-align:center'><b>No</b></p>

              <p></p>

              <p style='text-align:center'><b>Maybe</b></p>
            </td>
            <td style='width:50%;vertical-align:top'>
              <table border=1 cellspacing=0 cellpadding=1
                     style='border-collapse:collapse;border:none;'>

                <tr>
                  <th style='width:30%'></th>
                  <th style='width:10%;cellpadding:1'>
                    <p>Left Message</p>
                  </th>
                  <th style='width:10%;cellpadding:1'>
                    <p>Busy</p>
                  </th>
                  <th style='width:10%;cellpadding:1'>
                    <p>Wrong No</p>
                  </th>
                  <th style='width:10%;cellpadding:1'>
                    <p>Disconnected</p>
                  </th>
                  <th style='width:10%;cellpadding:1'>
                    <p>DNC</p>
                  </th>
                  <th style='width:10%;cellpadding:1'>
                    <p>Deceased</p>
                  </th>
                </tr>
                <tr>
                    {foreach from=$row.civicrm_phone_phone_phone key=phonekey item=phone}
                <tr>
                  <td>{$phonekey|escape} : {$phone|escape}</td>
                  <td></td>
                  <td></td>
                  <td></td>
                  <td></td>
                  <td></td>
                  <td></td>
                </tr>
                  {/foreach}

              </table>
            </td>
          </tr>
          <tr>
            <td class='td-tags' style='width:100%' colspan=3>
              <b>Tags</b>
                {$row.civicrm_tag_tag_name|escape}  </td>
          </tr>

        </table>
        Printed {$reportDate} Contact ID : {$row.civicrm_contact_civicrm_contact_contact_id|escape}
          {foreach from=$statistics.filters key=filterkey item=filter}
            Report Criteria:  {$filter.title|escape} {$filter.value|escape}
          {/foreach}
      </div>
    {/foreach}
</div>

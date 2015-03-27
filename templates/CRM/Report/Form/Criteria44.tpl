{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2014                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*}
{* Report form criteria section *}
{if $colGroups}
  <div id="col-groups" class="civireport-criteria" >
    {if $componentName eq 'Grant'}
      <h3>{ts}Include these Statistics{/ts}</h3>
    {else}
      <h3>Display Columns</h3>
    {/if}
    {foreach from=$colGroups item=grpFields key=dnc}
      {assign  var="count" value="0"}
      {* Wrap custom field sets in collapsed accordion pane. *}
      {if $grpFields.group_title}
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
      {if $grpFields.group_title}
        </div><!-- /.crm-accordion-body -->
        </div><!-- /.crm-accordion-wrapper -->
      {/if}
    {/foreach}
  </div>
{/if}

{if $groupByElements}
  {include file="CRM/Report/Form/Tabs/GroupBy44.tpl"}
{/if}
{if $form.custom_tables}
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
{/if}
{if $form.aggregate_column_headers}
  <table>
    <tr>
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
  </table>
{/if}

{if $orderByOptions}
  {include file="CRM/Report/Form/Tabs/OrderBy44.tpl"}
{/if}

{if $otherOptions}
  {include file="CRM/Report/Form/Tabs/ReportOptions44.tpl"}
{/if}

{if $filters}
  {include file="CRM/Report/Form/Tabs/Filters44.tpl"}
{/if}

    {literal}
  <script type="text/javascript">
        {/literal}
            {foreach from=$filters item=table key=tableName}
                {foreach from=$table item=field key=fieldName}
        {literal}var val = "dnc";{/literal}
                {assign var=fieldOp     value=$fieldName|cat:"_op"}
                {if !($field.operatorType & 4) && !$field.no_display && $form.$fieldOp.html}
                    {literal}var val = document.getElementById("{/literal}{$fieldOp}{literal}").value;{/literal}
    {/if}
                {literal}showHideMaxMinVal( "{/literal}{$fieldName}{literal}", val );{/literal}
            {/foreach}
        {/foreach}

        {literal}
    function showHideMaxMinVal( field, val ) {
      var fldVal    = field + "_value_cell";
      var fldMinMax = field + "_min_max_cell";
      if ( val == "bw" || val == "nbw" ) {
        cj('#' + fldVal ).hide();
        cj('#' + fldMinMax ).show();
      } else if (val =="nll" || val == "nnll") {
        cj('#' + fldVal).hide() ;
        cj('#' + field + '_value').val('');
        cj('#' + fldMinMax ).hide();
      } else {
        cj('#' + fldVal ).show();
        cj('#' + fldMinMax ).hide();
      }
    }

    cj(document).ready(function(){
      cj('.crm-report-criteria-groupby input:checkbox').click(function() {
        cj('#fields_' + this.id.substr(10)).prop('checked', this.checked);
      });
      {/literal}{if $displayToggleGroupByFields}{literal}
      cj('.crm-report-criteria-field input:checkbox').click(function() {
        cj('#group_bys_' + this.id.substr(7)).prop('checked', this.checked);
      });
      {/literal}{/if}{literal}
    });
  </script>
{/literal}

    <div>{$form.buttons.html}</div>

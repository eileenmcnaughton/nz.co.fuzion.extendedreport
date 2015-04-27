{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.6                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2014                                |
 +--------------------------------------------------------------------+
 + extended reports version.
 + We include this to drive the overriding of other forms in a way that
 + only affects reports from the extended report
 + these will all have a civicrm_major_version assigned to the
 + template and if this is not present this template
 + allows 4.4 to be exactly the same as the default.
 +
 + At this stage default 4.6 templates for TABS are being overwritten
 + to make them available to 4.4
 +--------------------------------------------------------------------+
*}

{if $outputMode neq 'print'}
  {if $civicrm_major_version < 46}
    {include file="CRM/common/crmeditable.tpl"}
  {/if}
{/if}
{* this div is being used to apply special css *}
{if $section eq 1}
  <div class="crm-block crm-content-block crm-report-layoutGraph-form-block">
    {*include the graph*}
    {include file="CRM/Report/Form/Layout/Graph.tpl"}
  </div>
{elseif $section eq 2}
  <div class="crm-block crm-content-block crm-report-layoutTable-form-block">
    {*include the table layout*}
    {include file="CRM/Report/Form/Layout/Table.tpl"}
  </div>
{else}
  {if $criteriaForm OR $instanceForm OR $instanceFormError}
    <div class="crm-block crm-form-block crm-report-field-form-block">
    {if $civicrm_major_version == 44}
      {include file="CRM/Report/Form/Fields44.tpl"}
    {else}
      {include file="CRM/Report/Form/Fields.tpl"}
    {/if}
    </div>
  {/if}

  <div class="crm-block crm-content-block crm-report-form-block">
    {*include actions*}
    {include file="CRM/Report/Form/Actions.tpl"}

    {*Statistics at the Top of the page*}
    {include file="CRM/Report/Form/Statistics.tpl" top=true}

    {*include the graph*}
    {include file="CRM/Report/Form/Layout/Graph.tpl"}

    {*include the table layout*}
    {include file="CRM/Report/Form/Layout/Table.tpl"}
    <br />
    {*Statistics at the bottom of the page*}
    {include file="CRM/Report/Form/Statistics.tpl" bottom=true}

    {include file="CRM/Report/Form/ErrorMessage.tpl"}
  </div>
{/if}
{if $outputMode == 'print'}
  <script type="text/javascript">
    window.print();
  </script>
{/if}

(function (angular, $, _) {

  angular.module('extendedreport').config(function ($routeProvider) {
      $routeProvider.when('/exreport/report/:id', {
        controller: 'ExtendedreportReportCntrl',
        templateUrl: '~/extendedreport/ReportCntrl.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          reportMetadata: function (crmApi, $route) {
            return crmApi('ReportTemplate', 'getmetadata', {
              instance_id: $route.current.params.id
            });
          },
          reportInstance: function (crmApi, $route) {
            return crmApi('ReportTemplate', 'getinstance', {
              id: $route.current.params.id
            });
          }
        }
      });
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  //   myContact -- The current contact, defined above in config().
  angular.module('extendedreport').controller('ExtendedreportReportCntrl', function ($scope, crmApi, crmStatus, crmUiHelp, reportMetadata, reportInstance, crmLegacy) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('extendedreport');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/extendedreport/ReportCntrl'});// See: templates/CRM/extendedreport/ReportCntrl.hlp
    var reportID = reportInstance.id;
    var reportFields = reportInstance.values[reportID]['form_values']['fields'];
    var reportOrderBys = reportInstance.values[reportID]['form_values']['order_bys'];
    var form_values = reportInstance.values[reportID]['form_values'];
    $scope.form_values = form_values;

    $scope.classicUrl = crmLegacy.url('civicrm/report/instance/' + reportInstance.id, 'reset=1output=html');

    // We have myContact available in JS. We also want to reference it in HTML.
    $scope.reportMetadata = reportMetadata.values;

    function getLabels(fields) {
      var labels = [];
      _.each(fields, function (field, key) {
        var label = {
          'title': field.title,
          'name': key
        };
        labels.push(label);
      });
      return labels;
    }

    function getSelectedFields(formValues, fields) {
      if (formValues.hasOwnProperty('extended_fields')) {
        return formValues['extended_fields'];
      }
      var selectedFields = [];
      _.each(formValues['fields'], function (field, key) {
        selectedFields.push({
          'title': fields[key]['title'],
          'name': key
        });
      });
      return selectedFields;
    }

    function getSelectedOrderBys(formValues, fields) {
      if (formValues.hasOwnProperty('extended_order_bys')) {
        return formValues['extended_order_bys'];
      }
      var selectedFields = [];
      _.each(formValues['order_bys'], function (field) {
        var columnName = field['column'];
        if (columnName !== '-') {
          selectedFields.push({
            //'title': fields[columnName]['title'],
            'column': columnName
          });
        }
      });
      return selectedFields;
    }

    function getUnselectedFields(fieldMetadata, selectedFields) {
      var ret = [];
      _.each(fieldMetadata, function (field, key) {
        if (!selectedFields.hasOwnProperty(key)) {
          ret.push({
            'title': field.title,
            'name': key
          });
        }
      });
      return ret;
    }

    function getUnselectedSorting(fieldMetadata, selectedFields) {

      var ret = [];
      _.each(fieldMetadata, function (field, key) {
        if (!selectedFields.hasOwnProperty(key)) {
          ret.push({
            'title': field.title,
            'name': key
          });
        }
      });
      return ret;
    }

    $scope.reportFields = getSelectedFields(form_values, reportMetadata.values['metadata']);
    $scope.reportOrderByFields = getSelectedOrderBys(form_values, reportMetadata.values['metadata']);
    $scope.fieldList = getUnselectedFields(reportMetadata.values['fields'], reportFields);
    $scope.orderBysList = getUnselectedSorting(reportMetadata.values['order_bys'], reportOrderBys);

    $scope.sortableFieldOptions = {
      placeholder: "fieldItem",
      connectWith: ".fields-container",
      cancel: "input,textarea,button,select,option,a,.crm-editable-enabled,[contenteditable]",
      containment: "#extendedReportFieldsConfig"
    };

    $scope.sortableOrderByOptions = {
      placeholder: "fieldItem",
      connectWith: ".order-bys-container",
      cancel: "input,textarea,button,select,option,a,.crm-editable-enabled,[contenteditable]",
      containment: "#extendedReportSortingConfig"
    };

    $scope.selectField = function (field) {
      if (!field.hasOwnProperty('field_on_null')) {
        field['field_on_null'] = [];
      }
      if (!field.hasOwnProperty('field_on_null_usage')) {
        field['field_on_null_usage'] = 'on_null';
      }
      $scope.selectedField = field;
      $scope.selectedAlternateFields = field['field_on_null'];
    };

    $scope.selectOrderBy = function (field) {
      if (!field.hasOwnProperty('field_on_null')) {
        field['field_on_null'] = [];
      }
      if (!field.hasOwnProperty('field_on_null_usage')) {
        field['field_on_null_usage'] = 'on_null';
      }
      if (!field.hasOwnProperty('order')) {
        field['order'] = 'ASC';
      }
      $scope.selectedOrderBy = field;
      $scope.selectedAlternateOrderBys = field['field_on_null'];
    };

    $scope.selectedTab = 'fields';

    $scope.selectTab = function (tab) {
      $scope.selectedTab = tab;
    };

    $scope.save = function save() {
      form_values['extended_fields'] = $scope.reportFields;
      form_values['extended_order_bys'] = $scope.reportOrderByFields;
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Saving...'), success: ts('Saved')},
        // The save action. Note that crmApi() returns a promise.
        crmApi('ReportInstance', 'create', {
          id: reportID,
          form_values: form_values
        })
      );
    };
  });

  // Editable titles using ngModel & html5 contenteditable
  angular.module('extendedreport').directive("extendedreportEditable", function () {
    return {
      restrict: "A",
      require: "ngModel",
      link: function (scope, element, attrs, ngModel) {
        var ts = CRM.ts('extendedreport');

        function read() {
          var htmlVal = element.html();
          if (!htmlVal) {
            htmlVal = ts('Untitled');
            element.html(htmlVal);
          }
          ngModel.$setViewValue(htmlVal);
        }

        ngModel.$render = function () {
          element.html(ngModel.$viewValue || ' ');
        };

        // Special handling for enter and escape keys
        element.on('keydown', function (e) {
          // Enter: prevent line break and save
          if (e.which === 13) {
            e.preventDefault();
            element.blur();
          }
          // Escape: undo
          if (e.which === 27) {
            element.html(ngModel.$viewValue || ' ');
            element.blur();
          }
        });

        element.on("blur change", function () {
          scope.$apply(read);
        });

        element.attr('contenteditable', 'true').addClass('crm-editable-enabled');
      }
    };
  });

})(angular, CRM.$, CRM._);


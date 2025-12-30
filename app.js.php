<?php


include 'functions.php';
session_name('lkzk');
session_start();
$c_access = $_SESSION['c_access'];
$c_login = $_SESSION['c_login'];
$c_fio = $_SESSION['c_fio'];

$_c_roles = ExplodePalki($_SESSION['c_roles']);
$c_roles = [];
if ($_c_roles)
foreach($_c_roles as $role)
{
  $c_roles[$role] = true;
}

?>

'use strict';

Array.prototype.in_array = function(p_val) {
  for(var i = 0, l = this.length; i < l; i++) {
    if(this[i] == p_val) {
      return true;
    }
  }
  return false;
}

if (!String.prototype.trim) {
    (function() {
        // Make sure we trim BOM and NBSP
        var rtrim = /^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g;
        String.prototype.trim = function() {
            return this.replace(rtrim, '');
        };
    })();
}


var MONTHS = 
[
  'Январь',
  'Февраль',
  'Март',
  'Апрель',
  'Май',
  'Июнь',
  'Июль',
  'Август',
  'Сентябрь',
  'Октябрь',
  'Ноябрь',
  'Декабрь'
];



var showSessionExpired = function($scope, ngDialog)
{
  if ($scope && !$scope.showing_session_expired)
  {
      ngDialog.openConfirm({
        template: 'session_expired',
        className: 'ngdialog-theme-default',
        scope: $scope,
        closeByEscape: false,
        onOpenCallback: function(value) {
            
            $scope.showing_session_expired = true;
        },
        preCloseCallback: function(value) {
        window.location.reload() 
      }
    }).then(function (value) {

        window.location.reload()

    });
  }
}

var checkSession = function($http, $scope, ngDialog)
{
  $http({url: 'ajax/get/check_session.php', method: 'POST', data: {}})
    .then( function (response) 
    {
      if (response.data != true)
      {
         showSessionExpired($scope, ngDialog)
      }
    })
}

function createSelectFilter(column, footerCell) 
{
  // Получить все уникальные значения из столбца, видимые и не видимые
  const uniqueValues = new Set();

  column.data().each(function(d) {
    if(d !== null && d !== undefined && d !== '') {
      uniqueValues.add(d);
    }
  });

  const select = $('<select class="search_init text_filter form-select"><option value=""></option></select>')
    .appendTo($(footerCell))
    .on('change', function() {
      column.search(this.value).draw();
    });

  Array.from(uniqueValues).sort().forEach(value => {
    select.append('<option value="' + value + '">' + value + '</option>');
  });
}

// columns - описание столбцов таблицы
/*
function createCustomFilters(table_id, table, columns) 
{
  CL('createCustomFilters');
  
  // Очищаем старые фильтры перед созданием новых
  $('#' + table_id + ' tfoot th').each(function() {
    $(this).find('select, input').remove();
  });

  table.columns(':visible').every(function(columnIndex) {
    const column = this;
    const footerCell = $('#' + table_id + ' tfoot th[ind="' + columnIndex + '"]');
    const colSettings = columns[columnIndex];

    if (colSettings && colSettings.type === 'select')
    {
      createSelectFilter(column, footerCell);
    } 
    else if (colSettings && colSettings.type === 'input')
    {
      const input = $('<input class="search_init text_filter form-control" type="text" />')
        .appendTo(footerCell)
        .on('keyup change', function() {
          if(column.search() !== this.value) {
            column.search(this.value).draw();
          }
        });
    }
  });
}
*/

// function createCustomFilters(table_id, table, columns) {
//     CL('createCustomFilters');
    
//     // Очищаем старые фильтры
//     $('#' + table_id + ' tfoot th').each(function() {
//         $(this).find('select, input').remove();
//     });

//     // Получаем сохраненное состояние таблицы
//     const state = table.state.loaded();
    
//     // Проходим по всем видимым колонкам
//     table.columns(':visible').every(function(columnIndex) {
//         const column = this;
//         const columnSettings = columns[columnIndex];
//         const footerCell = $('#' + table_id + ' tfoot th[ind="' + columnIndex + '"]');
        
//         // Получаем сохраненное значение поиска для колонки
//         let savedSearch = '';
//         if (state && state.columns && state.columns[columnIndex]) {
//             savedSearch = state.columns[columnIndex].search.search || '';
//         }

//         if (columnSettings && columnSettings.type === 'select') 
//         {
//             // Создаем селект
//             const select = $('<select class="form-select" style=""></select>')
//                 .appendTo(footerCell)
//                 .on('change', function() {
//                     column.search(this.value).draw();
//                 });

//                 // Добавляем пустую опцию
//             $('<option value=""></option>').appendTo(select);
            
//             // Заполняем опции из данных таблицы
//             column.data().unique().sort().each(function(d) {
//                 if (d) {
//                     $('<option value="' + d + '">' + d + '</option>').appendTo(select);
//                 }
//             });

//             // Устанавливаем сохраненное значение
//       if (savedSearch) {
//         select.val(savedSearch);
//       }
//         } 
//         else if (columnSettings && columnSettings.type === 'input') {
//             // Для инпутов
//             const input = $('<input class="form-control" type="text" />')
//                 .appendTo(footerCell)
//                 .val(savedSearch) // Устанавливаем сохраненное значение
//                 .on('keyup change', function() {
//                     if(column.search() !== this.value) {
//                         column.search(this.value).draw();
//                     }
//                 });
//         }
//     });
// }

function createCustomFilters(table_id, table, columns) 
{
  CL('createCustomFilters');
  
  // Очищаем старые фильтры перед созданием новых
  $('#' + table_id + ' tfoot th').each(function() {
    $(this).find('select, input').remove();
  });

  // Получаем сохраненное состояние таблицы
  const state = table.state.loaded();
  
  table.columns(':visible').every(function(columnIndex) {
    const column = this;
    const footerCell = $('#' + table_id + ' tfoot th[ind="' + columnIndex + '"]');
    const colSettings = columns[columnIndex];

    // Получаем сохраненное значение фильтра для колонки
    let savedSearch = '';

    if (state && state.columns && state.columns[columnIndex] && state.columns[columnIndex].search) {
      savedSearch = state.columns[columnIndex].search.search || '';
    }

    if (colSettings && colSettings.type === 'select')
    {
      const select = $('<select class="form-select"></select>')
        .appendTo(footerCell)
        .on('change', function() {
          column.search(this.value).draw();
        });

        // Добавляем пустую опцию
        $('<option value=""></option>').appendTo(select);
      
      // Добавляем опции в селект
      column.data().unique().sort().each(function(d) {
        if (d) {
          $('<option value="' + d + '">' + d + '</option>').appendTo(select);
        }
      });
      
      // Устанавливаем сохраненное значение
      if (savedSearch) {
        select.val(savedSearch);
      }
    } 
    else if (colSettings && colSettings.type === 'input')
    {
      const input = $('<input class="search_init text_filter form-control" type="text" />')
        .appendTo(footerCell)
        .val(savedSearch)  // Устанавливаем сохраненное значение
        .on('keyup change', function() {
          if(column.search() !== this.value) {
            column.search(this.value).draw();
          }
        });
    }
  });
}


const c_login = '<?=$c_login?>';
const c_fio = '<?=$c_fio?>';
// роли авторизованного
const c_roles = {<?=ArrayToJS($c_roles)?>};
// справочник ролей
const $_roles = {<?=ArrayToJS($_roles)?>};
const $_sotrudnik_types = {<?=ArrayToJS($_sotrudnik_types)?>};
const $_forms_obuchenia = {<?=ArrayToJS($_forms_obuchenia)?>};
const $_degrees_codes = {<?=ArrayToJS($_degrees_codes)?>};
const $_system_modes = {<?=ArrayToJS($_system_modes)?>};
// id кафедры для зав. кафедрой
const c_chair_id = '<?=($_SESSION['c_chair_id'] ? $_SESSION['c_chair_id'] : '')?>';
CL(c_chair_id);
const CUR_YEAR = new Date().getFullYear();

//****************************************
// https://amsul.ca/pickadate.js/
angular.module('app', ['ngRoute', 'ngDialog', 'angucomplete-alt', 'ngAnimate', 'angularSpinners', 'ui.mask', 'angularFileUpload', 'ngCookies', 'pickadate', 'datatables', 'datatables.columnfilter', 'datatables.colvis', 'ngResource', 'ngSanitize', 'ngCookies'])
//    'ui.bootstrap.modal', 'ui.bootstrap', ,  'mgcrea.ngStrap',

.constant('system_start_year', <?=$_system_start_year?$_system_start_year:2017?>)


// http://oncodesign.io/2014/02/19/safely-prevent-template-caching-in-angularjs/
.run(function($rootScope, $templateCache) {
   $rootScope.$on('$routeChangeStart', function(event, next, current) {
          if (typeof(current) !== 'undefined'){
              $templateCache.remove(current.templateUrl);
          }
      });
})

.config(['$routeProvider', '$locationProvider', '$httpProvider', function ($routeProvider, $locationProvider, $httpProvider) 
{
  $locationProvider.hashPrefix('');

  $httpProvider.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

  $routeProvider
    .when('/',
    {
      templateUrl: 'index.tpl.php?' + getRandom(10000, 99999),
      controller: 'IndexCtrl'
    })
    // Интерфейс завкафа
    .when('/nagruzka',
    {
      templateUrl: 'nagruzka.tpl.html?' + getRandom(10000, 99999),
      controller: 'NagruzkaCtrl',
      resolve:
      {
        nagruzka_type: function($route)
        {
          return null;
        },
        nagruzka_selected_chair_id: function($route)
        {
          return null
        },
        system_mode: function($http)
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'});
        },
        nagruzka_stat: function($http)
        {
          return $http({url: 'ajax/get/get_nagruzka_zavkaf_stat.php?chair_id=' + c_chair_id, method: 'GET'});
        },
        nagruzka: function($http)
        {
          return null;//$http({url: 'ajax/get/nagruzka_discipline.php?chair_id=' + c_chair_id, method: 'GET'});
        }

      }
    })
    // Интерфейс завкафа
    .when('/nagruzka/:type',
    {
      templateUrl: 'nagruzka.tpl.html?' + getRandom(10000, 99999),
      controller: 'NagruzkaCtrl',
      resolve:
      {
        nagruzka_type: function($route)
        {
          return $route.current.params.type
        },
        nagruzka_selected_chair_id: function($route)
        {
          return null
        },
        system_mode: function($http)
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'});
        },
        nagruzka_stat: function($http)
        {
          return $http({url: 'ajax/get/get_nagruzka_zavkaf_stat.php?chair_id=' + c_chair_id, method: 'GET'});
        },
        nagruzka: function($route, $http)
        {
          const nagruzka_type = $route.current.params.type;

          if (nagruzka_type == 'discipline')
          {
            return $http({url: 'ajax/get/nagruzka_discipline.php?chair_id=' + c_chair_id, method: 'GET'});
          }
          // else if (nagruzka_type == 'vkr')
          // {
          //   return $http({url: 'ajax/get/nagruzka_vkr.php?chair_id=' + c_chair_id, method: 'GET'});
          // }
          // else if (nagruzka_type == 'ksro')
          // {
          //   return $http({url: 'ajax/get/nagruzka_ksro.php?chair_id=' + c_chair_id, method: 'GET'});
          // }
          // else if (nagruzka_type == 'gia')
          // {
          //   return $http({url: 'ajax/get/nagruzka_gia.php?chair_id=' + c_chair_id, method: 'GET'});
          // }
          // else if (nagruzka_type == 'aspirant')
          // {
          //   return $http({url: 'ajax/get/nagruzka_aspirant.php?chair_id=' + c_chair_id, method: 'GET'});
          // }
          return null;
        }
        /*
        if (!isEmpty(nagruzka_selected_chair_id)) chair_str = "?chair_id=" + nagruzka_selected_chair_id;
        else chair_str = "";

        $scope.nagruzka = $resource('ajax/get/nagruzka_discipline.php' + chair_str).query(function()
        */
      }
    })
    // Интерфейс УОУП, как у завкафа, но readonly
    .when('/nagruzka/:type/:nagruzka_selected_chair_id',
    {
      templateUrl: 'nagruzka.tpl.html?' + getRandom(10000, 99999),
      controller: 'NagruzkaCtrl',
      resolve:
      {
        nagruzka_type: function($route)
        {
          return $route.current.params.type
        },
        // Параметр только для УОУП для выбора кафедры
        nagruzka_selected_chair_id: function($route)
        {
          return $route.current.params.nagruzka_selected_chair_id
        },
        system_mode: function($http)
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'});
        },
        nagruzka_stat: function($http, $route)
        {
          return $http({url: 'ajax/get/get_nagruzka_zavkaf_stat.php?chair_id=' + ($route.current.params.nagruzka_selected_chair_id ? $route.current.params.nagruzka_selected_chair_id : c_chair_id), method: 'GET'});
        },
        nagruzka: function($http, $route)
        {
          const nagruzka_type = $route.current.params.type;
          const chair_id = $route.current.params.nagruzka_selected_chair_id;

          if (nagruzka_type == 'discipline')
          {
            return $http({url: 'ajax/get/nagruzka_discipline.php?chair_id=' + (chair_id ? chair_id : c_chair_id), method: 'GET'});
          }
          // else if (nagruzka_type == 'vkr')
          // {
          //   return $http({url: 'ajax/get/nagruzka_vkr.php?chair_id=' + c_chair_id, method: 'GET'});
          // }
          // else if (nagruzka_type == 'ksro')
          // {
          //   return $http({url: 'ajax/get/nagruzka_ksro.php?chair_id=' + c_chair_id, method: 'GET'});
          // }
          // else if (nagruzka_type == 'gia')
          // {
          //   return $http({url: 'ajax/get/nagruzka_gia.php?chair_id=' + c_chair_id, method: 'GET'});
          // }
          // else if (nagruzka_type == 'aspirant')
          // {
          //   return $http({url: 'ajax/get/nagruzka_aspirant.php?chair_id=' + c_chair_id, method: 'GET'});
          // }
          return null;
        }
      }
    })
    .when('/uoup_nagruzka',
    {
      templateUrl: 'uoup_nagruzka.tpl.html?' + getRandom(10000, 99999),
      controller: 'UOUPNagruzkaCtrl',
      resolve:
      {
        uoup_nagruzka: function($http)
        {
          return $http({url: 'ajax/get/uoup_nagruzka.php', method: 'GET'});
        },
        nagruzka_uoup_stat: function($http)
        {
          return $http({url: 'ajax/get/get_nagruzka_uoup_stat.php', method: 'GET'});
        },
        page: function($q) {
          return $q.when('uoup_nagruzka');
        }
      }
    })
    .when('/uoup_nagruzka_no_chair',
    {
      templateUrl: 'uoup_nagruzka_no_chair.tpl.html?' + getRandom(10000, 99999),
      controller: 'UOUPNagruzkaCtrl',
      resolve:
      {
        uoup_nagruzka: function($http)
        {
          return $http({url: 'ajax/get/uoup_nagruzka_no_chair.php', method: 'GET'});
        },
        nagruzka_uoup_stat: function($http)
        {
          return {};
        },
        page: function($q) {
          return $q.when('uoup_nagruzka_no_chair');
        }
      }
    })
    .when('/uoup_chairs_refused',
    {
      templateUrl: 'uoup_chairs_refused.tpl.html?' + getRandom(10000, 99999),
      controller: 'UOUPChairsRefusedCtrl',
      resolve:
      {
        uoup_nagruzka: function($http)
        {
          return $http({url: 'ajax/get/chairs_refused_nagruzka_discipline.php', method: 'GET'});
        },
        system_mode: function($http)
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'});
        },
      }
    })
    .when('/uoup_nagruzka_to_change',
    {
      templateUrl: 'uoup_nagruzka_to_change.tpl.html?' + getRandom(10000, 99999),
      controller: 'UOUPNagruzkaToChangeCtrl',
      resolve:
      {
        uoup_nagruzka: function($http)
        {
          return $http({url: 'ajax/get/chairs_require_admin_change_nagruzka_discipline.php', method: 'GET'});
        },
        system_mode: function($http)
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'});
        },
      }
    })
    .when('/system_mode',
    {
      templateUrl: 'system_mode.tpl.html?' + getRandom(10000, 99999),
      controller: 'SystemModeCtrl',
      resolve:
      {
        page: function($q) {
          return $q.when('system_mode');
        },
        system_mode: function($http)
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'});
        }
      }
    })
    .when('/system_closed',
    {
      templateUrl: 'system_closed.tpl.html?' + getRandom(10000, 99999),
      controller: 'SystemClosedCtrl',
      resolve:
      {
        page: function($q) {
          return $q.when('system_closed');
        }
      }
    })
    .when('/sotrudniki',
    {
      templateUrl: 'sotrudniki.tpl.html?' + getRandom(10000, 99999),
      controller: 'SotrudnikiCtrl',
      resolve:
      {
        system_mode: function($http)
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'});
        }
      }
    })
    .when('/test',
    {
      templateUrl: 'test.tpl.html?' + getRandom(10000, 99999),
      controller: 'TestCtrl',
      resolve:
      {
       
      }
    })
    .when('/uoup',
    {
      templateUrl: 'uoup.tpl.html?' + getRandom(10000, 99999),
      controller: 'AdminsUOUPCtrl',
      resolve:
      {
        admins_uoup: function($http)
        {
          return $http({url: 'ajax/get/admins_uoup.php', method: 'GET'});
        }
      }
    })
    .otherwise(
    {
      template: 'Страница не найдена'
    });

}])

//Главный контроллер
.controller ('AppCtrl', function($templateCache, $scope, $rootScope, ngDialog, $http, $resource)
{
  CL('AppCtrl');

  $scope.c_login = c_login;

  CL(c_roles);
 
  $scope.c_roles = c_roles;
  // справочник ролей
  $scope.$_roles = $_roles;

  $scope.c_fio = c_fio;

  // $rootScope.CheckSystemMode = function(scope)
  // {
  //   // Получим режим работы
  //   $http({url: 'ajax/get/get_system_mode.php', method: 'GET'})
  //   .then(function(response) {
  //     scope.system_mode = $rootScope.system_mode = response.data.mode;

  //     CL($scope.system_mode);
  //     CL(c_roles);

  //     if (c_roles.zavkaf && $scope.system_mode === 'mode_closed')
  //     {
  //       // window.location = '#/system_closed';
  //     }
  //   });
  // }
 
  $templateCache.put('session_expired', '<p>Время Вашей сессии истекло. Для продолжения работы необходимо авторизоваться повторно.</p>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(1)">OK</button>\
              </div>');

  clearInterval($rootScope.checkSessionInterval);
  $rootScope.checkSessionInterval = setInterval(checkSession, 60000, $http, $scope, ngDialog);

  $rootScope.ClearGreenTableFilters = function(dtInstance, filter_distinct) // table,  global_nagruzka_filter)
  {
    CL('ClearGreenTableFilters');

    const table = dtInstance.dataTable;
    // CL(global_nagruzka_filter);

    filter_distinct.global_nagruzka_filter = undefined;

    // Сброс глобального поиска
    table.fnFilter('');

    // Сброс фильтров для всех столбцов
    for (let i = 0; i < table.fnSettings().aoColumns.length; i++) {
        table.fnFilter('', i);
    }

    // CL(table);

    // const dt = table;//.DataTable();

    // $('#DataTables_Table_1').find('tfoot tr select').val('').trigger('change');
    // $('#DataTables_Table_1').find('tfoot tr input').val('').trigger('change');

    table.fnSort([]); // Сбрасываем сортировку

    table.find('tfoot tr select').val('').trigger('change');
    table.find('tfoot tr input').val('').trigger('change');

    // Перерисовка таблицы
    table.fnDraw();
  }

  $rootScope.NagruzkaRowClick = function(nagruzka_row)
  {
    CL('NagruzkaRowClick');
    CL(nagruzka_row);

    const dialogScope = $scope.$new();
    dialogScope.nagruzka_row = nagruzka_row;

    dialogScope.nagruzka_history = $resource('ajax/get/get_nagruzka_history.php?load_base_UID=' + nagruzka_row.base_uid).query();

    ngDialog.open({
                    template: "nagruzka_history.tpl.html" + "?" + getRandom(10000, 99999),
                    scope: dialogScope,
                    plain: false,
                    disableAnimation: true,
                    className: 'ngdialog-theme-default history'
                  });
  }

})

.controller ('SystemClosedCtrl', function($rootScope, $scope, page)
{
  CL('SystemClosedCtrl');
  $rootScope.page = page;
})

.controller ('IndexCtrl', function($scope, $rootScope)
{
  $rootScope.page = 'index';

  CL('IndexCtrl');

  // window.location = '#/zayavki/' + cur_year;

  if (c_roles.uoup)
  {
    window.location = '#/uoup_nagruzka';
  }

  if (c_roles.zavkaf)
  {
    window.location = '#/nagruzka';
  }

})


.controller ('NagruzkaCtrl', function($rootScope, $scope, $http, $timeout, DTOptionsBuilder, DTColumnBuilder, DTColumnDefBuilder, ngDialog, $templateCache, nagruzka_type, nagruzka_selected_chair_id, $resource, $cookies, system_mode, nagruzka_stat, nagruzka)
{
  CL('NagruzkaCtrl');
  // CL(nagruzka_type);
  // CL(nagruzka_selected_chair_id);
  
  $scope.nagruzka_selected_chair_id = nagruzka_selected_chair_id;
  $scope.system_mode = system_mode.data.mode;
  $rootScope.page = 'nagruzka';
  $scope.$_forms_obuchenia = $_forms_obuchenia;
  $scope.nagruzka_type = nagruzka_type;
  $scope.nagruzka_stat = nagruzka_stat.data;
  // Строка для проверки, что тесты работают. Должна быть ошибка.
  // $scope.nagruzka = nagruzka.data;
  $scope.nagruzka = nagruzka ? nagruzka.data : null;

  // CL($scope.system_mode);
  // CL($scope.nagruzka);

  if (c_roles.zavkaf && $scope.system_mode === 'mode_closed')
  {
    window.location = '#/system_closed';
  }

  // $scope.nagruzka_readonly = c_roles.zavkaf && (!isEmpty(nagruzka_selected_chair_id) || $scope.system_mode === 'mode_verification') || $scope.system_mode === 'mode_archive';

  $scope.nagruzka_readonly = c_roles.uoup || $scope.system_mode === 'mode_verification' || $scope.system_mode === 'mode_archive';

  $templateCache.put('confirm_delete', '<p>Вы уверены, что хотите удалить?</p>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Нет</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(1)">Да</button>\
              </div>');

  $templateCache.put('confirm_require_admin_change', '<p>Нагрузка распределена, при отправке на изменение, распределение будет удалено. Продолжить?</p>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Нет</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(1)">Да</button>\
              </div>');

  
  // $scope.$_degrees_codes = $_degrees_codes;

  $scope.dtInstance = {};
  // Используется только для селекта "Вся нагрузка..."
  $scope.filter_distinct = {};
  $scope.group_action = {};

  $scope.filter_distinct.global_nagruzka_filter = $cookies.get('global_nagruzka_filter');

  function LoadNagruzkaZavkafStat()
  {
    $http({url: 'ajax/get/get_nagruzka_zavkaf_stat.php?chair_id=' + (nagruzka_selected_chair_id ? nagruzka_selected_chair_id : c_chair_id), method: 'GET'}).then(function(response)
    {
      $scope.nagruzka_stat = response.data;
    });
  }


  const columns = [
      null, 
      // факультет
      {
        name: 'department_name',
        type: 'select',
        bRegex: false,
        // bSmart: true
      }, 
      // аббревиатура
      {
        name: 'Abbr',
        type: 'input',
        bRegex: false,
      },
      // дисциплина
      {
        name: 'discipline_name',
        type: 'input',
        bRegex: false,
      },
      // группа
      {
        name: 'group_name',
        type: 'input',
        bRegex: false,
      },
      // уровень образования
      {
        name: 'education_level',
        type: 'select',
        bRegex: false,
      },
      // направление подготовки
      {
        name: 'napravlenie',
        type: 'input',
        bRegex: false,
      },
      // язык программы
      {
        name: 'language',
        type: 'select',
        bRegex: false,
      },
      // форма обучения
      {
        name: 'form_obuchenia',
        type: 'select',
        bRegex: false,
        values: ['Очная', 'Заочная', 'Очная-заочная']
      },
      // семестр
      {
        name: 'UID_Semester',
        type: 'select',
        bRegex: false,
        values: ['1', '2', '3', '4', '5', '6', '7'],
        width: '100'
      },
      // количество студентов
      {
        name: 'StudentAmount',
        type: 'input',
        bRegex: false,
      },
      // вид работ
      {
        name: 'kind_of_work',
        type: 'select',
        bRegex: false,
        // values: ['1', '2', '3', '4', '5', '6', '7']
      },
      // профиль/направленность
      {
        name: 'napravlennost',
        type: 'input',
        bRegex: false,
        width: '100'
      },
      // курс
      {
        name: 'UID_Course',
        type: 'select',
        bRegex: false,
        values: ['1', '2', '3', '4', '5', '6', '7']
      },
      // кол-во часов
      null,
      // преподаватель
      {
        name: 'lecturer_fio',
        type: 'input',
        bRegex: false,
        width: '80'
      },
      null
  ];

  // function NagruzkaInit()
  {
    // CL('NagruzkaInit');
    // $scope.persons = $resource('data.json').query();

    $scope.dtOptions = DTOptionsBuilder //.fromSource('data.json')
      .newOptions()
      .withOption('stateSave', true)
      // .withOption('aoColumns', [{bVisible': false}])
      .withPaginationType('full_numbers')
      .withColVis()
      // Add a state change function
      // .withColVisStateChange(stateChange)
      // Exclude the last column from the list
      .withColVisOption('aiExclude', [0,1,3,14,15])
      .withLanguage({
          "loadingRecords": "Загрузка...",
          "processing": "Обработка..."
      })
      // .withColumnFilter({
      //     aoColumns: columns
      // })

      .withOption('initComplete', function(settings, json) {
        // Скрываем индикатор когда загрузка завершена
        // CL("initComplete");
        $scope.$apply(function() {
            $scope.isLoading = false;
        });
      })
      // .withOption('processing', true)
      ;

    $scope.dtColumnDefs = [
      DTColumnDefBuilder.newColumnDef(0).notSortable(), // notVisible()
    ];

    // Наблюдение за изменением dtInstance, чтобы сделать некоторые инициализации
    /*
    $scope.$watch('dtInstance', function(newValue) 
    {
      CL('dtInstance');
      CL(newValue);

      if (newValue && newValue.DataTable) 
      {
        const table = newValue.DataTable;

        // CL(table);
        // These are the same
        // CL($scope.dtInstance.dataTable.DataTable());

        // Инициализация фильтров при старте
        createCustomFilters('DataTables_Table_nagruzka', table, columns);

        // Сброс и пересоздание фильтров при изменении видимости столбцов
        table.on('column-visibility.dt', function() {
          createCustomFilters('DataTables_Table_nagruzka', table, columns);
        });
      }
    }, true);
    */

    $scope.onNagruzkaTableInstance = function(dtInstance) 
    {
      CL('onNagruzkaTableInstance');
      // CL(dtInstance);

      $scope.dtInstance = dtInstance; // если нужно хранить ссылку
      const table = dtInstance.DataTable;
      createCustomFilters('DataTables_Table_nagruzka', table, columns);

      table.on('column-visibility.dt', function () {
          createCustomFilters('DataTables_Table_nagruzka', table, columns);
      });

      // Обработчик отрисовки таблицы (включая фильтрацию)
      table.on('draw.dt', function() {
        CL('draw.dt - table redrawn');
        const filteredData = table.rows({ search: 'applied' }).data().toArray();
        $scope.$applyAsync(() => {

          // Сбрасываем все чекбоксы
            if ($scope.nagruzka) {
                $scope.nagruzka.forEach(item => {
                    item.selected = false;
                });
            }
            
          $scope.filteredData = filteredData;
          CL('Filtered data updated:', filteredData.length, 'items');
          CL($scope.filteredData.length);
        });
      });
      
      // Инициализация filteredData при первой загрузке
      $scope.$applyAsync(() => 
      {
        CL('applyAsync');
        $scope.filteredData = table.rows({ search: 'applied' }).data().toArray();
        
      });
    };



  }

  $scope.GetNagruzkaAmountSum = function()
  {
    if ($scope.filteredData)
    {
      
      const filteredData = $scope.filteredData.map(item => parseFloat(item[14]));

      // const filteredData = $scope.filteredData.filter(item => typeof item.amount === 'number');

      // CL(filteredData);
      
      return roundToTwo(filteredData.reduce((sum, item) => sum + item, 0));


    }
  }

  
  $scope.GetNagruzkaTypesRowLink = function(nagruzka_type)
  {
    var link = '#/nagruzka/' + nagruzka_type;

    if (!isEmpty($scope.nagruzka_selected_chair_id)) link += '/' + $scope.nagruzka_selected_chair_id;

    return link;
  }

  $scope.ShowNagruzkaTypeLinkNotText = function()
  {
    //  || isEmpty($scope.nagruzka_selected_chair_id
    if (isEmpty($scope.nagruzka_type) || $scope.nagruzka_type == 'all') return true;
    else return false;
  }

  // вычислить уровень образования по коду направления
  $scope.GetEducationLevel = function(nagruzka_row)
  {

  }

  $scope.onNagruzkaGlobalFilterChange = function() 
  {
    CL('onNagruzkaGlobalFilterChange');

    // CL($scope.filter_distinct.global_nagruzka_filter);

    $cookies.put('global_nagruzka_filter', $scope.filter_distinct.global_nagruzka_filter);

    window.location.reload();
  }

  $scope.GetStatNagruzka = function(nagruzka_type, stat)
  {
    if (nagruzka_type == 'discipline')
    {
      if (stat == 'total' && !isEmpty($scope.nagruzka)) return $scope.nagruzka.length;
    }


    return '';
  }


  function SaveNagruzkaLecturer(nagruzka_row)
  {
    $http({url: 'ajax/post/select_nagruzka_lecturer.php', method: 'POST', data: { lecturer_fio: nagruzka_row.lecturer_fio, lecturer_uid: nagruzka_row.lecturer_uid, lecturer_person_id: nagruzka_row.lecturer_person_id, disciplines_UIDs_chain_str: nagruzka_row.disciplines_UIDs_chain_str, disciplines_Names_chain_str: nagruzka_row.disciplines_Names_chain_str, load_base_UID: nagruzka_row.base_uid}})
                .then(function(data)
                {
                  if (data.data.result == 'success')
                  {
                    toastr.success("Данные сохранены");
                    // Обновить статистику для ЗавКафа
                    LoadNagruzkaZavkafStat();

                    nagruzka_row.selected = false;
                  }
                  else
                  {
                    toastr.error("Ошибка");
                  }
                });
  }

  $scope.NagruzkaSelectedLecturer = function(data, nagruzka_row)
  {
    CL('NagruzkaSelectedLecturer');
    
    CL(data);

    if (!isEmpty(nagruzka_row) && !isEmpty(data))
    {
      nagruzka_row.lecturer_fio = data.originalObject.fio;
      nagruzka_row.lecturer_uid = data.originalObject.lecturer_uid;
      nagruzka_row.lecturer_person_id = data.originalObject.person_id;
      // nagruzka_row.lecturer_login = data.originalObject.login;
    }

    nagruzka_row.show_lecturer_autocomplete = false;

    // CL(nagruzka_row);

    SaveNagruzkaLecturer(nagruzka_row);

    

    // $scope.$broadcast('angucomplete-alt:clearInput'); //, 'lecturer_autocomplete_' + nagruzka_row['xml_content_of_load_UID'] + '_' + nagruzka_row['xml_content_of_load_staff_UID']);

    // CL('lecturer_autocomplete_' + nagruzka_row['xml_content_of_load_UID'] + '_' + nagruzka_row['xml_content_of_load_staff_UID']);
  }


  $scope.SelectNagruzkaTDClick = function(nagruzka)
  {
    nagruzka.selected = !nagruzka.selected;
  }

  // какие-то строки нагрузки выбраны чекбоксами
  $scope.SomeNagruzkaRowsSelected = function()
  {
    var some_selected = false;

    angular.forEach($scope.nagruzka, function(nagruzka)
    {
      // CL(nagruzka.selected);

      if (nagruzka.selected)
      {
        // CL('selected');
        some_selected = true;
      }
    });

    // CL(some_selected);

    return some_selected;
  }

  // получить строки нагрузки с учётом фильтров
  /*
  $scope.GetFilteredNagruzkaRows = function()
  {
    if (isEmpty($scope.dtInstance) || isEmpty($scope.dtInstance.dataTable.fnSettings())) return [];

    var filtered_nagruzka = [];
    var _column;
    var _column_filter_value = '';

    angular.forEach($scope.nagruzka, function(nagruzka)
    {
      var all_column_filters_suit = true;

      for (var i = 0; i < columns.length; i++)
      {
        _column = columns[i];
        // CL(_column);

        // столбец описан и потенциально участвует в фильтрации
        if (!isEmpty(_column) && !isEmpty(_column['name']) && !isEmpty(_column['type']))
        {
          // CL('here');

          // непосредственно значение фильтра (если не пусто)
          _column_filter_value = $scope.dtInstance.dataTable.fnSettings().aoPreSearchCols[i].sSearch;

          // CL(_column['name']);
          // CL($scope.dtInstance.dataTable.fnSettings().aoPreSearchCols[i]);

          if (!isEmpty(_column_filter_value))
          {
            // CL(_column_filter_value);
          }
          else
          {
            continue;
          }

          // if (nagruzka[_column['name']] != _column_filter_value)
          // значения разные
          if (_column['type'] == 'select' && nagruzka[_column['name']].localeCompare(_column_filter_value, 'ru', { sensitivity: 'base' }) != 0)
          {
            all_column_filters_suit = false;
            break;
          }
          else if (_column['type'] == 'input' && !nagruzka[_column['name']].includes(_column_filter_value))
          {
            if (_column['name'] == 'discipline_name')
            {
              // CL(nagruzka[_column['name']]);
              // CL(_column_filter_value);
            }
            all_column_filters_suit = false;
            break;
          }
        }
      }

      if (all_column_filters_suit)
      {
        filtered_nagruzka.push(nagruzka);
      }
    });

    // CL(filtered_nagruzka.length);

    return filtered_nagruzka;
  }
  */

  $scope.GetFilteredNagruzkaRowsIndexes = function()
  {
    var filtered_rows_inds_arr = [];

    if ($scope.dtInstance.dataTable)
    {
      filtered_rows_inds_arr = $scope.dtInstance.dataTable.DataTable().rows({ search: 'applied'}).indexes().toArray();
    }

    return filtered_rows_inds_arr;
  }


  $scope.GetNagruzkaSelectedCount = function()
  {
    return $scope.nagruzka.filter(i => i.selected).length;
  }

  // Выбрать все видимые (после фильтрации) строки нагрузки
  $scope.SelectAllFilteredNagruzkaRows = function()
  {
    CL('SelectAllFilteredNagruzkaRows');

    const filtered_rows_indexes = $scope.GetFilteredNagruzkaRowsIndexes();

    // очистим выбор всех строк нагрузки
    $scope.nagruzka.forEach(item => item.selected = false);

    // проставим выбор отфильтрованных строк нагрузки
    var filtered_nagruzka_rows = filtered_rows_indexes.map(function(i) 
    {
      if ($scope.IsNagruzkaEditable($scope.nagruzka[i]))
      {
        $scope.nagruzka[i].selected = true;
      }
      return $scope.nagruzka[i];
    });

    // CL(filtered_nagruzka_rows);

    // CL($scope.dtInstance.dataTable.fnSettings().aoPreSearchCols);
    // var filtered_rows_columns_arr = $scope.dtInstance.dataTable.DataTable().rows({ search: 'applied'}).indexes().toArray();

    // CL($scope.dtInstance.dataTable.DataTable().rows({ search: 'applied'}).indexes().toArray());
    // CL($scope.dtInstance.dataTable.fnSettings().aoPreSearchCols[1].sSearch);
  }

  $scope.DeselectSelectAllFilteredNagruzkaRows = function()
  {
     // очистим выбор всех строк нагрузки
    $scope.nagruzka.forEach(item => item.selected = false);
  }

  $scope.GroupActionSelectedLecturer = function(data)
  {
    CL('GroupActionSelectedLecturer');
    
    CL(data);

    if (!isEmpty(data))
    {
      $scope.group_action.lecturer_fio = data.originalObject.fio;
      $scope.group_action.lecturer_uid = data.originalObject.lecturer_uid;
      $scope.group_action.lecturer_person_id = data.originalObject.person_id;
    }

  }

  $scope.ChangeGroupAction = function()
  {
    $scope.group_action.lecturer_fio = $scope.group_action.lecturer_uid = $scope.group_action.lecturer_person_id = undefined;
  }


  $scope.DoGroupAction = function()
  {
    // Распределить всё на одного сотрудника
    if ($scope.group_action.action == 'assign_to_sotrudnik' && !isEmpty($scope.group_action.lecturer_fio))
    {
      $scope.nagruzka.forEach(nagruzka_row => 
      {
        // CL(item);
        if (nagruzka_row.selected)
        {
          nagruzka_row.lecturer_fio = $scope.group_action.lecturer_fio;
          nagruzka_row.lecturer_uid = $scope.group_action.lecturer_uid;
          nagruzka_row.lecturer_person_id = $scope.group_action.lecturer_person_id;

          SaveNagruzkaLecturer(nagruzka_row);
        }
      });
    }
    // Распределить всё на «вакансию»
    else if ($scope.group_action.action == 'assign_to_vacancy')
    {
      $scope.nagruzka.forEach(nagruzka_row => 
      {
        // CL(item);
        if (nagruzka_row.selected)
        {
          nagruzka_row.lecturer_fio = 'Вакансия';
          nagruzka_row.lecturer_uid = '26115.281474976893938';
          nagruzka_row.lecturer_person_id = '00000';

          SaveNagruzkaLecturer(nagruzka_row);
        }
      });
    }
    // Отказаться от выбранной нагрузки
    else if ($scope.group_action.action == 'refuse_nagruzka')
    {
      if (!$scope.group_action.message || !$scope.group_action.message.length)
      {
        toastr.error("Введите обязательный комментарий");
      }
      else
      {
        $scope.nagruzka.forEach(nagruzka_row => 
        {
          if (nagruzka_row.selected)
          {
            SaveNagruzkaStatus(nagruzka_row, 'refused');
          }
        });
      }
    }
    // Запрос администратору на внесение изменений
    else if ($scope.group_action.action == 'require_admin_change')
    {
      if (!$scope.group_action.message || !$scope.group_action.message.length)
      {
        toastr.error("Введите обязательный комментарий");
      }
      else
      {
        ngDialog.openConfirm({
                template: 'confirm_require_admin_change',
                className: 'ngdialog-theme-default',
                disableAnimation: true
            }).then(function (value) {  // да

                $scope.nagruzka.forEach(nagruzka_row => 
                {
                  if (nagruzka_row.selected)
                  {
                    SaveNagruzkaStatus(nagruzka_row, 'require_admin_change');

                    nagruzka_row.lecturer_fio = nagruzka_row.lecturer_uid = nagruzka_row.lecturer_person_id = '';
                  }
                });
            });

        
      }
    }
    // Написать комментарий администратору
    else if ($scope.group_action.action == 'write_admin_comment')
    {
      if (!$scope.group_action.message || !$scope.group_action.message.length)
      {
        toastr.error("Введите обязательный комментарий");
      }
      else
      {
        $scope.nagruzka.forEach(nagruzka_row => 
        {
          if (nagruzka_row.selected)
          {
            // CL(nagruzka_row);
            SaveNagruzkaStatus(nagruzka_row, 'write_admin_comment');
          }
        });
      }
    }
    else return;

    $scope.group_action.action = undefined;
  }

  function SaveNagruzkaStatus(nagruzka_row, new_status)
  {
    $http({url: 'ajax/post/save_nagruzka_status.php', method: 'POST', data: {status: new_status, message: $scope.group_action.message, load_base_UID: nagruzka_row.base_uid}})
                .then(function(data)
                {
                  if (data.data.result == 'success')
                  {
                    nagruzka_row.status = new_status;

                    if (new_status == 'write_admin_comment')
                    {
                      nagruzka_row.comment_to_admin = $scope.group_action.message;
                    }

                    toastr.success("Данные сохранены");

                    $scope.nagruzka.forEach(nagruzka_row => 
                    {
                      if (nagruzka_row.selected = false);
                    });

                    $scope.group_action.action = undefined;
                    $scope.group_action.message = '';
                  }
                  else
                  {
                    toastr.error("Ошибка");
                  }
                });
  }


  $scope.IsNagruzkaEditable = function(nagruzka_row)
  {
    const editable = !['refused', 'require_admin_change', 'done_change'].includes(nagruzka_row.status);

    // CL(nagruzka_row.status);
    // CL(editable);
    return editable;
  }

  $scope.ShowNagruzkaZavkafTypeRow = function(type)
  {
    return isEmpty(nagruzka_type) || nagruzka_type == 'all' || type == nagruzka_type;
  }

})

.controller ('UOUPNagruzkaCtrl', function($rootScope, $scope, $http, DTOptionsBuilder, DTColumnBuilder, DTColumnDefBuilder, ngDialog, $templateCache, $resource, uoup_nagruzka, nagruzka_uoup_stat, page)
{
  CL('UOUPNagruzkaCtrl');

  $templateCache.put('confirm_delete', '<p>Вы уверены, что хотите удалить?</p>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Нет</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(1)">Да</button>\
              </div>');

  $rootScope.page = page;
  $scope.$_forms_obuchenia = $_forms_obuchenia;

  $scope.dtInstance = {};
  $scope.filter_distinct = {};
  $scope.group_action = {};
  $scope.uoup_nagruzka = uoup_nagruzka.data;
  $scope.nagruzka_uoup_stat = nagruzka_uoup_stat.data;

  $scope.isLoading = true;

  // LoadNagruzkaUOUPStat();

  const columns = [
      
      // факультет
      {
        name: 'department_name',
        type: 'select',
        bRegex: false,
        // values: $scope.filter_distinct['department_name']
        // bSmart: true
      }, 
      // кафедра
      {
        name: 'chair',
        type: 'select',
        bRegex: false,
      },
      // заведующий
      {
        name: 'zavkaf',
        type: 'input',
        bRegex: false,
      },
      // всего нагрузки
      null,
      // распределено
      null,
      // не распределено
      null,
      // на вакансии
      null,
      
  ];


  // $scope.persons = $resource('data.json').query();

  $scope.dtOptions = DTOptionsBuilder //.fromSource('data.json')
    .newOptions()
    .withOption('stateSave', true)
    // .withOption('aoColumns', [{bVisible': false}])
    .withPaginationType('full_numbers')
    // .withColVis()
    // Add a state change function
    // .withColVisStateChange(stateChange)
    // Exclude the last column from the list
    // .withColVisOption('aiExclude', [])
    // Т.к. на этой странице нет скрытия столбцов, то нет проблемы съезжания фильтров, поэтому используем штатный механизм
    .withColumnFilter({
        aoColumns: columns
    })
    .withOption('initComplete', function(settings, json) {
        // Скрываем индикатор когда загрузка завершена
        $scope.$apply(function() {
            $scope.isLoading = false;
        });
      })
    ;

  $scope.dtColumnDefs = [
    // DTColumnDefBuilder.newColumnDef(7).notVisible().notSortable(),
  ];

  
  // УОУП открывает на просмотр нагрузку кафедры
  $scope.UOUPOpenChairNagruzka = function(chair_id)
  {
    CL('UOUPOpenChairNagruzka');
    CL(chair_id);

    if (!isEmpty(chair_id))
    {
      window.location = '#/nagruzka/all/' + chair_id;
    }
  }


  $scope.ShowNagruzkaUOUPTypeRow = function()
  {
    return true;
  }

  
})

.controller ('SystemModeCtrl', function($rootScope, $scope, page, system_mode, $http)
{
  CL('SystemModeCtrl');
  
  $rootScope.page = page;
  
  // Placeholder for future functionality
  $scope.systemModes = $_system_modes;
  $scope.currentMode = system_mode.data.mode;

  CL($scope.currentMode);

  // Сохранить режим работы
  $scope.SaveSystemMode = function()
  {
    CL('SaveSystemMode');
    CL($scope.currentMode);

    $http.post('ajax/post/save_system_mode.php', {mode: $scope.currentMode})
      .then(function(response)
      {
        toastr.success('Режим работы сохранен');
      });
  }
})

// Админ УОУП просматривает отказы зав. кафедрами от нагрузки и отменяет отказы
.controller ('UOUPChairsRefusedCtrl', function($rootScope, $scope, $http, DTOptionsBuilder, DTColumnBuilder, DTColumnDefBuilder, ngDialog, $templateCache, $resource, uoup_nagruzka, system_mode)
{
  CL('UOUPChairsRefusedCtrl');

  $templateCache.put('confirm_delete', '<p>Вы уверены, что хотите удалить?</p>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Нет</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(1)">Да</button>\
              </div>');

  $rootScope.page = 'uoup_chairs_refused';
  // $scope.$_forms_obuchenia = $_forms_obuchenia;
  $scope.system_mode = system_mode.data.mode;
  // CL($scope.system_mode);

  $scope.dtInstance = {};
  // заглушка
  $scope.filter_distinct = {};
  // $scope.group_action = {};
  $scope.nagruzka = uoup_nagruzka.data;

  const columns = [
      null, 
      // факультет
      {
        name: 'department_name',
        type: 'select',
        bRegex: false,
        // bSmart: true
      }, 
      // кафедра
      {
        name: 'Abbr',
        type: 'select',
        bRegex: false,
      },
      // аббревиатура
      {
        name: 'Abbr',
        type: 'input',
        bRegex: false,
      },
      // дисциплина
      {
        name: 'discipline_name',
        type: 'input',
        bRegex: false,
      },
      null,
      // уровень образования
      {
        name: 'education_level',
        type: 'select',
        bRegex: false,
      },
      // направление подготовки
      {
        name: 'napravlenie',
        type: 'select',
        bRegex: false,
      },
      // язык программы
      {
        name: 'language',
        type: 'select',
        bRegex: false,
      },
      // форма обучения
      {
        name: 'form_obuchenia',
        type: 'select',
        bRegex: false,
        values: ['Очная', 'Заочная', 'Очная-заочная']
      },
      // семестр
      {
        name: 'UID_Semester',
        type: 'select',
        bRegex: false,
        values: ['1', '2', '3', '4', '5', '6', '7'],
        width: '100'
      },
      null,
      // вид работ
      {
        name: 'kind_of_work',
        type: 'select',
        bRegex: false,
        // values: ['1', '2', '3', '4', '5', '6', '7']
      },
      null,
      // курс
      {
        name: 'UID_Course',
        type: 'select',
        bRegex: false,
        values: ['1', '2', '3', '4', '5', '6', '7']
      },
      null
  ];

  // $scope.columns = columns;

  $scope.dtOptions = DTOptionsBuilder //.fromSource('data.json')
    .newOptions()
    .withOption('stateSave', true)
    // .withOption('aoColumns', [{bVisible': false}])
    .withPaginationType('full_numbers')
    .withColVis()
    // Add a state change function
    // .withColVisStateChange(stateChange)
    // Exclude the last column from the list
    .withColVisOption('aiExclude', [0]) //, 1, 6, 8, 9, 10, 14])
    // .withColumnFilter({
    //     aoColumns: columns
    // })
    .withOption('initComplete', function(settings, json) {
        // Скрываем индикатор когда загрузка завершена);
        $scope.$apply(function() {
            $scope.isLoading = false;
        });
      })
    .withOption('drawCallback', function(settings) {
        const table = angular.element('#DataTables_Table_uoup_chairs_refused').dataTable().api();
        createCustomFilters('DataTables_Table_uoup_chairs_refused', table, columns);
      })
    ;

  // Возможность отключить сортировку и видимость столбцов по-умолчанию
  $scope.dtColumnDefs = [
    DTColumnDefBuilder.newColumnDef(0).notSortable(), // notVisible()
  ];

  // Наблюдение за изменением dtInstance, чтобы сделать некоторые инициализации
  $scope.$watch('dtInstance', function(newValue) 
  {
    if (newValue && newValue.DataTable) 
    {
      const table = newValue.DataTable;

      // CL(table);
      // These are the same
      // CL($scope.dtInstance.dataTable.DataTable());

      // Инициализация фильтров при старте
      createCustomFilters('DataTables_Table_uoup_chairs_refused', table, columns);

      // Сброс и пересоздание фильтров при изменении видимости столбцов
      table.on('column-visibility.dt', function() {
        createCustomFilters('DataTables_Table_uoup_chairs_refused', table, columns);
      });
    }
  });


  // Отклонить отказ зав. каф. от нагрузки
  $scope.UOUPCancelRefuse = function(nagruzka_row)
  {
    $templateCache.put('comment_and_cancel_refuse', '<p>Введите причину отказа:</p>\
              <div><textarea ng-model="message" class="form-control w-100 mb-2" style="height: 100px"></textarea></div>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(message)" ng-disabled="!message.length">Отменить отказ</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Закрыть</button>\
              </div>');

    var dialogScope = $scope.$new();

    ngDialog.openConfirm({
                template: 'comment_and_cancel_refuse',
                scope: dialogScope,
                className: 'ngdialog-theme-default', //  ngdialog-positions
                disableAnimation: true,
                preCloseCallback: function(value)
                {
                  return true;
                }
            })
            .then(function (message) {  // да

              $http({url: 'ajax/post/uoup_cancel.php', method: 'POST', data: {base_uid: nagruzka_row.base_uid, chair_id: nagruzka_row.chair_id, chair_name: nagruzka_row.chair_name, zavkaf_fio: nagruzka_row.zavkaf_fio, action: 'Администратор УОУП отклонил отказ кафедры от нагрузки', message: message}})
                .then(function(data)
                {
                  if (data.data.result == 'success')
                  {
                    toastr.success("Данные сохранены");
                    nagruzka_row.status = 'initial';
                  }
                  else
                  {
                    toastr.error("Ошибка");
                  }
                });

            })
            .catch(function dialogCloseErrorCallback(reason) {
                    // ngDialog v1.4.0 throws an exception, when closing the dialog with reason “undefined”.
            });
  }

  
})

.controller ('UOUPNagruzkaToChangeCtrl', function($rootScope, $scope, $http, $filter, DTOptionsBuilder, DTColumnBuilder, DTColumnDefBuilder, ngDialog, $templateCache, $resource, $timeout, uoup_nagruzka, system_mode)
{
  CL('UOUPNagruzkaToChangeCtrl');

  $templateCache.put('confirm_delete', '<p>Вы уверены, что хотите удалить?</p>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Нет</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(1)">Да</button>\
              </div>');

  $rootScope.page = 'uoup_nagruzka_to_change';
  // $scope.$_forms_obuchenia = $_forms_obuchenia;
  $scope.system_mode = system_mode.data.mode;

  $scope.dtInstance = {};
  // заглушка
  $scope.filter_distinct = {};

  $scope.allNagruzka = Array.isArray(uoup_nagruzka.data) ? angular.copy(uoup_nagruzka.data) : [];
  $scope.filteredNagruzka = angular.copy($scope.allNagruzka);
  $scope.adminChangeChairs = buildAdminChangeChairs($scope.allNagruzka);
  $scope.selectedAdminChangeChair = null;
  $scope.selectedChairComment = null;
  $scope.chairComments = [];
  $scope.isFiltering = false;
  $scope.viewState = 'chairs'; // 'chairs' or 'table'

  function buildAdminChangeChairs(rows)
  {
    if (!Array.isArray(rows) || !rows.length) return [];

    const chairs = {};

    rows.forEach(function(row)
    {
      const chairId = (row.chair_id || '').toString();
      if (!chairId) return;

      if (!chairs[chairId])
      {
        const chairTitle = (row.chair_name || '').replace(/<br\s*\/?\>/gi, ', ');
        chairs[chairId] = {
          chair_id: chairId,
          chair_name: chairTitle || 'Кафедра не указана',
          count: 0
        };
      }

      chairs[chairId].count += 1;
    });

    return Object.values(chairs).sort(function(a, b)
    {
      return a.chair_name.localeCompare(b.chair_name, 'ru');
    });
  }

  function buildChairComments(rows)
  {
    if (!Array.isArray(rows) || !rows.length) return [];

    const comments = {};

    rows.forEach(function(row)
    {
      const dateRaw = row.require_admin_change_date || '';
      const messageRaw = row.require_admin_change_message || '';
      const key = dateRaw + '__' + messageRaw;

      if (!comments[key])
      {
        comments[key] = {
          key: key,
          dateRaw: dateRaw,
          dateFormatted: dateRaw ? $filter('jsDate')(dateRaw) : 'Дата не указана',
          messageRaw: messageRaw,
          count: 0
        };
      }

      comments[key].count += 1;
    });

    return Object.values(comments).sort(function(a, b)
    {
      if (a.dateRaw === b.dateRaw)
      {
        return a.messageRaw.localeCompare(b.messageRaw, 'ru');
      }

      return (b.dateRaw || '').localeCompare(a.dateRaw || '');
    });
  }

  function applyFilters()
  {
    let filtered = angular.copy($scope.allNagruzka);

    if ($scope.selectedAdminChangeChair)
    {
      filtered = filtered.filter(function(row)
      {
        return row.chair_id == $scope.selectedAdminChangeChair.chair_id;
      });
    }

    if ($scope.selectedChairComment)
    {
      filtered = filtered.filter(function(row)
      {
        return row.require_admin_change_date === $scope.selectedChairComment.dateRaw &&
               row.require_admin_change_message === $scope.selectedChairComment.messageRaw;
      });
    }

    $scope.filteredNagruzka = filtered;
  }

  function rerenderDataTable() {
    if ($scope.dtInstance && $scope.dtInstance.rerender) {
      $scope.dtInstance.rerender();
    }
  }

  $scope.toggleAdminChangeChair = function(chair) {
    if (!chair) return;

    if ($scope.selectedAdminChangeChair && $scope.selectedAdminChangeChair.chair_id === chair.chair_id) {
      $scope.selectedAdminChangeChair = null;
      $scope.chairComments = [];
    } else {
      $scope.selectedAdminChangeChair = chair;
      $scope.chairComments = buildChairComments(
        $scope.allNagruzka.filter(function(row) {
          return row.chair_id == chair.chair_id;
        })
      );
    }
  };

  $scope.toggleChairComment = function(comment) {

    CL('toggleChairComment');

    if (!comment || !$scope.selectedAdminChangeChair) return;

    $scope.selectedChairComment = comment;
    applyFilters();
    $scope.viewState = 'table';
  };

  $scope.showChairs = function() {
    $scope.viewState = 'chairs';
    $scope.selectedAdminChangeChair = null;
    $scope.selectedChairComment = null;
    applyFilters();
  };

  const columns = [
      null, 
      // факультет
      {
        name: 'department_name',
        type: 'select',
        bRegex: false,
        // bSmart: true
      }, 
      // кафедра
      {
        name: 'Abbr',
        type: 'select',
        bRegex: false,
      },
      // аббревиатура
      {
        name: 'Abbr',
        type: 'input',
        bRegex: false,
      },
      // дисциплина
      {
        name: 'discipline_name',
        type: 'input',
        bRegex: false,
      },
      null,
      // уровень образования
      {
        name: 'education_level',
        type: 'select',
        bRegex: false,
      },
      // направление подготовки
      {
        name: 'napravlenie',
        type: 'select',
        bRegex: false,
      },
      // язык программы
      {
        name: 'language',
        type: 'select',
        bRegex: false,
      },
      // форма обучения
      {
        name: 'form_obuchenia',
        type: 'select',
        bRegex: false,
        values: ['Очная', 'Заочная', 'Очная-заочная']
      },
      // семестр
      {
        name: 'UID_Semester',
        type: 'select',
        bRegex: false,
        values: ['1', '2', '3', '4', '5', '6', '7'],
        width: '100'
      },
      null,
      // вид работ
      {
        name: 'kind_of_work',
        type: 'select',
        bRegex: false,
        // values: ['1', '2', '3', '4', '5', '6', '7']
      },
      null,
      // курс
      {
        name: 'UID_Course',
        type: 'select',
        bRegex: false,
        values: ['1', '2', '3', '4', '5', '6', '7']
      },
  ];


  // $scope.persons = $resource('data.json').query();

  $scope.dtOptions = DTOptionsBuilder.newOptions()
    .withOption('stateSave', true)
    .withPaginationType('full_numbers')
    .withColVis()
    .withColVisOption('aiExclude', [0])
    .withOption('initComplete', function(settings, json) {
      CL('initComplete');
      $scope.$apply(function() {
        $scope.isLoading = false;
      });

      const api = this.api();
      
      // Получаем legacy объект (jQuery с плагином) для ClearGreenTableFilters
      const legacyTable = $(api.table().node()).dataTable();
      const tempDtInstance = { dataTable: legacyTable };
      $scope.ClearGreenTableFilters(tempDtInstance, $scope.filter_distinct);

      createCustomFilters('DataTables_Table_nagruzka_to_change', api, columns);
      
      api.on('column-visibility.dt', function() {
        createCustomFilters('DataTables_Table_nagruzka_to_change', api, columns);
      });
    });

  // Возможность отключить сортировку и видимость столбцов по-умолчанию
  $scope.dtColumnDefs = [
    DTColumnDefBuilder.newColumnDef(0).notSortable(), // notVisible()
  ];


  // Отклонить запрос зав. каф. на изменение
  // комментарий обязателен
  $scope.UOUPDeclineToChange = function(nagruzka_row)
  {
    $templateCache.put('comment_and_cancel_to_change', '<p>Введите причину отказа:</p>\
              <div><textarea ng-model="message" class="form-control w-100 mb-2" style="height: 100px"></textarea></div>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(message)" ng-disabled="!message.length">Отклонить</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Закрыть</button>\
              </div>');

    var dialogScope = $scope.$new();

    ngDialog.openConfirm({
                template: 'comment_and_cancel_to_change',
                scope: dialogScope,
                className: 'ngdialog-theme-default', //  ngdialog-positions
                disableAnimation: true,
                preCloseCallback: function(value)
                {
                  return true;
                }
            })
            .then(function (message) {  // да

              $http({url: 'ajax/post/uoup_cancel.php', method: 'POST', data: {base_uid: nagruzka_row.base_uid, chair_id: nagruzka_row.chair_id, chair_name: nagruzka_row.chair_name, zavkaf_fio: nagruzka_row.zavkaf_fio, action: 'Админ УОУП отклонил запрос кафедры на внесение изменений', message: message}})
                .then(function(response)
                {
                  if (response.data.result == 'success')
                  {
                    toastr.success("Данные сохранены");
                    nagruzka_row.status = 'initial';
                  }
                  else
                  {
                    toastr.error("Ошибка");
                  }
                });

            })
            .catch(function dialogCloseErrorCallback(reason) {
                    // ngDialog v1.4.0 throws an exception, when closing the dialog with reason “undefined”.
            });
  }


  // Выполнить запрос зав. каф. на изменение
  // комментарий НЕ обязателен
  $scope.UOUPDoneToChange = function(nagruzka_row)
  {
    $templateCache.put('comment_and_done_change', '<p>Комментарий:</p>\
              <div><textarea ng-model="message" class="form-control w-100 mb-2" style="height: 100px"></textarea></div>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(message)" >Выполнено</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Закрыть</button>\
              </div>');

    var dialogScope = $scope.$new();

    ngDialog.openConfirm({
                template: 'comment_and_done_change',
                scope: dialogScope,
                className: 'ngdialog-theme-default', //  ngdialog-positions
                disableAnimation: true,
                preCloseCallback: function(value)
                {
                  return true;
                }
            })
            .then(function (message) {  // да

              $http({url: 'ajax/post/uoup_done_change.php', method: 'POST', data: {base_uid: nagruzka_row.base_uid, chair_id: nagruzka_row.chair_id, chair_name: nagruzka_row.chair_name, zavkaf_fio: nagruzka_row.zavkaf_fio, message: message}})
                .then(function(data)
                {
                  if (data.data.result == 'success')
                  {
                    toastr.success("Данные сохранены");
                    nagruzka_row.status = 'cancelling_to_change';
                  }
                  else
                  {
                    toastr.error("Ошибка");
                  }
                });

            })
            .catch(function dialogCloseErrorCallback(reason) {
                    // ngDialog v1.4.0 throws an exception, when closing the dialog with reason “undefined”.
            });
  }

  
})

.controller ('SotrudnikiCtrl', function($rootScope, $scope, $http, ngDialog, $templateCache, DTOptionsBuilder, DTColumnBuilder, DTColumnDefBuilder, $resource, system_mode)
{
  CL('SotrudnikiCtrl');

  $scope.system_mode = system_mode.data.mode;

  if (c_roles.zavkaf && $scope.system_mode === 'mode_closed')
  {
    window.location = '#/system_closed';
  }

  $scope.sotrudniki_readonly = c_roles.zavkaf && $scope.system_mode === 'mode_verification';

  $templateCache.put('confirm_delete', '<p>Вы уверены, что хотите удалить?</p>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Нет</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(1)">Да</button>\
              </div>');

  $rootScope.page = 'sotrudniki';
  $scope.$_sotrudnik_types = $_sotrudnik_types;

  $scope.dtInstance = {};

  $scope.persons = $resource('ajax/get/chair_sotrudniki.php').query(
    function()
    {
      if (Array.isArray($scope.persons))
      {
        angular.forEach($scope.persons, function(person)
        {
          if (person.type == 'sotrudnik')
          {
            person.selected = true;
          }
        });
      }
    });

  $scope.dtOptions = DTOptionsBuilder //.fromSource('data.json')
        .newOptions()
        .withOption('stateSave', true)
        .withPaginationType('full_numbers')
        .withColVis()
        // Add a state change function
        .withColVisStateChange(stateChange)
        // Exclude the last column from the list
        // .withColVisOption('aiExclude', [2])
        .withOption('initComplete', function(settings, json) {
          // Скрываем индикатор когда загрузка завершена);
          $scope.$apply(function() {
              $scope.isLoading = false;
          });
        })
        ;

        $scope.dtColumnDefs = [
          DTColumnDefBuilder.newColumnDef(0),
          DTColumnDefBuilder.newColumnDef(1),
          DTColumnDefBuilder.newColumnDef(2),

        ];

        // CL($scope.dtOptions);

    function stateChange(iColumn, bVisible) {
        console.log('The column', iColumn, ' has changed its status to', bVisible);
    }

    // $scope.dtColumns = [
    //     DTColumnBuilder.newColumn('id').withTitle('ID'),
    //     DTColumnBuilder.newColumn('firstName').withTitle('First name'),
    //     DTColumnBuilder.newColumn('lastName').withTitle('Last name')
    // ];

    /*
    $scope.ClearGreenTableFilters = function()
    {
      // CL('ClearGreenTableFilters');

      // CL($scope.dtInstance);

      // return;

      const table = $scope.dtInstance.dataTable;

      // CL(table);

      // return;

      // Сброс глобального поиска
      table.fnFilter('');

      // Сброс фильтров для всех столбцов
      for (let i = 0; i < table.fnSettings().aoColumns.length; i++) {
          table.fnFilter('', i);
      }

      $('#DataTables_Table_1').find('tfoot tr select').val('').trigger('change');
      $('#DataTables_Table_1').find('tfoot tr input').val('').trigger('change');

      // Перерисовка таблицы
      table.fnDraw();
    }
    */

    $scope.ShowPopup = function(person)
    {
      CL('ShowPopup');

      $scope.selected_person = person;

      ngDialog.openConfirm({
                template: 'popup',
                scope: $scope,
                className: 'ngdialog-theme-default ngdialog-positions',
                disableAnimation: true,
                preCloseCallback: function(value)
                {
                  return true;
                }
            })
            .then(function (value) {  // да

            })
            .catch(function dialogCloseErrorCallback(reason) {
                    // ngDialog v1.4.0 throws an exception, when closing the dialog with reason “undefined”.
            });

    }


    $scope.SelectSotrudnik = function(person)
    {
      $http({url: 'ajax/post/select_sotrudnik.php', method: 'POST', data: {person_id: person.person_id, selected: person.selected}})
                .then(function(data)
                {
                  if (data.data.result == 'success')
                  {
                    // deleteByColumn($scope.admins_uoup, 'login', admin.login);
                    toastr.success("Данные сохранены");
                  }
                  else
                  {
                    toastr.error("Ошибка");
                  }
                });
    }

})

.controller ('AdminsUOUPCtrl', function($rootScope, $scope, $http, ngDialog, $templateCache, admins_uoup)
{
  CL('AdminsUOUPCtrl');

  $templateCache.put('confirm_delete', '<p>Вы уверены, что хотите удалить?</p>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Нет</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(1)">Да</button>\
              </div>');

  $scope.admins_uoup = admins_uoup.data;
  // CL(admins_uoup.data);

  $scope.new_admin_uoup = {};

  $rootScope.page = 'uoup';

  $scope.MayEditUOUP = function()
  {
    return c_roles.full;
  }


  $scope.focusOutAdminUOUP = function()
  {
    $scope.$broadcast('angucomplete-alt:clearInput', 'add_admin_uoup');
    $scope.new_admin_uoup = {};
  }

  $scope.onAdminUOUPSelect = function(data)
  {
    if (data)
    {
      $scope.new_admin_uoup = Object.assign($scope.new_admin_uoup, data.originalObject);
    }

    CL($scope.new_admin_uoup);
  }


  $scope.AddAdminUOUP = function()
  {
    // CL(findIndByColumn($scope.admins_uoup, 'login', $scope.new_admin_uoup.login));

    if (findIndByColumn($scope.admins_uoup, 'login', $scope.new_admin_uoup.login) == null)
    $http({url: 'ajax/post/add_admin_uoup.php', method: 'POST', data: $scope.new_admin_uoup})
        .then(function(data)
        {
          if (data.data.result == 'success')
          {
            $scope.admins_uoup.push(clone($scope.new_admin_uoup));

            $scope.focusOutAdminUOUP();
          }
          else
          {
            toastr.error("Ошибка");
          }
        });
  }

  $scope.DeleteAdminUOUP = function(admin)
  {

    ngDialog.openConfirm({
                template: 'confirm_delete',
                className: 'ngdialog-theme-default',
                disableAnimation: true
            }).then(function (value) {  // да

                $http({url: 'ajax/post/delete_admin_uoup.php', method: 'POST', data: {login: admin.login}})
                .then(function(data)
                {
                  if (data.data.result == 'success')
                  {
                    deleteByColumn($scope.admins_uoup, 'login', admin.login);
                    toastr.success("Администратор удалён");
                  }
                  else
                  {
                    toastr.error("Ошибка");
                  }
                });
            });


  }

}) 


// чтобы в директиве 'numberInput' в качестве разделителя тысяч был пробел вместо запятой
.filter('customNumber', function($filter) {
  return function(value) {
    // Используем стандартный фильтр 'number' для форматирования числа
    let formatted = $filter('number')(value);
    // Заменяем запятые на пробелы
    return formatted.replace(/,/g, '-');
  };
})

.filter('toFixed', function() {
  return function(input, decimals) {
    if (isNaN(input) || input === null || input === '') return input;

    var num = Number(input);
    var dec = Number(decimals) || 0;
    var x = Math.pow(10, dec + 1);

    return (num + (1 / x)).toFixed(dec);
  };
})


.directive('numberInput', function($filter) {
  return {
    require: 'ngModel',
    link: function(scope, elem, attrs, ngModelCtrl) {

      ngModelCtrl.$formatters.push(function(modelValue) {
        return setDisplayNumber(modelValue, true);
      });

      // CL('ab');

      // it's best to change the displayed text using elem.val() rather than
      // ngModelCtrl.$setViewValue because the latter will re-trigger the parser
      // and not necessarily in the correct order with the changed value last.
      // see http://radify.io/blog/understanding-ngmodelcontroller-by-example-part-1/
      // for an explanation of how ngModelCtrl works.
      ngModelCtrl.$parsers.push(function(viewValue) {
        setDisplayNumber(viewValue);
        return setModelNumber(viewValue);
      });

      // occasionally the parser chain doesn't run (when the user repeatedly 
      // types the same non-numeric character)
      // for these cases, clean up again half a second later using "keyup"
      // (the parser runs much sooner than keyup, so it's better UX to also do it within parser
      // to give the feeling that the comma is added as they type)
      elem.bind('keyup focus', function() {
        setDisplayNumber(elem.val());
      });

      function setDisplayNumber(val, formatter) {
        var valStr, displayValue;

        if (typeof val === 'undefined') {
          return 0;
        }

        valStr = val.toString();
        displayValue = valStr.replace(/,/g, '.').replace(/[A-Za-z]/g, '');

        displayValue = parseFloat(displayValue);
        displayValue = (!isNaN(displayValue)) ? displayValue.toString() : '';

        

        // handle leading character -/0
        if (valStr.length === 1 && valStr[0] === '-') {
          displayValue = valStr[0];
        } else if (valStr.length === 1 && (valStr[0] === '0' || valStr[0] === 0)) 
        {
          displayValue = '0';
        } else {
          displayValue = displayValue; //$filter('number')(displayValue);
        }

          // handle decimal
        if (!attrs.integer) {
          if (displayValue.indexOf('.') === -1) {
            if (valStr.slice(-1) === '.') {
              displayValue += '.';
            } else if (valStr.slice(-2) === '.0') {
              displayValue += '.0';
            } else if (valStr.slice(-3) === '.00') {
              displayValue += '.00';
            }
          } // handle last character 0 after decimal and another number
          else {
            if (valStr.slice(-1) === '0') {
              displayValue += '0';
            }
          }
        }

        if (attrs.positive && displayValue[0] === '-') {
          displayValue = displayValue.substring(1);
        }

        if (typeof formatter !== 'undefined') {
          return (displayValue === '') ? 0 : displayValue;
        } else {
          elem.val((displayValue === '0') ? '0' : displayValue);
        }
      }

      // function setModelNumber(val) {
      //   var modelNum = val.toString().replace(/,/g, '.').replace(/[A-Za-z]/g, '');
      //   modelNum = parseFloat(modelNum);
      //   modelNum = (!isNaN(modelNum)) ? modelNum : 0;
      //   if (modelNum.toString().indexOf('.') !== -1) {
      //     modelNum = Math.round((modelNum + 0.00001) * 100) / 100;
      //   }
      //   if (attrs.positive) {
      //     modelNum = Math.abs(modelNum);
      //   }
      //   return modelNum;
      // }

      function setModelNumber(val) 
      {
          var modelNum = val.toString().replace(/,/g, '.').replace(/[A-Za-z]/g, '');
          modelNum = parseFloat(modelNum);
          modelNum = (!isNaN(modelNum)) ? modelNum : 0; // Если NaN, возвращаем 0
          if (modelNum.toString().indexOf('.') !== -1) {
            modelNum = Math.round((modelNum + 0.00001) * 100) / 100;
          }
          if (attrs.positive) {
            modelNum = Math.abs(modelNum);
          }
          return modelNum;
      }
    }
  };
})

.factory('LDialog', function(ngDialog)
{
  return  {
    success: function(message)
    {
      ngDialog.open({
        template: "<h4><i class='glyphicon glyphicon-ok-sign'></i></h4><p>" + message + "</p>",
        plain: true,
        disableAnimation: true,
        className: 'ngdialog-theme-success'
      });


    },
    error: function(message)
    {
      ngDialog.open({
        template: "<h4><i class='glyphicon glyphicon-exclamation-sign'></i></h4><p>" + message + "</p>",
        plain: true,
        disableAnimation: true,
        className: 'ngdialog-theme-error'
      });
    }
  };
})

.directive('focus', function () {
  return function (scope, element, attrs) {
    attrs.$observe('focus', function (newValue) {
      element[0].focus();
      // CL('h');
      // or, if you don't like side effects (see @Christophe's comment):
      //if(newValue === 'true')  element[0].focus();
    });
  }
})

.filter("nl2br", function($filter) {
 return function(data) {
   if (!data) return data;
   return data.replace(/\n\r?/g, '<br />');
 };
})

.filter("br2nl", function($filter) {
 return function(data) {
   if (!data) return data;
   return data.replace(/<br\s*\/?>/gi, ', ');
 };
})

.filter("jsDate", function () {

    return function (x) {

      if (!x || !x.length) return '';

      var dateTimeArr = x.split(' ');
      var dateArr = dateTimeArr[0].split('-');
      // var date = new Date(dateArr[0], dateArr[1] - 1, dateArr[2]);

      // 
      return  dateArr[2] + "." + dateArr[1] + "." + dateArr[0] + (dateTimeArr[1] ? (" " + dateTimeArr[1]) : '');

        // return new Date(parseInt(x.substr(6)));
    };
})

.filter('orderObjectBy', function() {
  return function(items, field, reverse) {
    const filtered = [];
    angular.forEach(items, function(value, key) {
      filtered.push({ key: key, value: value });
    });
    filtered.sort(function(a, b) {
      return (a.value > b.value ? 1 : -1) * (reverse ? -1 : 1);
    });
    return filtered;
  };
})
;





// Функция поиска в массиве простого значения с приведением типов к целому
// В т.ч. для поиска cfo_id
function findValueInArray(array, value) 
{
  // Приводим все элементы массива к целым числам
  const transformedArray = array.map(item => parseInt(item, 10));

  // Приводим значение к целому числу
  const integerValue = parseInt(value, 10);

  // CL(transformedArray);

  // Проверяем, есть ли значение в массиве
  return transformedArray.includes(integerValue);
}


// Функция для форматирования даты в dd.mm.yyyy
function formatDate(date) {
    const day = String(date.getDate()).padStart(2, '0'); // Получаем день и добавляем ведущий ноль
    const month = String(date.getMonth() + 1).padStart(2, '0'); // Получаем месяц (0-11) и добавляем ведущий ноль
    const year = date.getFullYear(); // Получаем год

    return `${day}.${month}.${year}`; // Форматируем строку даты
}



function isEmptyObject(obj)
{
  return obj == null || obj == undefined || Object.keys(obj).length === 0 && obj.constructor === Object || obj.constructor !== Object
}

// Возвращает только один элемент
function findObjByColumn(arr, column, value)
{
  for (var i = 0; i < arr.length; i++)
  {
    if (arr[i][column] == value)
    {
      return arr[i];
    }
  }

  return null;
}

// Возвращает все элементы
function findAllObjByColumn(arr, column, value)
{
  var result_array = [];

  for (var i = 0; i < arr.length; i++)
  {
    if (arr[i][column] == value)
    {
      result_array.push(arr[i]);
    }
  }

  if (!result_array.length) return null;
  else return result_array;
}

// Только сосчитать количество
function findObjCountByColumn(arr, column, value)
{
  var count = 0;

  for (var i = 0; i < arr.length; i++)
  {
    if (arr[i][column] == value)
    {
      count++;
    }
  }

  return count;
}


function findIndByColumn(arr, column, value)
{
  // var index = null;

  for (var i = 0; i < arr.length; i++)
  {
    if (arr[i][column] == value)
    {
      return i;
    }
  }

  return null;
}


function deleteByColumn(arr, column, value)
{
  for (var i = 0; i < arr.length; i++)
  {
    if (arr[i][column] == value)
    {
      arr.splice(i, 1);
      // CL(i);
      return;
    }
  }
}

function isObjectWithSingleProperty(variable) {
  if (typeof variable === 'object' && variable !== null) {
    var keys = Object.keys(variable);
    return keys.length === 1;
  }
  return false;
}

function getSingleKeyOfObject(variable) {
  if (typeof variable === 'object' && variable !== null) {
    var keys = Object.keys(variable);
    if (keys.length === 1) {
      return keys[0];
    }
  }
  return null; // Возвращаем null, если объект не соответствует условиям
}

function truncateStringRegex(str, limit) {
  const truncated = str.slice(0, limit);
  return truncated.replace(/\s+\S*$/, '') + "...";
}

function truncateString(str, limit) {
  if (str.length <= limit) return str;

  // Обрезаем строку до лимита
  let truncated = str.slice(0, limit);

  // Ищем последнее пробелоподобное место (где кончается слово)
  let lastSpace = truncated.lastIndexOf(" ");
  if (lastSpace > 0) {
    truncated = truncated.slice(0, lastSpace);
  }

  return truncated + "..."; // Добавляем троеточие для обозначения продолжения
}


function strip_tags(str)
{
    return str
             .replace(/(<(br[^>]*)>)/ig, '\n')
             .replace(/(<([^>]+)>)/ig,'');
}

function html_to_text(str)
{
    return str
             .replace(/\s+(<.*>)/ig, '$1')
             .replace(/&nbsp;/ig, ' ')
             .replace(/(<(\/p[^>]*)>)/ig, '\n')
             .replace(/(<(br[^>]*)>)/ig, '\n')
             .replace(/(<([^>]+)>)/ig,'')
             .replace(/\n /ig, '\n')
             .trim()
             ;
}

function parse_price(str)
{
  str = strip_tags(str);

  // CL(str);

  str = str
          .trim()
          .replace(/,/, '.')
          .replace(/&nbsp;/ig, ' ')
          .replace(/\.+\s*$/, '')
          // .replace(/^(\d*\.{0,1}\d*)[^\d]+/, '$1')
          ;
          // .replace(/[.]+$/, '')

  return str;
}

function getRandom(min, max) {
  return Math.random() * (max - min) + min;
}

function clone(obj) {
    var copy;

    // Handle the 3 simple types, and null or undefined
    if (null == obj || "object" != typeof obj) return obj;

    // Handle Date
    if (obj instanceof Date) {
        copy = new Date();
        copy.setTime(obj.getTime());
        return copy;
    }

    // Handle Array
    if (obj instanceof Array) {
        copy = [];
        for (var i = 0, len = obj.length; i < len; i++) {
            copy[i] = clone(obj[i]);
        }
        return copy;
    }

    // Handle Object
    if (obj instanceof Object) {
        copy = {};
        for (var attr in obj) {
            if (obj.hasOwnProperty(attr)) copy[attr] = clone(obj[attr]);
        }
        return copy;
    }

    throw new Error("Unable to copy obj! Its type isn't supported.");
}

// whether object is empty
// function isEmpty(map) {
//    for(var key in map) {
//       return !map.hasOwnProperty(key);
//    }
//    return true;
// }

function isEmpty(value) {
  // Проверка на undefined и null
  if (value === undefined || value === null) {
    return true;
  }

  // Проверка на пустую строку (включая пробелы)
  if (typeof value === 'string' && value.trim() === '') {
    return true;
  }

  // Проверка на пустой массив
  if (Array.isArray(value) && value.length === 0) {
    return true;
  }

  // Проверка на пустой объект
  if (value.constructor === Object && Object.keys(value).length === 0) {
    return true;
  }

  // Проверка на специальные числовые значения
  if (typeof value === 'number' && (isNaN(value) || value === Infinity)) {
    return true;
  }

  // Проверка на булевое false (опционально)
  // if (typeof value === 'boolean' && !value) {
  //   return true;
  // }

  return false;
}

// whether object properies has true value
function hasTrueValue(map) {
   for(var key in map) {
      if (map[key]) return true;
   }
   return false;
}

// для рассчетов
function money_format(n) 
{
  var result = n.toFixed(2).replace(/./g, function(c, i, a) 
  {
      return c;
  });
  
  result = result.replace('.00', '');

  return parseFloat(result);
}

// для вывода на экран
function currency_filter(n) 
{
  if (!n) return 0;

  n = parseFloat(n);
  var result = n.toFixed(2).replace(/./g, function(c, i, a) 
  {
      return i > 0 && c !== "." && (a.length - i) % 3 === 0 ? "&#8239;" + c : c;  // &#8239;
  });
  
  result = result.replace('.00', '');
  return result;
}

function cost_for_table(cost)
{
  return currency_filter(cost) + "&nbsp;руб.";
}



function MysqlToDE($date)
{
  var dateArr = $date.split('-');
  return  dateArr[2] + "." + dateArr[1] + "." + dateArr[0];
}

// https://stackoverflow.com/questions/10015027/javascript-tofixed-not-rounding
// Возвращает строку!
function toFixed(number, decimals) {
        var x = Math.pow(10, Number(decimals) + 1);
        return (Number(number) + (1 / x)).toFixed(decimals)
    }

// Возвращает число
function roundToTwo(num) {    
    // return toFixed(+(Math.round(num + "e+2")  + "e-2"), 2);
    // return Number(toFixed(+(Math.round(num + "e+2") + "e-2"), 2));
  return Number(parseFloat(num).toFixed(2));
}
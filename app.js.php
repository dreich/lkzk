<?php

include 'functions.php';
session_name('lkzk');
session_start();
$c_access = $_SESSION['c_access'];
$c_login = $_SESSION['c_login'];
$c_fio = $_SESSION['c_fio'];
$c_person_id = $_SESSION['c_person_id'];

$_c_roles = ExplodePalki($_SESSION['c_roles']);
$c_roles = [];
if ($_c_roles)
foreach($_c_roles as $role)
{
  $c_roles[$role] = true;
}

?>

'use strict';


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

const $_nagruzka_types = 
{
  'discipline': 'Дисциплины',
  'ruk_vkr': 'Руководство ВКР',
  'ruk_kurs': 'Руководство курсовыми работами',
  'ruk_practice': 'Руководство практикой',
  'ksro': 'Индивидуальные консультации и КСРО',
  'gia': 'ГИА',
  'aspirant': 'Руководство аспирантами и соискателями, кандидатские экзамены',
};


const c_login = '<?=$c_login?>';
const c_fio = '<?=$c_fio?>';
const c_person_id = '<?=$c_person_id?>';
CL(c_person_id);
// роли авторизованного
const c_roles = {<?=ArrayToJS($c_roles)?>};
// справочник ролей
const $_roles = {<?=ArrayToJS($_roles)?>};
const $_sotrudnik_types = {<?=ArrayToJS($_sotrudnik_types)?>};
const $_forms_obuchenia = {<?=ArrayToJS($_forms_obuchenia)?>};
const $_degrees_codes = {<?=ArrayToJS($_degrees_codes)?>};
const $_system_modes = {<?=ArrayToJS($_system_modes)?>}; 
// id кафедры для зав. кафедрой
var c_chair_id = '<?=($_SESSION['c_chair_id'] ? $_SESSION['c_chair_id'] : '')?>';
CL(c_chair_id);
var c_department_id = '<?=($_SESSION['c_department_id'] ? $_SESSION['c_department_id'] : '')?>';
CL(c_department_id);
// id кафедр для сотрудника
const c_sotrudnik_chairs_ids = [<?=JoinArrayElements(ExplodePalki($_SESSION['c_sotrudnik_chairs_ids']), ', ', false, "'", "'")?>];
const c_sotrudnik_chairs_titles = [<?=JoinArrayElements(ExplodePalki($_SESSION['c_sotrudnik_chairs_titles']), ', ', false, "'", "'")?>];
const c_sotrudnik_lecturer_uids = [<?=JoinArrayElements(ExplodePalki($_SESSION['c_sotrudnik_lecturer_uids']), ', ', false, "'", "'")?>];
CL(c_sotrudnik_chairs_ids);
CL(c_sotrudnik_lecturer_uids);
CL(c_sotrudnik_chairs_titles);
const $_languages = {'25031.281474976715638': 'Русский', '25031.945': 'Английский'};

// HACK
// c_chair_id = c_sotrudnik_chairs_ids[0];

const CUR_YEAR = new Date().getFullYear();


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

function clearDataTablesStorage() {
  const prefixes = [
      'DataTables_Table_nagruzka_',
      'DataTables_Table_uoup_nagruzka_',
      'DataTables_Table_ksro_',
      'DataTables_Table_uoup_chairs_refused_',
      'DataTables_Table_nagruzka_to_change_'
  ];
  
  // Собираем ключи для удаления
  const keysToRemove = [];
  for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (key && prefixes.some(prefix => key.indexOf(prefix) === 0)) {
          keysToRemove.push(key);
      }
  }
  
  // Удаляем найденные ключи
  keysToRemove.forEach(key => {
      localStorage.removeItem(key);
      console.log('Cleared:', key);
  });
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

  const select = $('<select class="search_init select_filter form-select"><option value=""></option></select>')
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

function createCustomFilters(table_id, table, columns, scope) 
{
  CL('createCustomFilters');
  
  // Очищаем старые фильтры перед созданием новых
  $('#' + table_id + ' tfoot th').each(function() {
    $(this).find('select, input').remove();
  });

  // Получаем сохраненное состояние таблицы
  const state = table.state.loaded();
  
  // If scope is not provided, use a dummy object to prevent errors
  const $scope = scope || { $apply: (fn) => fn() };
  
  // Проверяем, существует ли footer
  const footerExists = $('#' + table_id + ' tfoot').length > 0;
  // CL('Footer exists:', footerExists);
  
  if (!footerExists) {
    CL('ERROR: Table footer not found!');
    return;
  }
  
  table.columns(':visible').every(function(columnIndex) 
  {
    // CL(columnIndex);
    const column = this;
    const footerCell = $('#' + table_id + ' tfoot th[ind="' + columnIndex + '"]');
    const colSettings = columns[columnIndex];
    
    // Отладочная информация
    // console.log('Column index:', columnIndex, 'Type:', typeof columnIndex, 'Footer cell found:', footerCell.length > 0, 'Column settings:', colSettings);
    // CL(columnIndex);
    // console.log('Visible columns count:', table.columns(':visible').count());
    // console.log('Total columns count:', table.columns().count());
    
    if (footerCell.length === 0) {
      // console.log('ERROR: Footer cell not found for column index:', columnIndex);
      return;
    }

    // Получаем сохраненное значение фильтра для колонки
    let savedSearch = '';

    if (state && state.columns && state.columns[columnIndex] && state.columns[columnIndex].search) {
      savedSearch = state.columns[columnIndex].search.search || '';
    }
    
    // console.log('Saved search for column', columnIndex, ':', savedSearch);

    if (colSettings && colSettings.type === 'select')
    {
      // CL(footerCell);

      const select = $('<select class="form-select select_filter"></select>')
        .appendTo(footerCell)
        .on('change', function() {
          // Clear all checkboxes when filter changes
          if ($scope.nagruzka && $scope.nagruzka.length > 0) {
            $scope.$apply(function() {
              $scope.nagruzka.forEach(function(row) {
                row.selected = false;
              });
            });
          }
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

      // CL('Select filter created for column', columnIndex, 'with options:', select.find('option').length);
      
      // Устанавливаем сохраненное значение
      if (savedSearch) {
        select.val(savedSearch);
        // CL('Applied saved value:', savedSearch, 'to column', columnIndex);
      }
    } 
    else if (colSettings && colSettings.type === 'input')
    {
      const input = $('<input class="search_init text_filter form-control" type="text" />')
        .appendTo(footerCell)
        .val(savedSearch)  // Устанавливаем сохраненное значение
        .on('keyup change', function() {
          if(column.search() !== this.value) {
            // Clear all checkboxes when filter changes
            if ($scope.nagruzka && $scope.nagruzka.length > 0) {
              $scope.$apply(function() {
                $scope.nagruzka.forEach(function(row) {
                  row.selected = false;
                });
              });
            }
            column.search(this.value).draw();
          }
        });
      
      // CL('Input filter created for column', columnIndex, 'with saved value:', savedSearch);
    }
    else 
    {
      // CL('No filter type defined for column', columnIndex, '- skipping');
    }
  });
  
  // CL('createCustomFilters completed');
}

function UpdateNagruzkaStat($http, scope, nagr_type, chair_id, lecturer_uid, only_stat)
{
  // CL('UpdateNagruzkaStat');
  // CL(nagr_type);
  // CL(chair_id);
  // CL(lecturer_uid);
  // CL(only_stat);

  var script;

  if (nagr_type == 'ksro')
  {
    if (scope.system_mode == 'mode_filling')
    {
      script = 'ksro.php';
    }
    else
    {
      script = 'nagruzka/';
    }
  }
  else
  {
    script = 'nagruzka/';
  }

  // nagruzka.php
  // ksro.php
  var url = `ajax/get/${script}?chair_id=${chair_id}&type=${nagr_type}`;

  if (lecturer_uid)
  {
    url += '&lecturer_uid=' + encodeURIComponent(lecturer_uid);
  }

  if (only_stat)
  {
    url += `&only_stat=1`;
  }

  // if (nagr_type == 'discipline')
  {
    $http({url: url, method: 'GET'})
    .then(function (response) 
    {
      if (response.data)
      {
        if (isEmpty(scope.nagruzka_stat[chair_id])) scope.nagruzka_stat[chair_id] = {};
        // if (isEmpty(scope.nagruzka[chair_id])) scope.nagruzka[chair_id] = {};

        if (!only_stat)
        {
          if (nagr_type == 'ksro' && scope.system_mode == 'mode_filling')
          {
            scope.ksro = response.data.nagruzka;
          }
          else
          {
            scope.nagruzka = response.data.nagruzka;
          }
        }
        scope.nagruzka_stat[chair_id][nagr_type] = response.data.stat;

        // CL(response.data);
        // CL(scope.nagruzka_stat);
        scope.isLoading = false;

        // Если ограничены одним преподом, то нужно взять его ФИО (из первой же нагрузки)
        if (scope.nagruzka_selected_lecturer_uid /*|| scope.ksro_selected_lecturer_uid */)
        {
          scope._lecturer_fio = response.data.lecturer_fio;

          // if (nagr_type == 'ksro' && !isEmpty(response.data.nagruzka))
          // {
          //   scope._lecturer_fio = response.data.nagruzka[0].lecturer_fio;
          // }
          // else
          // {
          //   // const lector = findObjByColumn(response.data.nagruzka[0].lectors, 'lecturer_uid', scope._lecturer_uid);
          //   scope._lecturer_fio = response.data.lecturer_fio;
          // }
          
        }
      }
    })
  }
}

function GetNagruzkaTypesRowLink(scope, nagruzka_type, chair_id, lecturer_uid)
{
  // CL('GetNagruzkaTypesRowLink');

  var link = '';

  if (nagruzka_type == 'ksro')
  {
    link = '#/ksro';
  }
  else
  {
    link = '#/nagruzka/' + nagruzka_type;
  }

  // УОУП
  // scope.nagruzka_selected_chair_id
  if (c_roles['uoup'] && !isEmpty(chair_id)) link += '/' + chair_id;
  if (c_roles['uoup'] && !isEmpty(lecturer_uid)) link += '/' + lecturer_uid;

  // ЗавКаф
  if (c_roles['zavkaf'] && !isEmpty(c_chair_id)) link += '/' + c_chair_id;
  if (c_roles['zavkaf'] && !isEmpty(scope.nagruzka_selected_lecturer_uid)) link += '/' + scope.nagruzka_selected_lecturer_uid;

  // для сотрудника из параметра
  if (c_roles.sotrudnik)
  {
     link += `/${chair_id}/${lecturer_uid}`;
  }

  return link;
}



//****************************************
// https://amsul.ca/pickadate.js/
angular.module('app', ['ngRoute', 'ngDialog', 'angucomplete-alt', 'ngAnimate', 'angularSpinners', 'ui.mask', 'angularFileUpload', 'ngCookies', 'pickadate', 'datatables', 'datatables.columnfilter', 'datatables.colvis', 'datatables.buttons', 'ngResource', 'ngSanitize', 'ngCookies'])
//    'ui.bootstrap.modal', 'ui.bootstrap', ,  'mgcrea.ngStrap',

.constant('system_start_year', <?=$_system_start_year?$_system_start_year:2017?>)


// http://oncodesign.io/2014/02/19/safely-prevent-template-caching-in-angularjs/
.run(function($rootScope, $templateCache) {
   $rootScope.$on('$routeChangeStart', function(event, next, current) {
          if (typeof(current) !== 'undefined'){
              $templateCache.remove(current.templateUrl);
          }
      });

    $(document).on('init.dt', function() {
      // Скрываем сразу
      $('.dt-loading').hide();
      
      // И еще раз через секунду на всякий случай
      setTimeout(function() {
          $('.dt-loading').hide();
      }, 1000);
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
        chairs_sprav : function($http)
        {
          return $http({url: 'ajax/get/get_chairs.php', method: 'GET'});
        },
        nagruzka_type: function($route)
        {
          return null;
        },
        nagruzka_selected_chair_id: function($route)
        {
          return null
        },
        system_mode: function($http) {
            return $http({ url: 'ajax/get/get_system_mode.php', method: 'GET' })
                .then(function(response) {
                    // Возвращаем из resolve ТОЛЬКО сам режим (строку или объект)
                    return response.data.mode; 
                });
        },
        nagruzka_stat: function($http)
        {
          return null; //$http({url: 'ajax/get/get_nagruzka_zavkaf_stat.php?chair_id=' + c_chair_id, method: 'GET'});
        },
        nagruzka: function($http)
        {
          return null;//$http({url: 'ajax/get/nagruzka_discipline.php?chair_id=' + c_chair_id, method: 'GET'});
        },
        lecturer_uid: function($route)
        {
          return null;
        }

      }
    })
    // фильтрация по преподавателю
    /*
    .when('/nagruzka/discipline/:lecturer_uid',
    {
      templateUrl: 'nagruzka.tpl.html?' + getRandom(10000, 99999),
      controller: 'NagruzkaCtrl',
      resolve:
      {
        nagruzka_type: function()
        {
          return 'discipline';
        },
        lecturer_uid: function($route)
        {
          return $route.current.params.lecturer_uid;
        },
        system_mode: function($http) 
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'}); 
        },
        nagruzka_stat: function($http)
        {
          return $http({url: 'ajax/get/get_nagruzka_zavkaf_stat.php?chair_id=' + c_chair_id, method: 'GET'});
        },
        nagruzka: function($http, $route)
        {
          const lecturer_uid = $route.current.params.lecturer_uid;
          return $http({
            url: 'ajax/get/nagruzka_discipline.php?chair_id=' + c_chair_id + '&lecturer_uid=' + encodeURIComponent(lecturer_uid), 
            method: 'GET'
          });
        }
      }
    })
      */
    // Интерфейс завкафа
    .when('/nagruzka/:type',
    {
      templateUrl: 'nagruzka.tpl.html?' + getRandom(10000, 99999),
      controller: 'NagruzkaCtrl',
      resolve:
      {
        chairs_sprav : function($http)
        {
          return $http({url: 'ajax/get/get_chairs.php', method: 'GET'});
        },
        nagruzka_type: function($route)
        {
          return $route.current.params.type
        },
        nagruzka_selected_chair_id: function($route)
        {
          return null
        },
        system_mode: function($http) {
            return $http({ url: 'ajax/get/get_system_mode.php', method: 'GET' })
                .then(function(response) {
                    // Возвращаем из resolve ТОЛЬКО сам режим (строку или объект)
                    return response.data.mode; 
                });
        },
        nagruzka_stat: function($http)
        {
          return null; // $http({url: 'ajax/get/get_nagruzka_zavkaf_stat.php?chair_id=' + c_chair_id, method: 'GET'});
        },
        nagruzka: function($route, $http)
        {
          const nagruzka_type = $route.current.params.type;

          // if (nagruzka_type == 'discipline')
          {
            return null; // $http({url: 'ajax/get/nagruzka.php?chair_id=' + c_chair_id, method: 'GET'});
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
        },
        lecturer_uid: function($route)
        {
          return null;
        }
        /*
        if (!isEmpty(nagruzka_selected_chair_id)) chair_str = "?chair_id=" + nagruzka_selected_chair_id;
        else chair_str = "";

        $scope.nagruzka = $resource('ajax/get/nagruzka_discipline.php' + chair_str).query(function()
        */
      }
    })
    // Интерфейс Завкафа - нагрузка
    // Интерфейс УОУП, как у завкафа, но readonly (для УОУП нет в меню)
    .when('/nagruzka/:type/:nagruzka_selected_chair_id',
    {
      templateUrl: 'nagruzka.tpl.html?' + getRandom(10000, 99999),
      controller: 'NagruzkaCtrl',
      resolve:
      {
        chairs_sprav: function($http)
        {
          return $http({url: 'ajax/get/get_chairs.php', method: 'GET'});
        },
        nagruzka_type: function($route)
        {
          return $route.current.params.type
        },
        // Параметр только для УОУП для выбора кафедры
        nagruzka_selected_chair_id: function($route)
        {
          return $route.current.params.nagruzka_selected_chair_id
        },
        system_mode: function($http) {
            return $http({ url: 'ajax/get/get_system_mode.php', method: 'GET' })
                .then(function(response) {
                    // Возвращаем из resolve ТОЛЬКО сам режим (строку или объект)
                    return response.data.mode; 
                });
        },
        nagruzka_stat: function($http, $route)
        {
          return null; // $http({url: 'ajax/get/get_nagruzka_zavkaf_stat.php?chair_id=' + ($route.current.params.nagruzka_selected_chair_id ? $route.current.params.nagruzka_selected_chair_id : c_chair_id), method: 'GET'});
        },
        nagruzka: function($http, $route)
        {
          const nagruzka_type = $route.current.params.type;
          const chair_id = $route.current.params.nagruzka_selected_chair_id;

          // if (nagruzka_type == 'discipline')
          {
            return null; // $http({url: 'ajax/get/nagruzka.php?chair_id=' + (chair_id ? chair_id : c_chair_id), method: 'GET'});
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
        },
        lecturer_uid: function($route)
        {
          return null;
        }
      }
    })
    // Интерфейс Завкафа - нагрузка по преподавателю (переход из таблицы пункта меню Сотрудники)
    .when('/nagruzka/:type/:nagruzka_selected_chair_id/:lecturer_uid',
    {
      templateUrl: 'nagruzka.tpl.html?' + getRandom(10000, 99999),
      controller: 'NagruzkaCtrl',
      resolve:
      {
        chairs_sprav: function($http)
        {
          return $http({url: 'ajax/get/get_chairs.php', method: 'GET'});
        },
        nagruzka_type: function($route)
        {
          return $route.current.params.type
        },
        // Параметр только для УОУП для выбора кафедры
        nagruzka_selected_chair_id: function($route)
        {
          return $route.current.params.nagruzka_selected_chair_id
        },
        system_mode: function($http) {
            return $http({ url: 'ajax/get/get_system_mode.php', method: 'GET' })
                .then(function(response) {
                    // Возвращаем из resolve ТОЛЬКО сам режим (строку или объект)
                    return response.data.mode; 
                });
        },
        nagruzka_stat: function($http, $route)
        {
          return null; // $http({url: 'ajax/get/get_nagruzka_zavkaf_stat.php?chair_id=' + ($route.current.params.nagruzka_selected_chair_id ? $route.current.params.nagruzka_selected_chair_id : c_chair_id), method: 'GET'});
        },
        nagruzka: function($http, $route)
        {
          const nagruzka_type = $route.current.params.type;
          const chair_id = $route.current.params.nagruzka_selected_chair_id;
          const lecturer_uid = $route.current.params.lecturer_uid;

          // if (nagruzka_type == 'discipline')
          {
            let url = 'ajax/get/nagruzka/?chair_id=' + (chair_id ? chair_id : c_chair_id);
            if (lecturer_uid) 
            {
              url += '&lecturer_uid=' + encodeURIComponent(lecturer_uid);
            }
            return null; // $http({url: url, method: 'GET'});
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
        },
        lecturer_uid: function($route)
        {
          return $route.current.params.lecturer_uid;
        }
      }
    })
    .when('/uoup_nagruzka',
    {
      templateUrl: 'uoup_nagruzka.tpl.html?' + getRandom(10000, 99999),
      controller: 'UOUPNagruzkaCtrl',
      resolve:
      {
        chairs_sprav : function($http)
        {
          return $http({url: 'ajax/get/get_chairs.php', method: 'GET'});
        },
        uoup_nagruzka_selected_chair_id: function($route)
        {
          return null
        },
        uoup_nagruzka: function($http)
        {
        //   return $http({url: 'ajax/get/uoup_nagruzka.php', method: 'GET'});
              return null;
        },
        nagruzka_uoup_stat: function($http)
        {
          return $http({url: 'ajax/get/get_nagruzka_uoup_stat.php', method: 'GET'});
        },
        page: function($q) {
          return $q.when('uoup_nagruzka');
        },
        system_mode: function($http) 
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'}); 
        },
      }
    })
    .when('/uoup_nagruzka_no_chair',
    {
      templateUrl: 'uoup_nagruzka_no_chair.tpl.html?' + getRandom(10000, 99999),
      controller: 'UOUPNagruzkaCtrl',
      resolve:
      {
        chairs_sprav : function($http)
        {
          return $http({url: 'ajax/get/get_chairs.php', method: 'GET'});
        },
        uoup_nagruzka_selected_chair_id: function($route)
        {
          return null
        },
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
        },
        system_mode: function($http) 
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'}); 
        },
      }
    })
    .when('/uoup_nagruzka_no_type',
    {
      templateUrl: 'uoup_nagruzka_no_type.tpl.html?' + getRandom(10000, 99999),
      controller: 'UOUPNagruzkaCtrl',
      resolve:
      {
        chairs_sprav : function($http)
        {
          return $http({url: 'ajax/get/get_chairs.php', method: 'GET'});
        },
        nagruzka_selected_chair_id: function($route)
        {
          return null
        },
        uoup_nagruzka: function($http)
        {
          return $http({url: 'ajax/get/uoup_nagruzka_no_type.php', method: 'GET'});
        },
        nagruzka_uoup_stat: function($http)
        {
          return {};
        },
        page: function($q) {
          return $q.when('uoup_nagruzka_no_type');
        },
        system_mode: function($http) 
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'}); 
        },
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
    .when('/export_to_galaktika',
    {
      templateUrl: 'export_to_galaktika.tpl.html?' + getRandom(10000, 99999),
      controller: 'ExportToGalaktikaCtrl',
      resolve:
      {
        page: function($q) {
          return $q.when('export_to_galaktika');
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
        },
        sotrudniki_selected_chair_id: function($route)
        {
          return null;
        },
      }
    })
    // УОУП просматривает сотрудников кафедры
    .when('/sotrudniki/:chair_id',
    {
      templateUrl: 'sotrudniki.tpl.html?' + getRandom(10000, 99999),
      controller: 'SotrudnikiCtrl',
      resolve:
      {
        system_mode: function($http) 
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'}); 
        },
        sotrudniki_selected_chair_id: function($route)
        {
          return $route.current.params.chair_id
        },
      }
    })
    .when('/nagruzka_columns',
    {
      templateUrl: 'nagruzka_columns.tpl.html?' + getRandom(10000, 99999),
      controller: 'NagruzkaColumnsCtrl',
      resolve:
      {
        column_order: function($http)
        {
          return $http({url: 'ajax/get/get_nagruzka_column_order.php', method: 'GET'});
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
    // .when('/ksro',
    // {
    //   templateUrl: 'ksro.tpl.html?' + getRandom(10000, 99999),
    //   controller: 'KSROCtrl',
    //   resolve:
    //   {
    //     system_mode: function($http) 
    //     {
    //       return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'}); 
    //     }
    //   }
    // })
    .when('/ksro',
    {
      templateUrl: 'ksro.tpl.html?' + getRandom(10000, 99999),
      controller: 'KSROCtrl',
      resolve:
      {
        system_mode: function($http) 
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'}); 
        },
        // Параметр только для УОУП для выбора кафедры
        ksro_selected_chair_id: function($route)
        {
          return null
        },
        ksro_selected_lecturer_uid: function($route)
        {
          return null;
        },
        chairs_sprav: function($http)
        {
          return $http({url: 'ajax/get/get_chairs.php', method: 'GET'});
        }
      }
    })
    .when('/ksro/:ksro_selected_chair_id',
    {
      templateUrl: 'ksro.tpl.html?' + getRandom(10000, 99999),
      controller: 'KSROCtrl',
      resolve:
      {
        system_mode: function($http) 
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'}); 
        },
        // Параметр только для УОУП для выбора кафедры
        ksro_selected_chair_id: function($route)
        {
          return $route.current.params.ksro_selected_chair_id
        },
        ksro_selected_lecturer_uid: function($route)
        {
          return null;
        },
        chairs_sprav: function($http)
        {
          return $http({url: 'ajax/get/get_chairs.php', method: 'GET'});
        }
      }
    })
    .when('/ksro/:ksro_selected_chair_id/:ksro_selected_lecturer_uid',
    {
      templateUrl: 'ksro.tpl.html?' + getRandom(10000, 99999),
      controller: 'KSROCtrl',
      resolve:
      {
        system_mode: function($http) 
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'}); 
        },
        // Параметр только для УОУП для выбора кафедры
        ksro_selected_chair_id: function($route)
        {
          return $route.current.params.ksro_selected_chair_id
        },
        ksro_selected_lecturer_uid: function($route)
        {
          return $route.current.params.ksro_selected_lecturer_uid;
        },
        chairs_sprav: function($http)
        {
          return $http({url: 'ajax/get/get_chairs.php', method: 'GET'});
        }
      }
    })
    .when('/aspirantura',
    {
      templateUrl: 'aspirantura.tpl.html?' + getRandom(10000, 99999),
      controller: 'AspiranturaCtrl',
      resolve:
      {
        system_mode: function($http) 
        {
          return $http({url: 'ajax/get/get_system_mode.php', method: 'GET'}); 
        },
        // Параметр только для УОУП для выбора кафедры
        aspirantura_selected_chair_id: function($route)
        {
          return null
        },
        aspirantura_selected_lecturer_uid: function($route)
        {
          return null;
        },
        chairs_sprav: function($http)
        {
          return $http({url: 'ajax/get/get_chairs.php', method: 'GET'});
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
  $rootScope.checkSessionInterval = setInterval(checkSession, 120000, $http, $scope, ngDialog);


  $rootScope.ClearGreenTableFilters = function(dtInstance, filter_distinct, lecturer_uid, nagruzka_type, nagruzka_selected_chair_id) 
  {
    CL('ClearGreenTableFilters');
    const table = dtInstance.DataTable; // Changed from dataTable to DataTable

    // Check if we're on a page with a lecturer_uid
    // if (lecturer_uid) 
    // {
    //   // Build the target URL
    //   const basePath = `#/nagruzka/${nagruzka_type}`;
    //   const chairParam = nagruzka_selected_chair_id ? `/${nagruzka_selected_chair_id}` : '';
    //   const targetUrl = basePath + chairParam;
      
    //   // Clear all filters
    //   if (table) 
    //   {
    //     table.search('').columns().search('').draw();
    //   }
      
    //   // Force a full page reload with the new URL
    //   window.location.href = window.location.origin + window.location.pathname + targetUrl;
    //   return;
    // }

  

    // Original filter clearing logic
    if (filter_distinct) 
    {
      filter_distinct.global_nagruzka_filter = undefined;
    }
    
    if (table) 
    {

      // Очищаем внутренние фильтры DataTables
      table.search('').draw();
      
      table.columns().every(function() {
          this.search(''); // Очистка поиска для каждой колонки
      });
      
      table.draw();
      
      // ДОБАВЛЯЕМ: Сброс DOM-элементов фильтров
      // Получаем jQuery объект всей таблицы
      var $table = $(table.table().node());
      
      // Сбрасываем все селекты с классом select_filter
      $table.find('select.select_filter').val('');
      
      // Очищаем все текстовые поля с классом text_filter
      $table.find('input.text_filter').val('');
      
      // Если на селекты повешены обработчики change - вызываем их
      // $table.find('select.select_filter').trigger('change');

    }
  };

  $rootScope.NagruzkaRowClick = function(nagruzka_row)
  {
    CL('NagruzkaRowClick');
    CL(nagruzka_row);

    const dialogScope = $scope.$new();
    dialogScope.nagruzka_row = nagruzka_row;

    dialogScope.nagruzka_history = $resource('ajax/get/get_nagruzka_history.php?load_base_UID2=' + nagruzka_row.base_uid2).query();

    // Удалить комментарий у нагрузки (завкаф)
    dialogScope.DeleteComment = function(log_id)
    {
      $http({url: 'ajax/post/delete_comment.php', method: 'POST', data: {id: log_id}})
        .then(function(data)
        {
          if (data.data.result == 'success')
          {
            deleteByColumn(dialogScope.nagruzka_history, 'id', log_id);
            toastr.success("Комментарий удалён");
          }
          else
          {
            toastr.error("Ошибка");
          }
        });
    }

    ngDialog.open({
                    template: "nagruzka_history.tpl.html" + "?" + getRandom(10000, 99999),
                    scope: dialogScope,
                    plain: false,
                    disableAnimation: true,
                    className: 'ngdialog-theme-default history'
                  });
  }

  $scope.logout = function()
  {
    CL('logout');

    // Очищаем localStorage для всех DataTables
    clearDataTablesStorage();
    
    // Затем делаем редирект на logout
    window.location.href = '/?logout';
  }

})

.controller ('SystemClosedCtrl', function($rootScope, $scope, page)
{
  CL('SystemClosedCtrl');
  $rootScope.page = page;
})

.controller ('ExportToGalaktikaCtrl', function($rootScope, $scope, page)
{
  CL('ExportToGalaktiakCtrl');
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

  if (c_roles.zavkaf || c_roles.sotrudnik)
  {
    window.location = '#/nagruzka';
  }

})


.controller ('NagruzkaCtrl', function($rootScope, $scope, $http, DTOptionsBuilder, DTColumnBuilder, DTColumnDefBuilder, ngDialog, $templateCache, nagruzka_type, nagruzka_selected_chair_id, lecturer_uid, $resource, $cookies, system_mode, nagruzka_stat, nagruzka, chairs_sprav, $location, $timeout) 
{
  CL('NagruzkaCtrl');
  // CL(nagruzka_type);
  // CL(nagruzka_selected_chair_id);

  $rootScope.page = 'nagruzka';
  $scope.system_mode = system_mode; 
  $scope.isLoading = true;

  if ($scope.system_mode === 'export_to_galaktika') 
  {
    return;
  }

  if (nagruzka_type == 'ksro' && $scope.system_mode == 'mode_filling')
  {
    var path = "/#/ksro";

    if (!isEmpty(nagruzka_selected_chair_id)) path += `/${nagruzka_selected_chair_id}`;
    if (!isEmpty(lecturer_uid)) path += `/${lecturer_uid}`;

    window.location = path;
  }

  if (nagruzka_type == 'aspirantura' && $scope.system_mode == 'mode_filling')
  {
    var path = "/#/aspirantura";

    if (!isEmpty(nagruzka_selected_chair_id)) path += `/${nagruzka_selected_chair_id}`;
    if (!isEmpty(lecturer_uid)) path += `/${lecturer_uid}`;

    window.location = path;
  }
  
  $scope.$root = $rootScope;
  $scope.chairs_sprav = chairs_sprav.data;
  $scope.nagruzka_selected_chair_id = nagruzka_selected_chair_id;
  $scope.nagruzka_selected_lecturer_uid = lecturer_uid; // Store the lecturer_uid from the route
  // CL(lecturer_uid);
  
  

  $scope.$_forms_obuchenia = $_forms_obuchenia;
  $scope.$_nagruzka_types = $_nagruzka_types;
  $scope._nagruzka_type = nagruzka_type;
  // $scope.nagruzka_stat = nagruzka_stat.data;
  // Строка для проверки, что тесты работают. Должна быть ошибка.
  // $scope.nagruzka = nagruzka.data;
  // сейчас это все не подргружается
  $scope.nagruzka = {};
  $scope.nagruzka_stat = {};

  // TODO to fix lecturer_fio
  // if (!isEmpty($scope.nagruzka))
  // { 
  //   $scope.lecturer_fio = $scope.nagruzka[0]['lectors'][0].lecturer_fio;
  // }

  if (c_roles.sotrudnik)
  {
    $scope._chairs_ids = c_sotrudnik_chairs_ids;
    $scope._lecturer_uids = c_sotrudnik_lecturer_uids;
    $scope._chairs_titles = c_sotrudnik_chairs_titles;
  }

  if (c_roles.zavkaf)
  {
    $scope._chairs_ids = [c_chair_id];

    if (!isEmpty($scope.nagruzka_selected_chair_id) && !$scope._chairs_ids.includes($scope.nagruzka_selected_chair_id))
    {
      // CL('HERE');
      // CL($scope._nagruzka_type);
      window.location = `/#/nagruzka/${$scope._nagruzka_type}/${$scope._chairs_ids[0]}`;
    }
  }
  else if (c_roles.uoup)
  {
    $scope._chairs_ids = [$scope.nagruzka_selected_chair_id];
    $scope._lecturer_uids = [$scope.nagruzka_selected_lecturer_uid];
  }

  // CL($scope.system_mode); 
  CL($scope._chairs_ids);

  if (c_roles.zavkaf && $scope.system_mode === 'mode_closed') 
  {
    window.location = '#/system_closed';
  }

  // $scope.nagruzka_readonly = c_roles.zavkaf && (!isEmpty(nagruzka_selected_chair_id) || $scope.system_mode === 'mode_verification') ||  $scope.system_mode === 'mode_archive'; 
  // CL(c_roles);

  // $scope.nagruzka_readonly = c_roles.sotrudnik || c_roles.uoup || ($scope.system_mode != 'mode_filling' && $scope.system_mode != 'mode_verification'); 


  // $scope.system_mode ===  'mode_verification' || $scope.system_mode === 'mode_archive'; 

  // CL($scope.nagruzka_readonly);

  $templateCache.put('confirm_delete', '<p>Вы уверены, что хотите удалить?</p>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Нет</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(1)">Да</button>\
              </div>');

  $templateCache.put('confirm_require_admin_change', '<p>Нагрузка распределена. Если нагрузка будет изменена, то текущее распределение будет удалено. Продолжить?</p>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Нет</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(1)">Да</button>\
              </div>');

  
  // $scope.$_degrees_codes = $_degrees_codes;

  $scope.dtInstance = {};
  // Используется только для селекта "Вся нагрузка..."
  $scope.filter_distinct = {};
  $scope.group_action = {action: 'assign_to_several_sotrudniki'};

  $scope.filter_distinct.global_nagruzka_filter = $cookies.get('global_nagruzka_filter');


  $scope.NagruzkaCtrlUpdateNagruzkaStat = function(nagr_type, chair_id, lecturer_uid, only_stat)
  {
    UpdateNagruzkaStat($http, $scope, nagr_type, chair_id, lecturer_uid, only_stat);
  }

  

  // function LoadNagruzkaZavkafStat()
  // {
  //   $http({url: 'ajax/get/get_nagruzka_zavkaf_stat.php?chair_id=' + (nagruzka_selected_chair_id ? nagruzka_selected_chair_id : c_chair_id), method: 'GET'}).then(function(response)
  //   {
  //     $scope.nagruzka_stat = response.data;
  //   });
  // }


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

  /*
  function extractLecturersText(data, row) 
  {
      if (!row || !row.lectors || row.lectors.length === 0) return '';
      
      const lecturers = [];
      
      angular.forEach(row.lectors, function(lector) {
          if (!lector.delete) {
              let lecturerText = '';
              
              // Получаем ФИО
              if (lector.lecturer_fio && lector.lecturer_fio.length) {
                  lecturerText = lector.lecturer_fio;
              } else {
                  lecturerText = '[не распределено]';
              }
              
              // Добавляем часы если несколько лекторов
              if (row.lectors.length > 1 && lector.Amount) {
                  lecturerText += ' (' + Number(lector.Amount).toFixed(2) + ')';
              }
              
              lecturers.push(lecturerText);
          }
      });
      
      return lecturers.join('; ');
  }

  */

  // function NagruzkaInit()
  {
    // CL('NagruzkaInit');
    // $scope.persons = $resource('data.json').query();

    $scope.dtOptions = DTOptionsBuilder //.fromSource('data.json')
      .newOptions()
      .withOption('stateSave', true)
      .withOption('stateStorage', 'cookie')
      .withOption('stateSaveCallback', function(settings, data) {
          const path = $location.path();
          const storageKey = 'DataTables_Table_nagruzka_' + path.replace(/\//g, '_');
          localStorage.setItem(storageKey, JSON.stringify(data));
      })
      .withOption('stateLoadCallback', function(settings) {
          const path = $location.path();
          const storageKey = 'DataTables_Table_nagruzka_' + path.replace(/\//g, '_');
          const saved = localStorage.getItem(storageKey);
          return saved ? JSON.parse(saved) : null;
      })
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
      .withButtons([
          {
            extend: 'excel',
            text: 'Excel', // Текст на самой кнопке
            filename: "Нагрузка", // Имя файла
            title: "Нагрузка", // Заголовок на первой строке листа
            exportOptions: 
            {
              columns: function (idx, data, node) {
                  // Проверяем, что столбец видимый и не первый
                  const column = $scope.dtInstance.dataTable.fnSettings().aoColumns[idx];
                  return column.bVisible && idx !== 0;
              },
              format: 
              {
                body: function (data, column, row, node) 
                {
                  // console.log('Arguments:', arguments);

                  // Для определённых столбцов своя обработка
                  if (row === 14)
                  {
                    // Клонируем узел, чтобы не менять оригинал
                    const clone = node.cloneNode(true);
                    
                    // Удаляем все комментарии из клона
                    const walker = document.createTreeWalker(
                        clone,
                        NodeFilter.SHOW_COMMENT,
                        {
                            acceptNode: function(node) {
                                return NodeFilter.FILTER_ACCEPT;
                            }
                        }
                    );
                    
                    const commentsToRemove = [];
                    while (walker.nextNode()) {
                        commentsToRemove.push(walker.currentNode);
                    }
                    commentsToRemove.forEach(comment => comment.remove());
                    
                    // Получаем текст без комментариев
                    let text = clone.textContent || clone.innerText || '';
                    
                    // Очищаем от лишних пробелов и переносов строк
                    text = text.replace(/\s+/g, ' ').trim();
                    
                    // Разделяем лекторов (если нужно)
                    text = text.replace(/\)\s+/, '); ');

                    // Заменяем "[не распределено]" на "не распределено"
                    text = text.replace(/\[не распределено\]/g, 'не распределено');
                    
                    return text;

                  }
                  else
                  { 
                    if (!data || typeof data !== 'string') return data || '';

                    // Создаем временный элемент
                    const temp = document.createElement('div');
                    temp.innerHTML = data;

                    // Заменяем <br> на перенос строки перед получением textContent
                    // Вариант 1: через replace
                    const htmlWithBr = temp.innerHTML;
                    temp.innerHTML = htmlWithBr.replace(/<br\s*\/?>/gi, ', ');

                    return temp.textContent || temp.innerText || '';
                  }
                  

                  // Проверяем тип данных
                  // if (data === null || data === undefined) {
                  //     return '';
                  // }
                  
                  // // Если это строка и содержит HTML
                  // if (typeof data === 'string' && (data.indexOf('<') !== -1 || data.indexOf('&') !== -1)) {
                  //     // Создаем временный элемент для удаления HTML
                  //     const temp = document.createElement('div');
                  //     temp.innerHTML = data;
                  //     return temp.textContent || temp.innerText || '';
                  // }
                  
                  // // Если обычная строка или число
                  // return data;
                }
              }
            }
          }
      ])
      // .withColumnFilter({
      //     aoColumns: columns
      // })

      .withOption('initComplete', function(settings, json) {
        // Скрываем индикатор когда загрузка завершена
        CL("initComplete");
        $scope.$apply(function() {
            // $scope.isLoading = false;
        });

        // Добавляем создание фильтров
        const api = this.api();
        createCustomFilters('DataTables_Table_nagruzka', api, columns, $scope);
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
        createCustomFilters('DataTables_Table_nagruzka', table, columns, $scope);

        // Сброс и пересоздание фильтров при изменении видимости столбцов
        table.on('column-visibility.dt', function() {
          createCustomFilters('DataTables_Table_nagruzka', table, columns, $scope);
        });
      }
    }, true);
    */

    $scope.onNagruzkaTableInstance = function(dtInstance) 
    {
      CL('onNagruzkaTableInstance');
      $scope.isLoading = true;


      // для пути вида /#/nagruzka (без вида нагрузки) статистику подгрузим
      // if (isEmpty($scope.nagruzka_stat))
      if (!isEmpty($scope._chairs_ids))
      {
        angular.forEach($scope._chairs_ids, function(chair_id, ind)
        {
          // CL('HERE');

          var lecturer_uid;

          // в URL`е выбран конкретный lecturer_uid, пропустим остальные
          if (c_roles.sotrudnik && !isEmpty($scope.nagruzka_selected_lecturer_uid) && $scope.nagruzka_selected_lecturer_uid != c_sotrudnik_lecturer_uids[ind])
          {
            return;
          }

          if (c_roles.sotrudnik) lecturer_uid = c_sotrudnik_lecturer_uids[ind];
          else if ((c_roles.zavkaf || c_roles.ruk_aspirantura || c_roles.uoup) && $scope.nagruzka_selected_lecturer_uid) lecturer_uid = $scope.nagruzka_selected_lecturer_uid;

          var only_stat;

          if ($scope._nagruzka_type == 'discipline') only_stat = false; else only_stat = true;
          $scope.NagruzkaCtrlUpdateNagruzkaStat('discipline', chair_id, lecturer_uid, only_stat);

          if ($scope._nagruzka_type == 'ruk_vkr') only_stat = false; else only_stat = true;
          $scope.NagruzkaCtrlUpdateNagruzkaStat('ruk_vkr', chair_id, lecturer_uid, only_stat);

          if ($scope._nagruzka_type == 'ruk_kurs') only_stat = false; else only_stat = true;
          $scope.NagruzkaCtrlUpdateNagruzkaStat('ruk_kurs', chair_id, lecturer_uid, only_stat);

          if ($scope._nagruzka_type == 'ruk_practice') only_stat = false; else only_stat = true;
          $scope.NagruzkaCtrlUpdateNagruzkaStat('ruk_practice', chair_id, lecturer_uid, only_stat);

          if ($scope._nagruzka_type == 'ksro') only_stat = false; else only_stat = true;
          $scope.NagruzkaCtrlUpdateNagruzkaStat('ksro', chair_id, lecturer_uid, only_stat);

          if ($scope._nagruzka_type == 'gia') only_stat = false; else only_stat = true;
          $scope.NagruzkaCtrlUpdateNagruzkaStat('gia', chair_id, lecturer_uid, only_stat);

          if ($scope._nagruzka_type == 'aspirantura') only_stat = false; else only_stat = true;
          $scope.NagruzkaCtrlUpdateNagruzkaStat('aspirantura_kand_exam', chair_id, lecturer_uid, only_stat);
          $scope.NagruzkaCtrlUpdateNagruzkaStat('aspirantura_itog_exam', chair_id, lecturer_uid, only_stat);
          $scope.NagruzkaCtrlUpdateNagruzkaStat('aspirantura_ruk_asp', chair_id, lecturer_uid, only_stat);
          $scope.NagruzkaCtrlUpdateNagruzkaStat('aspirantura_ruk_soiskatel', chair_id, lecturer_uid, only_stat);

          


          // if (true || !$scope._nagruzka_type || $scope._nagruzka_type == 'all')
          // {
          //   $scope.UpdateNagruzkaStat('ruk_practice', chair_id, lecturer_uid);
          //   $scope.UpdateNagruzkaStat('ksro', chair_id, lecturer_uid);
          //   $scope.UpdateNagruzkaStat('gia', chair_id, lecturer_uid);
          //   $scope.UpdateNagruzkaStat('aspirant', chair_id, lecturer_uid);
          // }
          // else
          // {
          //   $scope.UpdateNagruzkaStat($scope._nagruzka_type, chair_id, lecturer_uid);
          // }
        })
      }
      // УОУП
      else if (!isEmpty($scope.nagruzka_selected_chair_id))
      {
        $scope.NagruzkaCtrlUpdateNagruzkaStat($scope._nagruzka_type, $scope.nagruzka_selected_chair_id);
      }
      // Руководитель подразделения аспирантуры
      else if (c_roles.ruk_aspirantura)
      {
        // if ($scope._nagruzka_type == 'aspirantura_itog_exam') only_stat = false; else only_stat = true;
        $scope.NagruzkaCtrlUpdateNagruzkaStat('aspirantura_itog_exam', null, lecturer_uid, false);
      }




      $scope.dtInstance = dtInstance;
      const table = dtInstance.DataTable;
      const lecturerColumnIndex = 15; // Index of the "Преподаватель" column
      let filtersInitialized = false;

      // Function to initialize filters only once
      const initializeFiltersOnce = () => 
      {
        CL('initializeFiltersOnce');

        if (!filtersInitialized) 
        {
          createCustomFilters('DataTables_Table_nagruzka', table, columns, $scope);
          filtersInitialized = true;
        }
      };

      // Function to apply lecturer filter
      /*
      const applyLecturerFilter = () => {
        // Ensure filters are initialized
        initializeFiltersOnce();

        const lecturerColumn = table.column(lecturerColumnIndex);
        const filterInput = document.querySelector(`#DataTables_Table_nagruzka thead th:nth-child(${lecturerColumnIndex + 1}) input[type=search]`);

        if ($scope.lecturer_uid) 
        {
          // const lecturerRow = $scope.nagruzka?.find(row => 
          //   row.lecturer_uid === $scope.lecturer_uid
          // );

          // if (lecturerRow?.lecturer_fio) {
          //   // Apply the filter using DataTables API
          //   lecturerColumn.search(lecturerRow.lecturer_fio).draw('page');
            
          //   // Update the input value if it exists
          //   if (filterInput) {
          //     filterInput.value = lecturerRow.lecturer_fio;
          //     const event = new Event('input', { bubbles: true });
          //     filterInput.dispatchEvent(event);
          //   }
          // }
        } 
        else 
        {
          // Clear the filter
          lecturerColumn.search('').draw('page');
          if (filterInput) {
            filterInput.value = '';
            const event = new Event('input', { bubbles: true });
            filterInput.dispatchEvent(event);
          }
        }
      };

      */

      // TMP comment Initialize filters immediately
      // initializeFiltersOnce();

      // Apply filter after a short delay to ensure the table is ready
      // const initialApply = () => {
      //   if (document.readyState === 'complete') {
      //     setTimeout(applyLecturerFilter, 100);
      //   } else {
      //     document.addEventListener('DOMContentLoaded', () => {
      //       setTimeout(applyLecturerFilter, 100);
      //     });
      //   }
      // };

      // Start the initial application
      // initialApply();

      // Handle table redraws - use one() instead of on() to prevent multiple handlers
      // table.one('draw.dt', applyLecturerFilter);

      // Handle column visibility changes
      table.on('column-visibility.dt', () => 
      {
        // CL('column-visibility.dt');
        // Only reinitialize filters if they haven't been initialized yet
        if (!filtersInitialized) 
        {
          initializeFiltersOnce();
        }
        // applyLecturerFilter();
      });

      // Watch for lecturer_uid changes
      // $scope.$watch('lecturer_uid', (newVal, oldVal) => {
      //   if (newVal !== oldVal) {
      //     requestAnimationFrame(applyLecturerFilter);
      //   }
      // });
    };



  }

  // $scope.GetNagruzkaAmountSum = function()
  // {
  //   CL('GetNagruzkaAmountSum');
  //   CL($scope.filteredData);
  //   if ($scope.filteredData)
  //   {
      
  //     const filteredData = $scope.filteredData.map(item => parseFloat(item[14]));

  //     // const filteredData = $scope.filteredData.filter(item => typeof item.amount === 'number');

  //     // CL(filteredData);
      
  //     return roundToTwo(filteredData.reduce((sum, item) => sum + item, 0));


  //   }
  // }

  // $scope.GetNagruzkaAmountSum = function() {
  //     // Check if DataTable is initialized
  //     if (!$.fn.DataTable || !$.fn.DataTable.isDataTable('.dataTable')) {
  //         return 0;
  //     }
      
  //     const table = $('.dataTable').DataTable();
  //     if (!table) return 0;
      
  //     const filteredData = table.rows({ filter: 'applied' }).data().toArray();
  //     if (!filteredData.length) return 0;
      
  //     return roundToTwo(
  //         filteredData.reduce((sum, row) => 
  //             sum + (parseFloat(row[14]) || 0), 
  //         0)
  //     );
  // };

  $scope.GetNagruzkaAmountSum = function() 
  {
    // CL($scope.filter_distinct.global_nagruzka_filter);
    
    if (!$.fn.DataTable || !$.fn.DataTable.isDataTable('.dataTable')) {
        return 0;
    }
    
    const table = $('.dataTable').DataTable();
    if (!table) return 0;

    // Use existing approach to get filtered nagruzka objects
    const filtered_rows_indexes = $scope.GetFilteredNagruzkaRowsIndexes();
    const visibleNagruzka = filtered_rows_indexes.map(function(i) {
        return $scope.nagruzka[i];
    });

    // Log the filtered nagruzka objects
    // console.log('Filtered nagruzka objects:', visibleNagruzka);
    
    let totalSum = 0;
    
    // If lecturer_uid filter is applied, sum amounts for that lecturer only
    if ($scope.nagruzka_selected_lecturer_uid) 
    {
        // console.log('Calculating sum for lecturer_uid:', $scope.lecturer_uid);
        
        visibleNagruzka.forEach(item => {
            if (item.lectors && item.lectors.length > 0) {
                item.lectors.forEach(lector => {
                    if (lector.lecturer_uid === $scope.nagruzka_selected_lecturer_uid && !lector.delete) {
                        totalSum += parseFloat(lector.Amount) || 0;
                    }
                });
            }
        });
    } else if ($scope.filter_distinct.global_nagruzka_filter) {
        // console.log('Calculating sum for global_nagruzka_filter:', $scope.filter_distinct.global_nagruzka_filter);
        
        visibleNagruzka.forEach(item => {
            if (item.lectors && item.lectors.length > 0) {
                item.lectors.forEach(lector => 
                {
                    if (!lector.delete)
                    {
                      let matches = false;
                      if ($scope.filter_distinct.global_nagruzka_filter === 'assigned') {
                          matches = !isEmpty(lector.lecturer_fio) && lector.lecturer_fio.toLowerCase() !== 'вакансия';
                      } else if ($scope.filter_distinct.global_nagruzka_filter === 'not_assigned') {
                          matches = isEmpty(lector.lecturer_fio);
                      } else if ($scope.filter_distinct.global_nagruzka_filter === 'assigned_to_vacancy') {
                          matches = !isEmpty(lector.lecturer_fio) && lector.lecturer_fio.toLowerCase() === 'вакансия';
                      }
                      if (matches) {
                          totalSum += parseFloat(lector.Amount) || 0;
                      }
                    }
                });
            }
        });
    } else {
        // No lecturer filter or global filter, sum all amounts
        // console.log('Calculating sum for all lecturers');
        visibleNagruzka.forEach(item => {
            // totalSum += parseFloat(item.Amount) || 0;

            if (item && item.lectors && item.lectors.length > 0) {
                item.lectors.forEach(lector => {
                    if (!lector.delete)
                    totalSum += parseFloat(lector.Amount) || 0;
                });
            }
        });

        
    }
    
    // console.log('Calculated sum:', totalSum);
    
    return roundToTwo(totalSum);
  }
  
  $scope.GetNagruzkaTypesRowLink = function(nagruzka_type, chair_id, lecturer_uid)
  {
    return GetNagruzkaTypesRowLink($scope, nagruzka_type, chair_id, lecturer_uid);
  }
  
  // $scope.GetNagruzkaTypesRowLink = function(nagruzka_type, chair_id, lecturer_uid)
  // {
  //   var link = '#/nagruzka/' + nagruzka_type;

  //   // УОУП
  //   if (c_roles['uoup'] && !isEmpty($scope.nagruzka_selected_chair_id)) link += '/' + $scope.nagruzka_selected_chair_id;

  //   // ЗавКаф
  //   if (c_roles['zavkaf'] && !isEmpty(c_chair_id)) link += '/' + c_chair_id;
  //   if (c_roles['zavkaf'] && !isEmpty($scope.nagruzka_selected_lecturer_uid)) link += '/' + $scope.nagruzka_selected_lecturer_uid;

  //   // для сотрудника из параметра
  //   if (c_roles.sotrudnik)
  //   {
  //      link += `/${chair_id}/${lecturer_uid}`;
  //   }

  //   return link;
  // }

  $scope.ShowNagruzkaTypeLinkNotText = function()
  {
    return true;

    // if (isEmpty($scope._nagruzka_type) || $scope._nagruzka_type == 'all') return true;
    // else return false;
  }

  // вычислить уровень образования по коду направления
  // $scope.GetEducationLevel = function(nagruzka_row)
  // {

  // }

  $scope.onNagruzkaGlobalFilterChange = function() 
  {
    CL('onNagruzkaGlobalFilterChange');

    // Deselect all checkboxes before applying the filter
    if ($scope.nagruzka && $scope.nagruzka.length > 0) {
      $scope.nagruzka.forEach(function(row) {
        row.selected = false;
      });
    }

    $cookies.put('global_nagruzka_filter', $scope.filter_distinct.global_nagruzka_filter);

    window.location.reload();
  }

  // $scope.GetStatNagruzka = function(nagruzka_type, stat)
  // {
  //   if (nagruzka_type == 'discipline')
  //   {
  //     if (stat == 'total' && !isEmpty($scope.nagruzka)) return $scope.nagruzka.length;
  //   }


  //   return '';
  // }

  /*
  function SaveNagruzkaLecturer(lecturer_row)
  {

    // return;
    
    $http({url: 'ajax/post/select_nagruzka_lecturer.php', method: 'POST', data: { lecturer_fio: lecturer_row.lecturer_fio, lecturer_uid: lecturer_row.lecturer_uid, lecturer_person_id: lecturer_row.lecturer_person_id, disciplines_UIDs_chain_str: lecturer_row.disciplines_UIDs_chain_str, disciplines_Names_chain_str: lecturer_row.disciplines_Names_chain_str, load_base_UID2: lecturer_row.base_uid2}})
                .then(function(data)
                {
                  if (data.data.result == 'success')
                  {
                    toastr.success("Данные сохранены");
                    // Обновить статистику для ЗавКафа
                    LoadNagruzkaZavkafStat();

                    // CL($scope.nagruzka);
                    // CL(lecturer_row.base_uid);

                    const nagruzka_row = $scope.nagruzka.find(function(row)
                    { 
                      // CL(row.base_uid2);
                      return String(row.base_uid).trim() == String(lecturer_row.base_uid).trim();
                    }
                    );

                    // CL(nagruzka_row);

                    nagruzka_row.selected = false;
                  }
                  else
                  {
                    toastr.error("Ошибка");
                  }
                });
  }

  */
  


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
      if ($scope.MaySelectNagruzka($scope.nagruzka[i]))
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


    if ($scope.group_action.action == 'assign_to_sotrudnik')
    {
      $timeout(function() {
        // Находим input по id и устанавливаем фокус
        const input_id = 'group_action_autocomplete_value';
        const input = document.getElementById(input_id);
        if (input) {
            input.focus();
        }
      }, 200); // Небольшая задержка для рендеринга
    }
    else
    {
      $timeout(function() {
        // Находим input по id и устанавливаем фокус
        const input_id = 'group_action_comment';
        const input = document.getElementById(input_id);
        if (input) {
            input.focus();
        }
      }, 200);
    }

  }


  


  $scope.DoGroupAction = function()
  {
    CL('DoGroupAction');

    // Распределить всё на одного сотрудника
    if ($scope.group_action.action == 'assign_to_sotrudnik' && !isEmpty($scope.group_action.lecturer_fio))
    {
      // CL(angular.copy($scope.nagruzka));

      // При распределении на одного сотрудника нужно оставить одну строку распределения (nagruzka_row.lectors)
      $scope.nagruzka.forEach(nagruzka_row => 
      {
        if (nagruzka_row.selected)
        {
          // пропустим строки, в которых нельзя распределять лекторов
          if (!$scope.NagruzkaMayAssignLector(nagruzka_row, nagruzka_row['lectors'][0]))
          {
            nagruzka_row.selected = false;
            return;
          }

          // в качестве лектора за базу возьмём объект nagruzka_row
          // Create a clean copy by converting to JSON and back
          const new_lector = JSON.parse(JSON.stringify(nagruzka_row));
          // Remove the lectors property from the copy
          delete new_lector.lectors;

          new_lector.lecturer_fio = $scope.group_action.lecturer_fio;
          new_lector.lecturer_uid = $scope.group_action.lecturer_uid;
          new_lector.lecturer_person_id = $scope.group_action.lecturer_person_id;
          new_lector.lecturer_login = $scope.group_action.lecturer_login;
          new_lector.zs = true;

          nagruzka_row.lectors = [{}];

          // Preserve the original object's reference by updating its properties
          Object.keys(new_lector).forEach(key => {
              // Skip any properties that might cause issues
              if (key !== '$$hashKey' && key !== 'this' && key !== '$promise' && key !== '$resolved') {
                  nagruzka_row.lectors[0][key] = new_lector[key];
              }
          });

          // CL(nagruzka_row);

          $scope.SaveNagruzkaSubRows(nagruzka_row);

          // CL(nagruzka_row);
        }
      });



      // CL(angular.copy($scope.nagruzka));

      $scope.group_action.action = undefined;
    }
    // Распределить всё на «вакансию»
    else if ($scope.group_action.action == 'assign_to_vacancy')
    {
      $scope.nagruzka.forEach(nagruzka_row => 
      {
        // CL(item);
        if (nagruzka_row.selected)
        {
          // пропустим строки, в которых нельзя распределять лекторов
          if (!$scope.NagruzkaMayAssignLector(nagruzka_row, nagruzka_row['lectors'][0]))
          {
            nagruzka_row.selected = false;
            return;
          }

          // в качестве лектора за базу возьмём объект nagruzka_row
          // Create a clean copy by converting to JSON and back
          const new_lector = JSON.parse(JSON.stringify(nagruzka_row));
          // Remove the lectors property from the copy
          delete new_lector.lectors;

          new_lector.lecturer_fio = 'Вакансия';
          new_lector.lecturer_uid = '26115.281474976893938';
          new_lector.lecturer_person_id = '000000';
          new_lector.lecturer_login = '';
          new_lector.zs = true;

          nagruzka_row.lectors = [{}];

          // Preserve the original object's reference by updating its properties
          Object.keys(new_lector).forEach(key => {
              // Skip any properties that might cause issues
              if (key !== '$$hashKey' && key !== 'this' && key !== '$promise' && key !== '$resolved') {
                  nagruzka_row.lectors[0][key] = new_lector[key];
              }
          });

          $scope.SaveNagruzkaSubRows(nagruzka_row);
          // SaveNagruzkaLecturer(nagruzka_row);
        }
      });

      $scope.group_action.action = undefined;
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

        $scope.group_action.action = undefined;
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
        // Проверим, распределена ли хотя бы одна из выбранных строк нагрузки (есть преподаватель)

        let atLeastOneRowHasLecturer = $scope.nagruzka.some(nagruzka_row => 
        {
          var has_lecturer = false;

          nagruzka_row.lectors.forEach(lector => 
          {
            if (!isEmpty(lector.lecturer_fio) && lector.lecturer_fio != 'Вакансия') 
            {
              has_lecturer = true;
            }
          });

          return nagruzka_row.selected && has_lecturer; // nagruzka_row.selected && !isEmpty(nagruzka_row.lecturer_uid);
        });

        function doRequireAdminChange()
        {
          $scope.nagruzka.forEach(nagruzka_row => 
          {
            if (nagruzka_row.selected)
            {
              SaveNagruzkaStatus(nagruzka_row, 'require_admin_change');

              // nagruzka_row.lecturer_fio = nagruzka_row.lecturer_uid = nagruzka_row.lecturer_person_id = '';
              nagruzka_row.lectors.forEach(lector => 
              {
                lector.lecturer_fio = lector.lecturer_uid = lector.lecturer_person_id = '';
              });
            }
          });

          $scope.group_action.action = undefined;
        }

        if (atLeastOneRowHasLecturer)
        {
          ngDialog.openConfirm({
                template: 'confirm_require_admin_change',
                className: 'ngdialog-theme-default',
                disableAnimation: true
            }).then(function (value) {  // да

              doRequireAdminChange()
                
            });
        }
        else
        {
          doRequireAdminChange();
        }
        
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

        $scope.group_action.action = undefined;
      }
    }
    // Распределить нагрузку на несколько сотрудников
    else if ($scope.group_action.action == 'assign_to_several_sotrudniki')
    {
      const dialogScope = $scope.$new();

      var nagruzka_row;

      $scope.nagruzka.forEach(nagr => 
      {
        if (nagr.selected)
        {
          nagruzka_row = nagr;
        }
      });


      // CL(nagruzka_row);

      // Это значит, распределение
      // if (!nagruzka_row['lectors'][0].zs && !isEmpty(nagruzka_row['lectors'][0].lecturer_fio)) return;
      if (!$scope.NagruzkaMayAssignLector(nagruzka_row, nagruzka_row['lectors'][0]))
      {
        nagruzka_row.selected = false;
        return;
      }


      // nagruzka_row.lectors.forEach(nagr_lector => 
      // {
      //   nagr_lector.state = 'initial';
      // });

      dialogScope.nagruzka_amount_sum = $scope.GetNagruzkaAmountSum();
      // чтобы иметь возможность отменить правку в диалоге, сделаем отдельный объект
      dialogScope.nagruzka_row = angular.copy(nagruzka_row);

      // CL(nagruzka_row.lectors);

      // dialogScope.nagruzka_row = nagruzka_row;
      // dialogScope.nagruzka_history = $resource('ajax/get/get_nagruzka_history.php?load_base_UID2=' + nagruzka_row.base_uid2).query();

      ngDialog.open({
                    template: "assign_to_several_sotrudniki.tpl.html" + "?" + getRandom(10000, 99999),
                    scope: dialogScope,
                    plain: false,
                    disableAnimation: true,
                    className: 'ngdialog-theme-default history'
                  });
    }
    else return;
  }

  $scope.QuickOpenAssignToSeveralSotrudikiPopup = function(nagruzka_row)
  {
    $scope.group_action.action = 'assign_to_several_sotrudniki';
    nagruzka_row.selected = true;
    $scope.DoGroupAction();
  }

  $scope.GetNagruzkaLectorsInitialLength = function(nagruzka_lectors)
  {
    return nagruzka_lectors.filter(nagr_lector => nagr_lector.state == 'initial').length;
  }

  // nagruzka_lectors - Подстроки строки нагрузки распределения по преподавателям
  // fill_amounts == true => заполнить добавляемому лектору часы = часам всей нагрузки - используется после удаления всех лекторов, чтобы добавить "не распределено"
  $scope.AddNagruzkaSubRow = function(nagruzka_row, fill_amounts)
  {
    CL('AddNagruzkaSubRow');
    CL(nagruzka_row);

    // CL(nagruzka_row.lectors[0]);
    const new_lector = angular.copy(nagruzka_row.lectors[0]);
    new_lector.zs = true;
    new_lector.delete = false;

    if (new_lector.LoadType == '1')
    {
      new_lector.Amount = 0;
    }
    else
    {
      new_lector.StudentAmount = 0;
    }

    if (fill_amounts)
    {
      new_lector.Amount = nagruzka_row.Amount;
      new_lector.StudentAmount = nagruzka_row.StudentAmount;
    }

    new_lector.lecturer_login = new_lector.lecturer_person_id = new_lector.lecturer_fio = new_lector.lecturer_uid = '';
    nagruzka_row.lectors.push(new_lector);

    if (!$scope.$$phase) {
        $scope.$apply();
    }

    CL(nagruzka_row.lectors);
  }

  $scope.RemoveNagruzkaSubRow = function(nagruzka_row, index)
  {
    // nagruzka_lectors.splice(index, 1);

    const nagruzka_lectors = nagruzka_row.lectors;

    nagruzka_lectors[index].delete = true;

    // $scope.SaveNagruzkaSubRows(nagruzka_row);
  }

  $scope.GetNagruzkaAmountField = function(nagruzka_row)
  {
    var nagruzka_field_to_count = '';

    if (nagruzka_row)
    {
      if (nagruzka_row.LoadType == '0')
      {
        nagruzka_field_to_count = 'StudentAmount';
      }
      else
      {
        nagruzka_field_to_count = 'Amount';
      }
    }

    return nagruzka_field_to_count;
  }

  // сумма считается в часах либо студентах
  // для попапа распределения нагрузки сосчитать сумму в часах либо студентах
  $scope.GetNagruzkaLectorsAmountSum = function(nagruzka_row)
  {
    // CL('GetNagruzkaLectorsAmountSum');
    // CL(nagruzka_row);

    var nagruzka_field_to_count = $scope.GetNagruzkaAmountField(nagruzka_row);

    var nagruzka_lectors = [];

    if (nagruzka_row.lectors)
    {
      nagruzka_lectors = nagruzka_row.lectors.filter(lector => !lector.delete);
    }
    // else
    // {
    //   nagruzka_lectors = [];
    // }

    const sum = nagruzka_lectors.reduce((sum, lector) => sum + parseFloat(lector[nagruzka_field_to_count]), 0);

    // CL(sum);

    return !Number.isNaN(sum) ? sum : 0;
  }

  // Для таблицы Нагрузка, столбца "Количество часов" получить количество часов (Amount)
  // Если включен показ только одного лектора, то возьмём только для одного лектора
  $scope.GetNagruzkaAmount = function(nagruzka_row)
  {
    // в интерфейсе выбран показ нагрузки одного лектора
    if ($scope.nagruzka_selected_lecturer_uid)
    {
      var nagruzka_lectors = [];
      var sum;

      if (nagruzka_row.lectors)
      {
        nagruzka_lectors = nagruzka_row.lectors.filter(lector => !lector.delete && lector.lecturer_uid === $scope.nagruzka_selected_lecturer_uid);

        sum = nagruzka_lectors.reduce((sum, lector) => sum + parseFloat(lector['Amount']), 0);
      }

      return sum;
    }
    else
    {
      return nagruzka_row['Amount'];
    }
  }

  // определить, сколько в массиве не удалённых, не пустых лекторов, не вакансий
  $scope.GetNagruzkaRealLectorsNum = function(lectors)
  {
    const filtered = lectors.filter(function(lector)
    {
      return !isEmpty(lector.lecturer_fio) && lector.lecturer_fio.toLowerCase() !== 'вакансия' && !lector.delete;
    })

    // CL(filtered.length);

    return filtered.length;
  };

  $scope.GetNagruzkaLectorsRemain = function(nagruzka_row)
  {
    const nagruzka_field_to_count = $scope.GetNagruzkaAmountField(nagruzka_row);
    
    return roundToTwo(nagruzka_row[nagruzka_field_to_count] - $scope.GetNagruzkaLectorsAmountSum(nagruzka_row));
  }

  $scope.focusOutLecturer = function(row, data)
  {
    $scope.nagruzka.forEach(nagruzka_row => 
    {
      nagruzka_row.lectors.forEach(lector => 
      {
        lector.show_lecturer_autocomplete = false;
      });

    });
  }

  $scope.NagruzkaSelectedLecturer = function(data, lecturer_row)
  {
    CL('NagruzkaSelectedLecturer');
    
    // CL(data.originalObject);
    // CL(lecturer_row);

    const nagruzka_row = $scope.nagruzka.find(function(row)
    { 
      return String(row.base_uid).trim() == String(lecturer_row.base_uid).trim();
    });

    // удалить распределение
    if (data.originalObject.fio == '-')
    {
      lecturer_row.Amount = nagruzka_row.Amount;
      lecturer_row.StudentAmount = nagruzka_row.StudentAmount;

      lecturer_row.lecturer_login = lecturer_row.lecturer_person_id = lecturer_row.lecturer_fio = lecturer_row.lecturer_uid = lecturer_row.chair_id = lecturer_row.chair_name = '' ;
    }
    else if (!isEmpty(lecturer_row) && !isEmpty(data))
    {
      lecturer_row.lecturer_fio = data.originalObject.fio;
      lecturer_row.lecturer_uid = data.originalObject.lecturer_uid;
      lecturer_row.lecturer_person_id = data.originalObject.person_id;
      lecturer_row.lecturer_login = data.originalObject.lecturer_login;
      // берём кафедру лектора из selected_chair_sotrudniki.php, т.е. из автокомплитаs
      lecturer_row.chair_id = data.originalObject.chair_id;
      lecturer_row.chair_name = data.originalObject.chair_name;
      lecturer_row.zs = true;
    }

    // lecturer_row.base_uid2
    
    

    // CL('Before merging:');
    // CL(nagruzka_row.lectors);

    lecturer_row.show_lecturer_autocomplete = false;

    // CL(nagruzka_row);

    // SaveNagruzkaLecturer(lecturer_row);

    // Before saving, check for duplicate lecturers
    if (nagruzka_row.lectors && nagruzka_row.lectors.length > 1) 
    {
        const uniqueLecturers = {};
        const lecturersWithoutUid = [];
        
        // Group lecturers by lecturer_uid
        nagruzka_row.lectors.forEach(lector => {
            // Skip deleted lecturers from grouping
            if (lector.delete === true) {
                return;
            }
            
            const lecturerUid = lector.lecturer_uid;
            
            // If lecturer_uid is empty, add to a separate array
            if (isEmpty(lector.lecturer_uid)) {
                lecturersWithoutUid.push(lector);
                return;
            }
            
            if (isEmpty(uniqueLecturers[lecturerUid])) 
            {
                // First occurrence of this lecturer
                uniqueLecturers[lecturerUid] = {
                    ...lector,
                    // Convert to numbers for proper addition
                    Amount: parseFloat(lector.Amount) || 0,
                    StudentAmount: parseInt(lector.StudentAmount) || 0
                };
            } else {
                // Add to existing lecturer's amounts
                uniqueLecturers[lecturerUid].Amount += parseFloat(lector.Amount) || 0;
                uniqueLecturers[lecturerUid].StudentAmount += parseInt(lector.StudentAmount) || 0;
            }
        });

        // CL(uniqueLecturers);
        
        // Combine unique lecturers with those that didn't have UIDs
        nagruzka_row.lectors = [
            ...Object.values(uniqueLecturers),
            ...lecturersWithoutUid
        ];
    }

    CL('After merging:');
    CL(nagruzka_row.lectors);

    $scope.SaveNagruzkaSubRows(nagruzka_row);

    // $scope.$broadcast('angucomplete-alt:clearInput'); //, 'lecturer_autocomplete_' + nagruzka_row['xml_content_of_load_UID'] + '_' + nagruzka_row['xml_content_of_load_staff_UID']);

    // CL('lecturer_autocomplete_' + nagruzka_row['xml_content_of_load_UID'] + '_' + nagruzka_row['xml_content_of_load_staff_UID']);
  }


  // чтобы подсветить красным некорректную сумму введённой нагрузки
  $scope.IsNagruzkaLectorsSumCorrect = function(nagruzka_row)
  {
    var nagruzka_field_to_count = $scope.GetNagruzkaAmountField(nagruzka_row);

    // if (nagruzka_field_to_count == 'StudentAmount')
    // {
    //   var hours_per_student = nagruzka_row.Amount / nagruzka_row['StudentAmount'];
    // }

    const sum = $scope.GetNagruzkaLectorsAmountSum(nagruzka_row);

    // CL(sum);
    // CL(parseFloat(nagruzka_row[nagruzka_field_to_count]));

    // CL(typeof sum);
    // CL(typeof parseFloat(nagruzka_row[nagruzka_field_to_count]));

    return sum == parseFloat(nagruzka_row[nagruzka_field_to_count]);
  }

  // nagruzka_row должен быть строкой нагрузки по ссылке, а не клоном
  // one_selected_nagruzka_row_passed_as_clone_from_dialog - не пусто, если строка нагрузки пришла из диалога распределения
  // на нескольких сотрудников, при этом она выбирается одна
  $scope.SaveNagruzkaSubRows = function(nagruzka_row, one_selected_nagruzka_row_passed_as_clone_from_dialog)
  {
    CL('SaveNagruzkaSubRows');
    // CL(nagruzka_row.lectors);

    const nagruzka_lectors = nagruzka_row.lectors;

    var nagruzka_field_to_count;

    // нагрузка в студентах

    var nagruzka_field_to_count = $scope.GetNagruzkaAmountField(nagruzka_row);

    if (nagruzka_field_to_count == 'StudentAmount')
    {
      var hours_per_student = nagruzka_row.Amount / nagruzka_row['StudentAmount'];
    }

    var not_deleted_lectors_count = 0;

    angular.forEach(nagruzka_lectors, function(lector)
    {
      if (nagruzka_field_to_count == 'StudentAmount')
      {
        lector.Amount = hours_per_student * lector['StudentAmount'];
      }
    
      if (lector[nagruzka_field_to_count] == 0)
      {
        lector.delete = true;
      }

      if (!lector.delete)
      {
        not_deleted_lectors_count++;
      }

    });

    // CL(nagruzka_row.lectors);
    // Если удалили всех лекторов, нужно добавить пустой объект "не распределено"
    if (not_deleted_lectors_count == 0)
    {
      $scope.AddNagruzkaSubRow(nagruzka_row, true);
    }

    if (!$scope.IsNagruzkaLectorsSumCorrect(nagruzka_row))
    {
      toastr.error("Некорректная сумма");
    }
    else
    {
      $http({url: 'ajax/post/save_nagruzka_sub_rows.php', method: 'POST', data: nagruzka_lectors})
                .then(function(data)
                {
                  if (data.data.result == 'success')
                  {
                    angular.forEach($scope.nagruzka, function(currentRow, ind) 
                    {
                      if (currentRow.selected) 
                      {

                        // -- ниже код ошибочный, т.к. nagruzka_row в эту функцию передаётся "по ссылке", а не как клон строки
                        // !!! ошибка здесь в том, что тут подразумевается, что выбрана только одна строка нагрузки,
                        // а на самом деле сюда попадаем и когда выбрано несколько строк.
                        // Также нужно понять, что когда выбрана одна строка, то из диалога (распр. на неск. сотр-в) сюда приходит клон строки, а в остальных вызовах этой функции сюда приходит строка по значению, и этот код не нужен.
                        // Может быть нужно просто вызывать этот код только при вызове из диалога (доп. аргумент).
                        // И багоопасно здесь оставлять без указания или проверки, что подразумевается: выбрана только одна строка
                        
                        if (one_selected_nagruzka_row_passed_as_clone_from_dialog)
                        {
                          try 
                          {
                              // First, save the current state of the row
                              // const currentRow = $scope.nagruzka[ind];
                              
                              // Create a clean copy by converting to JSON and back
                              const cleanCopy = JSON.parse(JSON.stringify(nagruzka_row));
                              
                              // Preserve the original object's reference by updating its properties
                              Object.keys(cleanCopy).forEach(key => {
                                  // Skip any properties that might cause issues
                                  if (key !== '$$hashKey' && key !== 'this' && key !== '$promise' && key !== '$resolved') {
                                      currentRow[key] = cleanCopy[key];
                                  }
                              });
                              
                              // Explicitly set selected to false
                              currentRow.selected = false;
                              
                            } catch (e) {
                                console.error('Error updating row:', e);
                                // Fallback to a simple property copy if JSON methods fail
                                currentRow.selected = false;
                            }
                          }

                          currentRow.selected = false;
                        }
                    });
                    

                    // Force Angular to detect the changes
                    if (!$scope.$$phase) {
                        $scope.$apply();
                    }

                    // angular.forEach($scope.nagruzka, function(nagr, ind) {
                    //     if (nagr.selected) {
                    //         // Copy all properties from nagruzka_row to the existing object
                    //         // This preserves the object reference which helps with Angular's digest cycle
                    //         angular.copy(nagruzka_row, $scope.nagruzka[ind]);
                    //         $scope.nagruzka[ind].selected = false;  // Unselect the row
                    //     }
                    // });

                    if (c_roles.zavkaf)
                    {
                      const lecturer_uid = $scope.nagruzka_selected_lecturer_uid;
                      const chair_id = $scope.nagruzka_selected_chair_id;
                      $scope.NagruzkaCtrlUpdateNagruzkaStat($scope._nagruzka_type, chair_id, lecturer_uid, true);
                    } 

                    toastr.success("Данные сохранены");

                  }
                  else
                  {
                    toastr.error("Ошибка");
                  }
                });


      return true;
    }
    

  }


  $scope.GetNagruzkaUnits = function(nagruzka_row)
  {
    if (nagruzka_row.LoadType == '0') return 'студ.';
    else if (nagruzka_row.LoadType == '1') return 'час.';
  }

  $scope.GetNagruzkaUnitsFull = function(nagruzka_row)
  {
    if (nagruzka_row.LoadType == '0') return 'Студенты';
    else if (nagruzka_row.LoadType == '1') return 'Часы';
  }

  function SaveNagruzkaStatus(nagruzka_row, new_status)
  {
    $http({url: 'ajax/post/save_nagruzka_status.php', method: 'POST', data: {status: new_status, message: $scope.group_action.message, load_base_UID2: nagruzka_row.base_uid2}})
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
                      nagruzka_row.selected = false
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

  $scope.MaySelectNagruzka = function(nagruzka_row)
  {
    // return true;
    
    // CL(!isEmpty(nagruzka_row));

    const val = (c_roles.zavkaf || c_roles.ruk_aspirantura) && ($scope.system_mode == 'mode_filling' || $scope.system_mode == 'mode_verification')
    && (!isEmpty(nagruzka_row) && !['refused', 'require_admin_change', 'done_change'].includes(nagruzka_row.status) || nagruzka_row == undefined)
    // УОУП может только разбивать нагрузку
    || c_roles.uoup && $scope.system_mode == 'mode_verification' && !['ksro', 'aspirantura'].includes($scope._nagruzka_type);

    // CL(val);

    return val;
  }



/*
  $scope.IsNagruzkaRowEditable = function(nagruzka_row)
  {
    const editable = $scope.system_mode == 'mode_filling' && c_roles.zavkaf && !['refused', 'require_admin_change', 'done_change'].includes(nagruzka_row.status) 
    && !isEmpty(nagruzka_row.lectors) && (nagruzka_row.lectors[0].zs || isEmpty(nagruzka_row.lectors[0].lecturer_fio)) ;
 
    // CL(nagruzka_row.status);
    // CL(editable);
    return editable;
  }
*/
  
  $scope.NagruzkaMayAssignLector = function(nagruzka_row, lector)
  {
    // return true;

    // CL((lector == undefined || lector.zs || isEmpty(lector.lecturer_fio)) && $scope.system_mode == 'mode_filling' && c_roles.zavkaf && !['refused', 'require_admin_change', 'done_change'].includes(nagruzka_row.status) 
    // && !isEmpty(nagruzka_row.lectors) && (nagruzka_row.lectors[0].zs || isEmpty(nagruzka_row.lectors[0].lecturer_fio)));

     // (lector.zs || isEmpty(lector.lecturer_fio)) // && $scope.IsNagruzkaRowEditable(nagruzka_row)
    // && 
    return (lector == undefined || lector.zs || isEmpty(lector.lecturer_fio)) && $scope.system_mode == 'mode_filling' && (c_roles.zavkaf || c_roles.ruk_aspirantura) && !['refused', 'require_admin_change', 'done_change'].includes(nagruzka_row.status) 
    && !isEmpty(nagruzka_row.lectors) && (nagruzka_row.lectors[0].zs || isEmpty(nagruzka_row.lectors[0].lecturer_fio))
    || $scope.system_mode == 'mode_verification' && c_roles.uoup && !['ksro', 'aspirantura'].includes($scope._nagruzka_type);
  }


  $scope.IsGroupActionAllowed = function(group_action)
  {
    return c_roles.zavkaf && ($scope.system_mode == 'mode_filling' || $scope.system_mode == 'mode_verification' && group_action == 'require_admin_change')
    || c_roles.ruk_aspirantura && ($scope.system_mode == 'mode_filling' || $scope.system_mode == 'mode_verification') && ['assign_to_sotrudnik', 'refuse_nagruzka', 'require_admin_change', 'write_admin_comment'].includes(group_action)
    || c_roles.uoup && $scope.system_mode == 'mode_verification' && ['assign_to_several_sotrudniki', 'assign_to_sotrudnik', 'assign_to_vacancy'].includes(group_action);
  }

  // $scope.ShowNagruzkaZavkafTypeRow = function(type)
  // {
  //   return true; // isEmpty(nagruzka_type) || nagruzka_type == 'all'; //  || type == nagruzka_type;
  // }

  $scope.NagruzkaShowLecturerAutocomplete = function(lector, input_id)
  {
    // CL(input_id);
    lector.show_lecturer_autocomplete = true;

    $timeout(function() {
        // Находим input по id и устанавливаем фокус
        input_id = input_id + '_value';
        const input = document.getElementById(input_id);
        if (input) {
            input.focus();
        }
    }, 200); // Небольшая задержка для рендеринга
  }


  $scope.GetNagruzkaStatusSum = function(chair_id, stat_type)
  {
    if (!isEmpty($scope.nagruzka_stat))
    return (!isEmpty($scope.nagruzka_stat[chair_id]['discipline']) && !isEmpty($scope.nagruzka_stat[chair_id]['discipline'][stat_type]) ? parseFloat($scope.nagruzka_stat[chair_id]['discipline'][stat_type]['sum']) : 0)

          + (!isEmpty($scope.nagruzka_stat[chair_id]['ruk_vkr']) && !isEmpty($scope.nagruzka_stat[chair_id]['ruk_vkr'][stat_type]) ? parseFloat($scope.nagruzka_stat[chair_id]['ruk_vkr'][stat_type]['sum']) : 0)

          + (!isEmpty($scope.nagruzka_stat[chair_id]['ruk_kurs']) && !isEmpty($scope.nagruzka_stat[chair_id]['ruk_kurs'][stat_type]) ? parseFloat($scope.nagruzka_stat[chair_id]['ruk_kurs'][stat_type]['sum']) : 0)

          + (!isEmpty($scope.nagruzka_stat[chair_id]['ruk_practice']) && !isEmpty($scope.nagruzka_stat[chair_id]['ruk_practice'][stat_type]) ? parseFloat($scope.nagruzka_stat[chair_id]['ruk_practice'][stat_type]['sum']) : 0)

          + (!isEmpty($scope.nagruzka_stat[chair_id]['ksro']) && !isEmpty($scope.nagruzka_stat[chair_id]['ksro'][stat_type]) ? parseFloat($scope.nagruzka_stat[chair_id]['ksro'][stat_type]['sum']) : 0)

          + (!isEmpty($scope.nagruzka_stat[chair_id]['gia']) && !isEmpty($scope.nagruzka_stat[chair_id]['gia'][stat_type]) ? parseFloat($scope.nagruzka_stat[chair_id]['gia'][stat_type]['sum']) : 0)

          + (!isEmpty($scope.nagruzka_stat[chair_id]['aspirant']) && !isEmpty($scope.nagruzka_stat[chair_id]['aspirant'][stat_type]) ? parseFloat($scope.nagruzka_stat[chair_id]['aspirant'][stat_type]['sum']) : 0)
  }


  // Сотруднику не показывать статистику (соответственно и вход в нагрузку), если завкаф закрыл просмотр, либо не тот режим
  $scope.NagruzkaStatVisible = function(chair_id)
  {
    // if (!isEm$scope.chairs_sprav[chair_id])
    // CL($scope.chairs_sprav[chair_id]['visible']);

    // в этих режимах всегда видно, там вроде readonly
    if ($scope.system_mode == 'mode_verification' || $scope.system_mode == 'mode_archive')
    {
      return true;
    }
    // если это сотрудник (единственная роль) и просмотр отключен завкафом
    else if (c_roles.sotrudnik && Object.keys(c_roles).length == 1 && !isEmpty($scope.chairs_sprav[chair_id]) && !$scope.chairs_sprav[chair_id]['visible'])
    {
      return false;
    }
    else if (!isEmpty($scope.chairs_sprav[chair_id]))
    {
      return true;
    }
  }


  
  

})

.controller ('UOUPNagruzkaCtrl', function($rootScope, $scope, $http, DTOptionsBuilder, DTColumnBuilder, DTColumnDefBuilder, ngDialog, $templateCache, $resource, uoup_nagruzka, nagruzka_uoup_stat, page, $cookies, system_mode, chairs_sprav, $location) // 
{
  CL('UOUPNagruzkaCtrl');

  $rootScope.page = page;

  

  $scope.system_mode = system_mode.data.mode; 

  if ($scope.system_mode === 'export_to_galaktika') 
  {
    return;
  }

  $templateCache.put('confirm_delete', '<p>Вы уверены, что хотите удалить?</p>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Нет</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(1)">Да</button>\
              </div>');

  
  $scope.$_forms_obuchenia = $_forms_obuchenia;
  $scope.chairs_sprav = chairs_sprav.data;
  $scope.system_mode = system_mode.data.mode; 

  $scope.dtInstance = {};
  $scope.filter_distinct = {};
  $scope.group_action = {};
  $scope.uoup_nagruzka = uoup_nagruzka ? uoup_nagruzka.data : null;
  $scope.nagruzka_uoup_stat = {}; //nagruzka_uoup_stat.data;

  $scope.isLoading = true;

  // нужно очистить глобальный фильтр, иначе он может притащиться из /#/nagruzka, и здесь невидимо всё портить
  // а он не используется по этому адресу
  $cookies.put('global_nagruzka_filter', "");

  // $scope.uoup_nagruzka = $resource('ajax/get/uoup_nagruzka.php').query();

  // LoadNagruzkaUOUPStat();

  var page_title;
  var columns;

  if ($scope.page == 'uoup_nagruzka_no_type')
  {
    page_title = 'Нагрузка без типа';
  }
  else if ($scope.page == 'uoup_nagruzka_no_chair')
  {
    page_title = 'Нагрузка без кафедры';
  }
  else if ($scope.page == 'uoup_nagruzka')
  {
    page_title = 'Нагрузка';


  }


  if ($scope.page == 'uoup_nagruzka_no_type' || $scope.page == 'uoup_nagruzka_no_chair')
  {
    columns = [
      
      // UID нагрузки
      null,

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
  }
  else if (page == 'uoup_nagruzka')
  {
    columns = [
      
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
  }

  


  // $scope.persons = $resource('data.json').query();

  

  $scope.dtOptions = DTOptionsBuilder //.fromSource('data.json')
    .newOptions()
    .withOption('stateSave', true)

    .withOption('stateSaveCallback', function(settings, data) {
        const path = $location.path();
        const storageKey = 'DataTables_Table_uoup_nagruzka_' + path.replace(/\//g, '_');
        localStorage.setItem(storageKey, JSON.stringify(data));
    })
    .withOption('stateLoadCallback', function(settings) {
        const path = $location.path();
        const storageKey = 'DataTables_Table_uoup_nagruzka_' + path.replace(/\//g, '_');
        const saved = localStorage.getItem(storageKey);
        return saved ? JSON.parse(saved) : null;
    })
   
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
    // .withDOM('flrtip')
    // .withDOM('flBrtip')
    // .withDOM('<"pull-right"fB>rtip')
    // .withDOM('<"top"f<"pull-right"B>>rtip')
    .withButtons([
          {
            extend: 'excel',
            text: 'Excel', // Текст на самой кнопке
            filename: page_title, // Имя файла
            title: page_title // Заголовок на первой строке листа
          }
      ])
    .withOption('initComplete', function(settings, json) {
        // Скрываем индикатор когда загрузка завершена
        $scope.$apply(function() {
          CL('initComplete');
            // $scope.isLoading = false;
        });
      })
    ;

  $scope.dtColumnDefs = [
    // DTColumnDefBuilder.newColumnDef(7).notVisible().notSortable(),
  ];

  $scope.onUOUPNagruzkaTableInstance = function(dtInstance) 
  {
    CL('onUOUPNagruzkaTableInstance');
    $scope.isLoading = true;

    $scope.dtInstance = dtInstance;
    const table = dtInstance.DataTable;
  }

  
  // УОУП открывает на просмотр нагрузку кафедры
  $scope.UOUPOpenChairNagruzka = function(chair_id)
  {
    // CL('UOUPOpenChairNagruzka');
    // CL(chair_id);

    if (!isEmpty(chair_id))
    {
      window.location = '#/nagruzka/all/' + chair_id;
    }
  }


  $scope.ShowNagruzkaUOUPTypeRow = function()
  {
    return true;
  }

  // $scope.nagruzka_stat = {};
  // $scope.nagruzka = {};

  $scope.UpdateUOUPNagruzkaStat = function(nagr_type)
  {
    // CL('UpdateUOUPNagruzkaStat');
    // CL(chair_id);

    var script, url;

    if (nagr_type == 'ksro')
    {
      if ($scope.system_mode == 'mode_filling')
      {
        script = 'ksro';
        url = `ajax/get/ksro.php?type=${nagr_type}&only_stat=1`;
      }
      else
      {
        script = 'nagruzka';
        url = `ajax/get/nagruzka/?type=${nagr_type}&only_stat=1`;
      }
    }
    else
    {
      script = 'nagruzka';
      url = `ajax/get/nagruzka/?type=${nagr_type}&only_stat=1`;
    }

    

    // var url = `ajax/get/nagruzka.php?type=${nagr_type}&only_stat=1`;

    // if (nagr_type == 'discipline')
    {
      $http({url: url, method: 'GET'})
      .then(function (response) 
      {
        if (response.data)
        {
          // if (isEmpty($scope.nagruzka_stat[chair_id])) $scope.nagruzka_stat[chair_id] = {};
          // if (isEmpty($scope.nagruzka[chair_id])) $scope.nagruzka[chair_id] = {};

          // $scope.nagruzka = response.data.nagruzka;
          $scope.nagruzka_uoup_stat[nagr_type] = response.data.stat;

          if (nagr_type != 'all')
          $scope.isLoading = false;

          // CL($scope.nagruzka_stat);
          // CL(response.data.nagruzka);

        }
      })
    }
  }

  // Нагрузка загружается либо до контроллера, либо здесь, в зависимости от страницы, где находимся
  if (!$scope.uoup_nagruzka)
  {
    $scope.uoup_nagruzka = {};
    
    $http({url: "ajax/get/nagruzka/?lite=1", method: 'GET'})
        .then(function (response) 
        {
          if (response.data)
          {
            $scope.uoup_nagruzka = response.data.nagruzka;
            // $scope.isLoading = false;
          }
        })

    $scope.UpdateUOUPNagruzkaStat('discipline');
    $scope.UpdateUOUPNagruzkaStat('ruk_vkr');
    $scope.UpdateUOUPNagruzkaStat('ruk_kurs');
    $scope.UpdateUOUPNagruzkaStat('ruk_practice');
    $scope.UpdateUOUPNagruzkaStat('ksro');
    $scope.UpdateUOUPNagruzkaStat('gia');
    $scope.UpdateUOUPNagruzkaStat('aspirant');
  }

  
  $scope.GetUOUPNagruzkaStatusSum = function(stat_type)
  {
    // if (!isEmpty($scope.nagruzka_uoup_stat['discipline']) && !isEmpty($scope.nagruzka_uoup_stat['discipline'][stat_type]))
    // CL($scope.nagruzka_uoup_stat['discipline'][stat_type]['sum']);

    if (!isEmpty($scope.nagruzka_uoup_stat))
    return (!isEmpty($scope.nagruzka_uoup_stat['discipline']) && !isEmpty($scope.nagruzka_uoup_stat['discipline'][stat_type]) ? parseFloat($scope.nagruzka_uoup_stat['discipline'][stat_type]['sum']) : 0)

          + (!isEmpty($scope.nagruzka_uoup_stat['ruk_vkr']) && !isEmpty($scope.nagruzka_uoup_stat['ruk_vkr'][stat_type]) ? parseFloat($scope.nagruzka_uoup_stat['ruk_vkr'][stat_type]['sum']) : 0)

          + (!isEmpty($scope.nagruzka_uoup_stat['ruk_kurs']) && !isEmpty($scope.nagruzka_uoup_stat['ruk_kurs'][stat_type]) ? parseFloat($scope.nagruzka_uoup_stat['ruk_kurs'][stat_type]['sum']) : 0)

          + (!isEmpty($scope.nagruzka_uoup_stat['ruk_practice']) && !isEmpty($scope.nagruzka_uoup_stat['ruk_practice'][stat_type]) ? parseFloat($scope.nagruzka_uoup_stat['ruk_practice'][stat_type]['sum']) : 0)

          + (!isEmpty($scope.nagruzka_uoup_stat['ksro']) && !isEmpty($scope.nagruzka_uoup_stat['ksro'][stat_type]) ? parseFloat($scope.nagruzka_uoup_stat['ksro'][stat_type]['sum']) : 0)

          + (!isEmpty($scope.nagruzka_uoup_stat['gia']) && !isEmpty($scope.nagruzka_uoup_stat['gia'][stat_type]) ? parseFloat($scope.nagruzka_uoup_stat['gia'][stat_type]['sum']) : 0)

          + (!isEmpty($scope.nagruzka_uoup_stat['aspirant']) && !isEmpty($scope.nagruzka_uoup_stat['aspirant'][stat_type]) ? parseFloat($scope.nagruzka_uoup_stat['aspirant'][stat_type]['sum']) : 0)
  }
  
  
})

.controller ('SystemModeCtrl', function($rootScope, $scope, page, system_mode, $http, ngDialog, $templateCache, FileUploader, $resource) 
{
  CL('SystemModeCtrl');
  
  $rootScope.page = page;
  
  // Шаблон подтверждения перехода в режим выверки
  $templateCache.put('confirm_export_to_galaktika', '<p>Переход в режим выверки, данные будут выгружены в Галактику при следующей синхронизации. Переход в режим выверки должен происходить только один раз в год. Вы уверены?</p>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Нет</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(1)">Да</button>\
              </div>');
  
  $scope.systemModes = $_system_modes; 
  $scope.currentMode = system_mode.data.mode; 
  $scope.previousMode = system_mode.data.mode; // Храним предыдущий режим

  CL($scope.currentMode);

  // Сохранить режим работы
  $scope.SaveSystemMode = function()
  {
    CL('SaveSystemMode');
    CL($scope.currentMode);

    // Если выбран режим Выверка - показываем confirm
    if ($scope.currentMode === 'mode_verification')
    {
      ngDialog.openConfirm({
        template: 'confirm_export_to_galaktika',
        className: 'ngdialog-theme-default',
        disableAnimation: true
      }).then(function()
      {
        // Пользователь подтвердил - сохраняем режим Выгрузка данных в Галактику
        CL('Сохраняем режим: export_to_galaktika');
        $scope.currentMode = 'export_to_galaktika';
        $scope.previousMode = 'export_to_galaktika';
        
        $http.post('ajax/post/save_system_mode.php', {mode: 'export_to_galaktika'}) 
          .then(function(response)
          {
            toastr.success('Режим работы сохранен');
          });
      }, function()
      {
        // Пользователь отменил - возвращаем предыдущий режим
        CL('Отмена, возвращаем режим: ' + $scope.previousMode);
        $scope.currentMode = $scope.previousMode;
      });
    }
    else
    {
      // Обычное сохранение для других режимов
      $scope.previousMode = $scope.currentMode;
      
      $http.post('ajax/post/save_system_mode.php', {mode: $scope.currentMode}) 
        .then(function(response)
        {
          toastr.success('Режим работы сохранен');
          $scope.previousMode = $scope.currentMode;
        });
    }
  }

  $scope.zavkaf_instruction = $resource('ajax/get/get_zavkaf_instruction_data_for_uoup.php').get();

  var zavkaf_instruction_uploader_init = {
    scope: $scope,
    url: 'ajax/post/zavkaf_instruction_upload.php',
    formData: [],
    autoUpload: true
    // filters: [ function (item) {return true; } ]
  };

  $scope.zavkaf_instruction_uploader = new FileUploader(zavkaf_instruction_uploader_init);

  $scope.zavkaf_instruction_uploader.onSuccessItem = function(item, response, status, headers)
  {
    CL(response.result);
    if (response.result == 'success')
    {
      $scope.zavkaf_instruction.comment = response.created_file.file_src_name;
      $scope.zavkaf_instruction.datetime = response.created_file.date;
      toastr.success("Файл загружен");
    }
    else toastr.error("Ошибка при загрузке файла");
  }

})

// Админ УОУП просматривает отказы зав. кафедрами от нагрузки и отменяет отказы
.controller ('UOUPChairsRefusedCtrl', function($rootScope, $scope, $http, DTOptionsBuilder, DTColumnBuilder, DTColumnDefBuilder, ngDialog, $templateCache, $resource, uoup_nagruzka, system_mode, $filter, $timeout, $location, $q) 
{
  CL('UOUPChairsRefusedCtrl');

  $rootScope.page = 'uoup_chairs_refused';
  $scope.system_mode = system_mode.data.mode; 

  if ($scope.system_mode === 'export_to_galaktika') 
  {
    CL("Режим работы Выгрузка в Галактику, поэтому пусто");
    return;
  }

  $templateCache.put('confirm_delete', '<p>Вы уверены, что хотите удалить?</p>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Нет</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(1)">Да</button>\
              </div>');

  
  // $scope.$_forms_obuchenia = $_forms_obuchenia;
  $scope.system_mode = system_mode.data.mode; 
  // CL($scope.system_mode); 

  $scope.dtInstance = {};
  // заглушка
  $scope.filter_distinct = {};
  // $scope.group_action = {};
  $scope.nagruzka = uoup_nagruzka.data;

  // 1. Add these variables after $scope.nagruzka = uoup_nagruzka.data;
  $scope.allNagruzka = Array.isArray(uoup_nagruzka.data) ? angular.copy(uoup_nagruzka.data) : [];
  $scope.filteredNagruzka = angular.copy($scope.allNagruzka);
  $scope.adminChangeChairs = buildAdminChangeChairs($scope.allNagruzka);
  // CL($scope.adminChangeChairs);
  $scope.selectedAdminChangeChair = null;
  $scope.chairComments = [];
  $scope.viewState = 'chairs'; // 'chairs' or 'table'

  // 2. Add these functions before the controller ends
  function buildAdminChangeChairs(rows) 
  {
    CL('UOUPChairsRefusedCtrl buildAdminChangeChairs');

    const departmentsMap = {};

    // Use the new field names
    const commentField = 'refused_change_message';
    const dateField = 'refused_date';

    // console.log('Using fields:', { commentField, dateField });

    // First pass: build the structure
    rows.forEach(row => {
      if (row.status !== 'refused' && row.status !== 'done_refused') return;

      const deptKey = row.department_name || 'Без факультета';
      const chairKey = row.chair_id || 'no_chair';
      const chairName = row.chair_name || 'Без кафедры';
      const comment = row[commentField];
      
      // Get date from the new refused_date field
      let commentDate;
      if (row[dateField]) {
        commentDate = new Date(row[dateField]);
        // Set to noon to avoid timezone issues
        commentDate.setHours(12, 0, 0, 0);
      } else {
        // Fallback to current date if no date is available
        commentDate = new Date();
        commentDate.setHours(12, 0, 0, 0);
      }

      // Format date for grouping (just date, no time)
      // const formattedDate = $filter('date')(commentDate, 'yyyy-MM-dd');
      // Keep the original date with full precision (including seconds)
      let commentDateTime = row[dateField] ? new Date(row[dateField]) : new Date();
      const commentKey = commentDateTime.getTime() + '|' + comment;

      
      // Create a key that includes both date (with seconds) and comment
      // const dateCommentKey = commentDateTime.getTime() + '|' + comment;

      // Skip if no comment or empty comment
      if (!comment || comment.trim() === '') {
        return;
      }

      // Initialize department if not exists
      if (!departmentsMap[deptKey]) {
        departmentsMap[deptKey] = {
          department_name: deptKey,
          chairs: [],
          count: 0,
          chairMap: {},
          show_chairs: false
        };
      }

      // Initialize chair if not exists
      if (!departmentsMap[deptKey].chairMap[chairKey]) {
        const newChair = {
          chair_id: chairKey,
          chair_name: chairName,
          count: 0,
          comments: [],
          commentMap: {}
        };
        
        departmentsMap[deptKey].chairs.push(newChair);
        departmentsMap[deptKey].chairMap[chairKey] = newChair;
      }

      const chair = departmentsMap[deptKey].chairMap[chairKey];
      chair.count++;
      departmentsMap[deptKey].count++;

      // Initialize comment group if not exists
      if (!chair.commentMap[commentKey]) {
        const commentGroup = {
          key: commentKey,
          // у всей группы строк по идее должен быть одинаковый статус (refused либо done_refused)
          status: row.status,
          message: comment,
          date: commentDateTime.toISOString(),
          dateFormatted: $filter('date')(commentDateTime, 'dd.MM.yyyy HH:mm:ss'),
          count: 0,
          rows: []
        };

        chair.comments.push(commentGroup);
        chair.commentMap[commentKey] = commentGroup;
      }

      // Add this row to the comment group
      chair.commentMap[commentKey].count++;
      chair.commentMap[commentKey].rows.push(row);
    });

    // Sort comments by date (newest first)
    Object.values(departmentsMap).forEach(dept => {
      dept.chairs.forEach(chair => {
        chair.comments.sort((a, b) => new Date(b.date) - new Date(a.date));
      });
    });

    // Clean up internal maps before returning
    const result = Object.values(departmentsMap).map(dept => {
      const { chairMap, ...deptRest } = dept;
      const chairs = deptRest.chairs.map(chair => {
        const { commentMap, ...chairRest } = chair;
        return chairRest;
      });
      return { ...deptRest, chairs };
    });

    // console.log('Built adminChangeChairs:', result);
    // CL(result);
    
    return result;
  }

  $scope.NagruzkaRefusedeToggleAdminShowDepartmentChairs = function(department)
  {
    department.show_chairs = !department.show_chairs;
  }

/*

// Add this to your controller and click the debug button
$scope.debugDateFields = function() {
  console.log('=== DEBUG DATE FIELDS ===');
  const sampleRow = $scope.allNagruzka.find(row => row.status === 'refused');
  if (sampleRow) {
    console.log('Available date fields in row:', {
      date: sampleRow.date,
      comment_date: sampleRow.comment_date,
      created_at: sampleRow.created_at,
      updated_at: sampleRow.updated_at
    });
    console.log('All row fields:', Object.keys(sampleRow));
  } else {
    console.log('No rows with status "refused" found');
  }
};

// Add this to your controller
$scope.debugChairData = function(chair) {
  console.log('=== DEBUG CHAIR DATA ===');
  console.log('Chair ID:', chair.chair_id);
  console.log('Chair name:', chair.chair_name);
  console.log('Comments array:', chair.comments);
  
  const chairRows = $scope.allNagruzka
    .filter(r => r.chair_id === chair.chair_id && r.status === 'refused');
  
  console.log('Total rows for chair:', chairRows.length);
  
  if (chairRows.length > 0) {
    const firstRow = chairRows[0];
    console.log('Available fields in row:', Object.keys(firstRow));
    
    console.log('Comment fields in rows:');
    chairRows.slice(0, 5).forEach((row, i) => {
      console.log(`Row ${i}:`, {
        comment_to_admin: row.comment_to_admin,  // Added this line
        comment: row.comment,
        Comment: row.Comment,
        comment_text: row.comment_text,
        status: row.status,
        chair_id: row.chair_id
      });
    });
  }
  
  console.log('Sample rows (first 3):', chairRows.slice(0, 3));
  console.log('========================');
};

*/


$scope.toggleAdminChangeChair = function(chair) 
{
  CL('UOUPChairsRefusedCtrl toggleAdminChangeChair');

  // console.log('Toggling chair:', chair);
  
  if ($scope.selectedAdminChangeChair && $scope.selectedAdminChangeChair.chair_id === chair.chair_id) {
    $scope.selectedAdminChangeChair = null;
  } else {
    $scope.selectedAdminChangeChair = chair;
  }
};
  

  $scope.selectChairComment = function(comment) 
  {
    CL('UOUPChairsRefusedCtrl selectChairComment');

    $scope.selectedChairComment = $scope.selectedChairComment && 
                                $scope.selectedChairComment.key === comment.key ? 
                                null : comment;
  };

  $scope.showTable = function(comment) 
  {
    // console.log('Showing table for comment:', comment);
    
    if (!comment || !comment.rows || !comment.rows.length) {
      console.error('No rows to show for comment:', comment);
      return;
    }

    // CL(comment);
    $scope.uoup_chairs_refused_selected_comment = comment;

    // Update the filtered data with all rows that have this comment
    $scope.filteredNagruzka = comment.rows;
    $scope.viewState = 'table';
    
    // Force table redraw
    $scope.$evalAsync(() => {
      if ($scope.dtInstance && $scope.dtInstance.rerender) {
        $scope.dtInstance.rerender();
      }
    });
  };

  $scope.showChairs = function() {
    $scope.viewState = 'chairs';
    $scope.filteredNagruzka = angular.copy($scope.allNagruzka);
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
      null
  ];

  // $scope.columns = columns;

  $scope.dtOptions = DTOptionsBuilder //.fromSource('data.json')
    .newOptions()
    .withOption('order', [0, 'asc'])
    .withOption('pageLength', 25)
    .withOption('responsive', true)
    .withOption('stateSave', true)

    .withOption('stateSaveCallback', function(settings, data) {
        const path = $location.path();
        const storageKey = 'DataTables_Table_uoup_chairs_refused_' + path.replace(/\//g, '_');
        localStorage.setItem(storageKey, JSON.stringify(data));
    })
    .withOption('stateLoadCallback', function(settings) {
        const path = $location.path();
        const storageKey = 'DataTables_Table_uoup_chairs_refused_' + path.replace(/\//g, '_');
        const saved = localStorage.getItem(storageKey);
        return saved ? JSON.parse(saved) : null;
    })

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
        createCustomFilters('DataTables_Table_uoup_chairs_refused', table, columns, $scope);
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
      createCustomFilters('DataTables_Table_uoup_chairs_refused', table, columns, $scope);

      // Сброс и пересоздание фильтров при изменении видимости столбцов
      table.on('column-visibility.dt', function() {
        createCustomFilters('DataTables_Table_uoup_chairs_refused', table, columns, $scope);
      });
    }
  });


  // "Выполнено" в "Отказе кафедр"
  $scope.UOUPDoneRefuseBulk = function()
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
            .then(function (message) 
            {  // да

              // Формируем массив промисов
              var promises = $scope.filteredNagruzka.map(function(nagruzka_row) {
                
                // Возвращаем $http запрос
                return $http({
                  url: 'ajax/post/uoup_done_refused.php', 
                  method: 'POST', 
                  data: {
                    load_base_UID2: nagruzka_row.base_uid2, 
                    chair_id: nagruzka_row.chair_id, 
                    chair_name: nagruzka_row.chair_name, 
                    zavkaf_fio: nagruzka_row.zavkaf_fio, 
                    action: 'Администратор УОУП выполнил отказ кафедры от нагрузки', 
                    message: message
                  }
                })
                .then(function(data) {
                  if (data.data.result == 'success') {
                    nagruzka_row.status = 'done_refused';
                  } else {
                    toastr.error("Ошибка");
                  }
                  
                  // Прокидываем результат дальше
                  return data; 
                });
              });

              // Ждем завершения всех запросов перед редиректом
              $q.all(promises)
                .then(function(results) {
                  // Выполнится только когда сервер ответит на все отправленные запросы
                  toastr.success("Данные сохранены");
                  window.location = "/uoup_chairs_refused";
                })
                .catch(function(error) {
                  // Выполнится, если сервер вернет ошибку (например, 500 Internal Server Error)
                  toastr.error("Произошла ошибка при отправке запросов");
                });

              /*
              $scope.filteredNagruzka.forEach(function(nagruzka_row)
              {
                $http({url: 'ajax/post/uoup_done_refused.php', method: 'POST', data: {load_base_UID2: nagruzka_row.base_uid2, chair_id: nagruzka_row.chair_id, chair_name: nagruzka_row.chair_name, zavkaf_fio: nagruzka_row.zavkaf_fio, action: 'Администратор УОУП выполнил отказ кафедры от нагрузки', message: message}})
                .then(function(data)
                {
                  if (data.data.result == 'success')
                  {
                    nagruzka_row.status = 'done_refused';
                  }
                  else
                  {
                    toastr.error("Ошибка");
                  }
                });
              });
              
              toastr.success("Данные сохранены");

              $timeout(function() {
                window.location = "/uoup_chairs_refused";
              }, 1000);
              */
            })
            .catch(function dialogCloseErrorCallback(reason) {
                    // ngDialog v1.4.0 throws an exception, when closing the dialog with reason “undefined”.
            });

  }

  $scope.UOUPCancelRefuseBulk = function()
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
            .then(function (message) 
            {
              // да

              // Собираем все запросы в массив промисов
              var promises = $scope.filteredNagruzka.map(function(nagruzka_row) {
                
                // Обязательно возвращаем $http, чтобы он попал в массив promises
                return $http({
                  url: 'ajax/post/uoup_cancel.php', 
                  method: 'POST', 
                  data: {
                    load_base_UID2: nagruzka_row.base_uid2, 
                    chair_id: nagruzka_row.chair_id, 
                    chair_name: nagruzka_row.chair_name, 
                    zavkaf_fio: nagruzka_row.zavkaf_fio, 
                    action: 'Администратор УОУП отклонил отказ кафедры от нагрузки', 
                    message: message
                  }
                })
                .then(function(data) {
                  if (data.data.result == 'success') {
                    nagruzka_row.status = 'initial';
                  } else {
                    toastr.error("Ошибка");
                  }
                  
                  // Возвращаем данные для поддержания цепочки промисов
                  return data; 
                });
              });

              // Дожидаемся выполнения всех отправленных запросов
              $q.all(promises)
                .then(function(results) {
                  // Этот код сработает только после получения ответов от всех $http запросов
                  toastr.success("Данные сохранены");
                  window.location = "/uoup_chairs_refused";
                })
                .catch(function(error) {
                  // Обработка случая, если сервер вернул ошибку на один из запросов
                  toastr.error("Произошла системная ошибка при отправке запросов");
                });

              /*
              $scope.filteredNagruzka.forEach(function(nagruzka_row)
              {
                $http({url: 'ajax/post/uoup_cancel.php', method: 'POST', data: {load_base_UID2: nagruzka_row.base_uid2, chair_id: nagruzka_row.chair_id, chair_name: nagruzka_row.chair_name, zavkaf_fio: nagruzka_row.zavkaf_fio, action: 'Администратор УОУП отклонил отказ кафедры от нагрузки', message: message}})
                .then(function(data)
                {
                  if (data.data.result == 'success')
                  {
                    nagruzka_row.status = 'initial';
                  }
                  else
                  {
                    toastr.error("Ошибка");
                  }
                });
                
              });

              toastr.success("Данные сохранены");

              $timeout(function() {

                window.location = "/uoup_chairs_refused";
                
              }, 1000);

              */

            })
            .catch(function dialogCloseErrorCallback(reason) {
                    // ngDialog v1.4.0 throws an exception, when closing the dialog with reason “undefined”.
            });
    
  }


  // Отклонить отказ зав. каф. от нагрузки
  /*
  $scope.UOUPCancelRefuse = function(nagruzka_row)
  {
    

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

              $http({url: 'ajax/post/uoup_cancel.php', method: 'POST', data: {load_base_UID2: nagruzka_row.base_uid2, chair_id: nagruzka_row.chair_id, chair_name: nagruzka_row.chair_name, zavkaf_fio: nagruzka_row.zavkaf_fio, action: 'Администратор УОУП отклонил отказ кафедры от нагрузки', message: message}})
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
  */
  
})

.controller ('UOUPNagruzkaToChangeCtrl', function($rootScope, $scope, $http, $filter, DTOptionsBuilder, DTColumnBuilder, DTColumnDefBuilder, ngDialog, $templateCache, $resource, $timeout, uoup_nagruzka, system_mode, $location, $q) 
{
  CL('UOUPNagruzkaToChangeCtrl');

  $rootScope.page = 'uoup_nagruzka_to_change';
  $scope.system_mode = system_mode.data.mode; 

  if ($scope.system_mode === 'export_to_galaktika') 
  {
    CL("Режим работы Выгрузка в Галактику, поэтому пусто");
    return;
  }

  $templateCache.put('confirm_delete', '<p>Вы уверены, что хотите удалить?</p>\
              <div class="ngdialog-buttons">\
                  <button type="button" class="ngdialog-button ngdialog-button-secondary" ng-click="closeThisDialog(0)">Нет</button>\
                  <button type="button" class="ngdialog-button ngdialog-button-primary" ng-click="confirm(1)">Да</button>\
              </div>');
  
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

  /*
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

  */

  function buildAdminChangeChairs(rows) {
    if (!Array.isArray(rows) || !rows.length) return [];

    const departments = {};

    rows.forEach(function(row) {
      const deptName = row.department_name || 'Факультет не указан';
      const chairId = (row.chair_id || '').toString();
      const chairName = (row.chair_name || 'Кафедра не указана').replace(/<br\s*\/?>/gi, ', ');

      if (!departments[deptName]) {
        departments[deptName] = {
          department_name: deptName,
          chairs: {},
          count: 0,
          show_chairs: false
        };
      }

      if (!departments[deptName].chairs[chairId]) {
        departments[deptName].chairs[chairId] = {
          chair_id: chairId,
          chair_name: chairName,
          count: 0
        };
      }

      departments[deptName].chairs[chairId].count += 1;
      departments[deptName].count += 1;
    });

    // Convert to array and sort
    return Object.values(departments).map(dept => {
      dept.chairs = Object.values(dept.chairs).sort((a, b) => 
        a.chair_name.localeCompare(b.chair_name, 'ru')
      );
      return dept;
    }).sort((a, b) => 
      a.department_name.localeCompare(b.department_name, 'ru')
    );
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
    CL('applyFilters');

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

    CL($scope.filteredNagruzka);
  }

  function rerenderDataTable() {
    if ($scope.dtInstance && $scope.dtInstance.rerender) {
      $scope.dtInstance.rerender();
    }
  }


  $scope.NagruzkaToChangeToggleAdminShowDepartmentChairs = function(department)
  {
    department.show_chairs = !department.show_chairs;
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
    .withOption('stateSaveCallback', function(settings, data) {
        const path = $location.path();
        const storageKey = 'DataTables_Table_nagruzka_to_change_' + path.replace(/\//g, '_');
        localStorage.setItem(storageKey, JSON.stringify(data));
    })
    .withOption('stateLoadCallback', function(settings) {
        const path = $location.path();
        const storageKey = 'DataTables_Table_nagruzka_to_change_' + path.replace(/\//g, '_');
        const saved = localStorage.getItem(storageKey);
        return saved ? JSON.parse(saved) : null;
    })

    .withPaginationType('full_numbers')
    .withColVis()
    .withColVisOption('aiExclude', [0])
    .withOption('initComplete', function(settings, json) {
      // CL('initComplete');
      $scope.$apply(function() {
        $scope.isLoading = false;
      });

      const api = this.api();
      
      // Получаем legacy объект (jQuery с плагином) для ClearGreenTableFilters
      const legacyTable = $(api.table().node()).dataTable();
      const tempDtInstance = { dataTable: legacyTable };
      $scope.ClearGreenTableFilters(tempDtInstance, $scope.filter_distinct);

      createCustomFilters('DataTables_Table_nagruzka_to_change', api, columns, $scope);
      
      api.on('column-visibility.dt', function() {
        createCustomFilters('DataTables_Table_nagruzka_to_change', api, columns, $scope);
      });
    });

  // Возможность отключить сортировку и видимость столбцов по-умолчанию
  $scope.dtColumnDefs = [
    DTColumnDefBuilder.newColumnDef(0).notSortable(), // notVisible()
  ];

  // Отклонить запрос зав. каф. на изменение
  // комментарий обязателен
  $scope.UOUPDeclineToChangeBulk = function()
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

              // 1. Создаем массив промисов с помощью .map() вместо .forEach()
              var promises = $scope.filteredNagruzka.map(function(nagruzka_row) {
                
                // Возвращаем сам запрос $http в массив promises
                return $http({
                  url: 'ajax/post/uoup_cancel.php', 
                  method: 'POST', 
                  data: {
                    load_base_UID2: nagruzka_row.base_uid2, 
                    chair_id: nagruzka_row.chair_id, 
                    chair_name: nagruzka_row.chair_name, 
                    zavkaf_fio: nagruzka_row.zavkaf_fio, 
                    action: 'Админ УОУП отклонил запрос кафедры на внесение изменений', 
                    message: message
                  }
                })
                .then(function(response) {
                  if (response.data.result == 'success') {
                    nagruzka_row.status = 'initial';
                  } else {
                    toastr.error("Ошибка");
                  }
                  // Возвращаем response, чтобы $q.all корректно отработал цепочку
                  return response; 
                });
              });

              // 2. Ждем выполнения всех промисов (запросов)
              $q.all(promises)
                .then(function(results) {
                  // Этот блок выполнится ТОЛЬКО после того, как все запросы будут успешно завершены
                  toastr.success("Данные сохранены");
                  window.location = "/uoup_nagruzka_to_change";
                })
                .catch(function(error) {
                  // Если хотя бы один запрос вернет ошибку сервера (например, 500), попадем сюда
                  toastr.error("Произошла ошибка при отправке запросов");
                });

              /*

              $scope.filteredNagruzka.forEach(function(nagruzka_row)
              {
                $http({url: 'ajax/post/uoup_cancel.php', method: 'POST', data: {load_base_UID2: nagruzka_row.base_uid2, chair_id: nagruzka_row.chair_id, chair_name: nagruzka_row.chair_name, zavkaf_fio: nagruzka_row.zavkaf_fio, action: 'Админ УОУП отклонил запрос кафедры на внесение изменений', message: message}})
                .then(function(response)
                {
                  if (response.data.result == 'success')
                  {
                    nagruzka_row.status = 'initial';
                  }
                  else
                  {
                    toastr.error("Ошибка");
                  }
                });
              });

              toastr.success("Данные сохранены");
              
              $timeout(function() {
                window.location = "/uoup_nagruzka_to_change";
              }, 1000);

              */

            })
            .catch(function dialogCloseErrorCallback(reason) {
                    // ngDialog v1.4.0 throws an exception, when closing the dialog with reason “undefined”.
            });

  }

  // Выполнить запрос зав. каф. на изменение
  // комментарий НЕ обязателен
  $scope.UOUPDoneToChangeBulk = function()
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
            .then(function (message) 
            {  // да

              // Собираем все запросы в массив промисов
              var promises = $scope.filteredNagruzka.map(function(nagruzka_row) {
                
                // Возвращаем $http запрос в массив
                return $http({
                  url: 'ajax/post/uoup_done_change.php', 
                  method: 'POST', 
                  data: {
                    load_base_UID2: nagruzka_row.base_uid2, 
                    chair_id: nagruzka_row.chair_id, 
                    chair_name: nagruzka_row.chair_name, 
                    zavkaf_fio: nagruzka_row.zavkaf_fio, 
                    message: message
                  }
                })
                .then(function(data) {
                  if (data.data.result == 'success') {
                    // nagruzka_row.status = 'cancelling_to_change'; // не понял этого, в бэкенд такой статус не пишется
                    nagruzka_row.status = 'done_change';
                  } else {
                    toastr.error("Ошибка");
                  }
                  
                  // Возвращаем результат для цепочки промисов
                  return data; 
                });
              });

              // Дожидаемся выполнения всех отправленных запросов
              $q.all(promises)
                .then(function(results) {
                  // Выполнится только после получения ответов от всех запросов
                  toastr.success("Данные сохранены");
                  window.location = "/uoup_nagruzka_to_change";
                })
                .catch(function(error) {
                  // На случай системной ошибки сервера (например, 500)
                  toastr.error("Произошла системная ошибка при отправке запросов");
                });

                
              /*
              $scope.filteredNagruzka.forEach(function(nagruzka_row)
              {
                $http({url: 'ajax/post/uoup_done_change.php', method: 'POST', data: {load_base_UID2: nagruzka_row.base_uid2, chair_id: nagruzka_row.chair_id, chair_name: nagruzka_row.chair_name, zavkaf_fio: nagruzka_row.zavkaf_fio, message: message}})
                .then(function(data)
                {
                  if (data.data.result == 'success')
                  {
                    // nagruzka_row.status = 'cancelling_to_change'; // не понял этого, в бэкенд такой статус не пишется
                    nagruzka_row.status = 'done_change';
                  }
                  else
                  {
                    toastr.error("Ошибка");
                  }
                });
              });
              
              toastr.success("Данные сохранены");

              $timeout(function() {
                window.location = "/uoup_nagruzka_to_change";
              }, 1000);

              */

            })
            .catch(function dialogCloseErrorCallback(reason) {
                    // ngDialog v1.4.0 throws an exception, when closing the dialog with reason “undefined”.
            });

  }


  /*
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

              $http({url: 'ajax/post/uoup_cancel.php', method: 'POST', data: {load_base_UID2: nagruzka_row.base_uid2, chair_id: nagruzka_row.chair_id, chair_name: nagruzka_row.chair_name, zavkaf_fio: nagruzka_row.zavkaf_fio, action: 'Админ УОУП отклонил запрос кафедры на внесение изменений', message: message}})
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
  */
  
})

.controller ('SotrudnikiCtrl', function($rootScope, $scope, $http, ngDialog, $templateCache, DTOptionsBuilder, DTColumnBuilder, DTColumnDefBuilder, $resource, system_mode, sotrudniki_selected_chair_id) 
{
  CL('SotrudnikiCtrl');

  if (c_roles.zavkaf && system_mode.data.mode == 'mode_closed') 
  {
    window.location = '#/system_closed';
  }

  $rootScope.page = 'sotrudniki';
  $scope.$_sotrudnik_types = $_sotrudnik_types;
  CL(sotrudniki_selected_chair_id);
  $scope.system_mode = system_mode.data.mode; 
  $scope.sotrudniki = [];
  $scope.chairs = [];

  $scope.data = {};

  $scope.dtOptions = DTOptionsBuilder
    .newOptions()
    .withPaginationType('full_numbers')
    .withLanguage({
        "loadingRecords": "Загрузка...",
        "processing": "Обработка..."
    })
    .withOption('pageLength', 100)  // строк на страницу
    .withButtons([
          {
            extend: 'excel',
            text: 'Excel', // Текст на самой кнопке
            filename: "Сотрудники", // Имя файла
            title: "Сотрудники", // Заголовок на первой строке листа
            exportOptions: 
            {
              columns: function (idx, data, node) {
                  // Проверяем, что столбец видимый и не первый
                  const column = $scope.dtInstance.dataTable.fnSettings().aoColumns[idx];
                  return column.bVisible && idx !== 0;
              },
              /*
              format: 
              {
                body: function (data, column, row, node) 
                {
                  // console.log('Arguments:', arguments);

                  // Для определённых столбцов своя обработка
                  if (row === 14)
                  {
                    // Клонируем узел, чтобы не менять оригинал
                    const clone = node.cloneNode(true);
                    
                    // Удаляем все комментарии из клона
                    const walker = document.createTreeWalker(
                        clone,
                        NodeFilter.SHOW_COMMENT,
                        {
                            acceptNode: function(node) {
                                return NodeFilter.FILTER_ACCEPT;
                            }
                        }
                    );
                    
                    const commentsToRemove = [];
                    while (walker.nextNode()) {
                        commentsToRemove.push(walker.currentNode);
                    }
                    commentsToRemove.forEach(comment => comment.remove());
                    
                    // Получаем текст без комментариев
                    let text = clone.textContent || clone.innerText || '';
                    
                    // Очищаем от лишних пробелов и переносов строк
                    text = text.replace(/\s+/g, ' ').trim();
                    
                    // Разделяем лекторов (если нужно)
                    text = text.replace(/\)\s+/, '); ');

                    // Заменяем "[не распределено]" на "не распределено"
                    text = text.replace(/\[не распределено\]/g, 'не распределено');
                    
                    return text;

                  }
                  else
                  { 
                    if (!data || typeof data !== 'string') return data || '';

                    // Создаем временный элемент
                    const temp = document.createElement('div');
                    temp.innerHTML = data;

                    // Заменяем <br> на перенос строки перед получением textContent
                    // Вариант 1: через replace
                    const htmlWithBr = temp.innerHTML;
                    temp.innerHTML = htmlWithBr.replace(/<br\s*\/?>/gi, ', ');

                    return temp.textContent || temp.innerText || '';
                  }
                  

                  // Проверяем тип данных
                  // if (data === null || data === undefined) {
                  //     return '';
                  // }
                  
                  // // Если это строка и содержит HTML
                  // if (typeof data === 'string' && (data.indexOf('<') !== -1 || data.indexOf('&') !== -1)) {
                  //     // Создаем временный элемент для удаления HTML
                  //     const temp = document.createElement('div');
                  //     temp.innerHTML = data;
                  //     return temp.textContent || temp.innerText || '';
                  // }
                  
                  // // Если обычная строка или число
                  // return data;
                }
              }
              */
            }
          }
      ]);

  $scope.dtColumnDefs = [];

  $scope.onSotrudnikiTableInstance = function(dtInstance) 
  {
    $scope.dtInstance = dtInstance; 
  }

  // $http({url: 'ajax/get/sotrudnik.php', method: 'GET'}).then(function(response)
  // {
  //   $scope.sotrudniki = response.data;
  // });

  var sotrudniki_chair_id_param = '';

  if (c_roles.uoup)
  {
    sotrudniki_chair_id_param = `?chair_id=${sotrudniki_selected_chair_id}`;
  }

  $http({url: 'ajax/get/chair_sotrudniki.php' + sotrudniki_chair_id_param, method: 'GET'}).then(function(response)
  {
    $scope.sotrudniki = response.data.sotrudniki;
    $scope.data.sotrudnik_chair_nagruzka_visibility = response.data.sotrudnik_chair_nagruzka_visibility;
    $scope.sotrudniki_selected_chair_name = response.data.chair_name;
  });

  $scope.saveSotrudnik = function(sotrudnik)
  {
    $http({url: 'ajax/post/select_sotrudnik.php', method: 'POST', data: sotrudnik})
      .then(function(response)
      {
        if (response.data.result == 'success')
        {
          toastr.success("Данные сохранены");
        }
        else
        {
          toastr.error("Ошибка");
        }
      });
  };

  $scope.navigateToNagruzka = function(person) 
  {
    CL('navigateToNagruzka');
    // Only navigate if the person has a lecturer_uid
    if (person.lecturer_uid && person.amount_sum > 0) 
    {
      var chairId;

      if (c_roles.zavkaf)
      {
        // Get the current chair ID (c_chair_id is a global variable)
        chairId = c_chair_id || '';
      }
      else if (c_roles.uoup)
      {
        chairId = sotrudniki_selected_chair_id;
      }
      // Navigate to the nagruzka page filtered by this lecturer
      window.location.href = `#/nagruzka/all/${chairId}/${person.lecturer_uid}`;
    }
  };

  $scope.SelectSotrudnik = function(person)
  {
    CL('SelectSotrudnik');

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


  $scope.SaveSotrudnikNagruzkaVisibility = function()
  {
    $http({url: 'ajax/post/save_sotrudnik_chair_nagruzka_visibility.php', method: 'POST', data: {visible: $scope.data.sotrudnik_chair_nagruzka_visibility}})
              .then(function(data)
              {
                if (data.data.result == 'success')
                {
                  toastr.success("Данные сохранены");
                }
                else
                {
                  toastr.error("Ошибка");
                }
              });
  }
  
})

.controller ('NagruzkaColumnsCtrl', function($rootScope, $scope, $http, column_order)
{
  CL('NagruzkaColumnsCtrl');
  $rootScope.page = 'nagruzka_columns';

  const defaultColumns = [
    { name: 'department_name', label: 'Факультет' },
    { name: 'Abbr', label: 'Аббр' },
    { name: 'discipline_name', label: 'Дисциплина' },
    { name: 'group_name', label: 'Группа' },
    { name: 'education_level', label: 'Уровень образования' },
    { name: 'napravlenie', label: 'Направление подготовки' },
    { name: 'language', label: 'Язык программы' },
    { name: 'form_obuchenia', label: 'Форма обучения' },
    { name: 'UID_Semester', label: 'Семестр' },
    { name: 'StudentAmount', label: 'Количество студентов' },
    { name: 'kind_of_work', label: 'Вид работ' },
    { name: 'napravlennost', label: 'Профиль/направленность программы' },
    { name: 'UID_Course', label: 'Курс' },
    { name: 'amount', label: 'Количество часов' },
    { name: 'lecturer_fio', label: 'Преподаватель' },
    { name: 'comment_to_admin', label: 'Комментарий' }
  ];

  $scope.columns = angular.copy(defaultColumns);

  if (column_order.data && column_order.data.columns)
  {
    $scope.columns = column_order.data.columns;
  }

  $scope.saveColumnOrder = function()
  {
    $http({
      url: 'ajax/post/save_nagruzka_column_order.php',
      method: 'POST',
      data: { columns: $scope.columns }
    }).then(function(response)
    {
      if (response.data.result == 'success')
      {
        toastr.success("Порядок столбцов сохранен");
      }
      else
      {
        toastr.error("Ошибка сохранения");
      }
    });
  };

  $scope.resetToDefault = function()
  {
    $scope.columns = angular.copy(defaultColumns);
  };
})

/*
.controller ('TestCtrl', function($rootScope, $scope)
{
  CL('TestCtrl');
  $rootScope.page = 'test';
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
        // angular.forEach($scope.persons, function(person)
        // {
        //   if (person.type == 'sotrudnik')
        //   {
        //     person.selected = true;
        //   }
        // });
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
      CL('SelectSotrudnik');

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

    // $scope.navigateToNagruzka = function(person) 
    // {
    //   CL('navigateToNagruzka');
    //   // Only navigate if the person has a lecturer_uid
    //   if (person.lecturer_uid && person.amount_sum > 0) {
    //     // Get the current chair ID (c_chair_id is a global variable)
    //     const chairId = c_chair_id || '';
    //     // Navigate to the nagruzka page filtered by this lecturer
    //     window.location.href = `#/nagruzka/discipline/${chairId}/${person.lecturer_uid}`;
    //   }
    // };

})

*/

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

.controller ('KSROCtrl', function($templateCache, $scope, $rootScope, ngDialog, $http, $resource, DTOptionsBuilder, DTColumnDefBuilder, system_mode, ksro_selected_chair_id, ksro_selected_lecturer_uid, chairs_sprav, $location)
{
  CL('KSROCtrl');

  $scope.c_login = c_login;
  $rootScope.page = 'ksro';
  $scope.$_languages = $_languages;
  $scope.system_mode = system_mode.data.mode; 
  $scope.nagruzka_selected_lecturer_uid = ksro_selected_lecturer_uid; // Store the lecturer_uid from the route
  $scope.chair_id = $scope.ksro_selected_chair_id = ksro_selected_chair_id;
  $scope.chairs_sprav = chairs_sprav.data;
  $scope.$_nagruzka_types = $_nagruzka_types;
  $scope.nagruzka_stat = {};
  CL(ksro_selected_lecturer_uid);
  CL(ksro_selected_chair_id);

  if (c_roles.sotrudnik)
  {
    $scope._chairs_ids = c_sotrudnik_chairs_ids;
    $scope._lecturer_uids = c_sotrudnik_lecturer_uids;
    $scope._chairs_titles = c_sotrudnik_chairs_titles;
  }

  CL($scope.system_mode);
 
  $scope.c_roles = c_roles;

  $scope.GetNagruzkaTypesRowLink = function(nagruzka_type, chair_id, lecturer_uid)
  {
    return GetNagruzkaTypesRowLink($scope, nagruzka_type, chair_id, lecturer_uid);
  }

  $scope.KSROCtrlUpdateNagruzkaStat = function(nagr_type, chair_id, lecturer_uid, only_stat)
  {
    UpdateNagruzkaStat($http, $scope, nagr_type, chair_id, lecturer_uid, only_stat);
  }

  $scope.KSROCtrlUpdateNagruzkaStat('discipline', ksro_selected_chair_id, ksro_selected_lecturer_uid, true);
  $scope.KSROCtrlUpdateNagruzkaStat('ruk_vkr', ksro_selected_chair_id, ksro_selected_lecturer_uid, true);
  $scope.KSROCtrlUpdateNagruzkaStat('ruk_kurs', ksro_selected_chair_id, ksro_selected_lecturer_uid, true);
  $scope.KSROCtrlUpdateNagruzkaStat('ruk_practice', ksro_selected_chair_id, ksro_selected_lecturer_uid, true);
  $scope.KSROCtrlUpdateNagruzkaStat('ksro', ksro_selected_chair_id, ksro_selected_lecturer_uid, false);
  $scope.KSROCtrlUpdateNagruzkaStat('gia', ksro_selected_chair_id, ksro_selected_lecturer_uid, true);
  $scope.KSROCtrlUpdateNagruzkaStat('aspirant', ksro_selected_chair_id, ksro_selected_lecturer_uid, true);


  CL($scope.nagruzka_stat);



  // let url = 'ajax/get/ksro.php?chair_id=' + (ksro_selected_chair_id ? ksro_selected_chair_id : c_chair_id);
  // if (ksro_selected_lecturer_uid) 
  // {
  //   url += '&lecturer_uid=' + encodeURIComponent(ksro_selected_lecturer_uid);
  // }

  // $scope.ksro = $resource(url).query();

  // $http({url: url, method: 'GET'})
  //     .then(function (response) 
  //     {
  //       if (response.data)
  //       {
  //         $scope.ksro = response.data.nagruzka;
  //         $scope.isLoading = false;

  //         // Если ограничены одним преподом, то нужно взять его ФИО (из первой же нагрузки)
  //         if ($scope.ksro_selected_lecturer_uid)
  //         {
  //           $scope.ksro_lecturer_fio = response.data.lecturer_fio;
  //         }
  //       }
  //     })

  $scope.data = {show_edit_ksro: false, edit_ksro_index: undefined};

  $scope.edit_ksro = {};

  const columns = [
    {
      name: 'fio',
      type: 'input',
      bRegex: false,
    },
    {
      name: 'dolzhnost',
      type: 'select',
      bRegex: false,
    },
    {
      name: 'stavka',
      type: null,
      bRegex: false,
    },
    {
      name: 'language',
      type: 'select',
      bRegex: false,
    },
    {
      name: 'ik_osen',
      type: null,
      bRegex: false,
    },
    {
      name: 'ik_vesna',
      type: null,
      bRegex: false,
    },
    {
      name: 'ksro_osen',
      type: null,
      bRegex: false,
    },
    {
      name: 'ksro_vesna',
      type: null,
      bRegex: false,
    }
  ];

  $scope.dtOptions = DTOptionsBuilder
    .newOptions()
    .withOption('stateSave', true)
    .withOption('stateSaveCallback', function(settings, data) {
        const path = $location.path();
        const storageKey = 'DataTables_Table_ksro_' + path.replace(/\//g, '_');
        localStorage.setItem(storageKey, JSON.stringify(data));
    })
    .withOption('stateLoadCallback', function(settings) {
        const path = $location.path();
        const storageKey = 'DataTables_Table_ksro_' + path.replace(/\//g, '_');
        const saved = localStorage.getItem(storageKey);
        return saved ? JSON.parse(saved) : null;
    })
    .withButtons([
          {
            extend: 'excel',
            text: 'Excel', // Текст на самой кнопке
            filename: "ИК/КСРО", // Имя файла
            title: "ИК/КСРО" // Заголовок на первой строке листа
          }
      ])
    .withPaginationType('full_numbers')
    .withLanguage({
        "loadingRecords": "Загрузка...",
        "processing": "Обработка..."
    })
    .withOption('initComplete', function(settings, json) {
      const api = this.api();
      createCustomFilters('DataTables_Table_ksro', api, columns, $scope);
    });

  $scope.dtColumnDefs = [];

  $scope.onKSROTableInstance = function(dtInstance) 
  {
    CL('onKSROTableInstance');
    $scope.dtInstance = dtInstance;
  };

  $scope.KSROSelectedLecturer = function(data)
  {
    CL('KSROSelectedLecturer');
    
    // CL(data.originalObject);
    // CL(lecturer_row);

    if (!isEmpty(data))
    {
      $scope.edit_ksro = {};
      // $scope.edit_ksro.id = null;
      $scope.edit_ksro.lecturer_fio = data.originalObject.fio;
      $scope.edit_ksro.uid = data.originalObject.lecturer_uid;
      $scope.edit_ksro.lecturer_person_id = data.originalObject.person_id;
      $scope.edit_ksro.login = data.originalObject.lecturer_login;
      $scope.edit_ksro.dolzhnost = data.originalObject.dolzhnost;
      $scope.edit_ksro.stavka = parseFloat(data.originalObject.stavka.replace(',', '.'));
    }

    // CL($scope.edit_ksro);
  }

  $scope.showAddKSRO = function()
  {
    $scope.data.show_edit_ksro = true;
    $scope.data.edit_ksro_index = undefined;
  }

  $scope.editKSRO = function(index)
  {
    if ($scope.MayEditKSRO())
    {
      // CL(index);
      const row = $scope.ksro[index];
      $scope.edit_ksro = angular.copy(row);
      $scope.data.edit_ksro_index = index;
      $scope.data.show_edit_ksro = true;
    }
  }

  $scope.cancelKSRO = function()
  {
    $scope.data.show_edit_ksro = false;
    $scope.data.edit_ksro_index = undefined;
    $scope.edit_ksro = {};
  }

  $scope.deleteKSRO = function(row)
  {
    $http({url: 'ajax/post/delete_ksro.php', method: 'POST', data: {ids: row.ids}})
        .then(function(response)
        {
          if (response.data.result == 'success')
          {
            // deleteByColumn($scope.ksro, 'id', row.id);
            $scope.ksro.splice($scope.data.edit_ksro_index, 1);
            $scope.cancelKSRO();
            toastr.success("Данные удалены");
          }
          else
          {
            toastr.error("Ошибка");
          }
        });
  }


  $scope.saveKSRO = function()
  {
    // Проверим правильность формы
    var valid = true;

    if (isEmpty($scope.edit_ksro.UID_Language))
    {
      toastr.error("Выберите язык");
      return;
    }

    // Проверяем, что в массиве нет такого же сотрудника с таким же языком
    const existingPerson = $scope.ksro.find(
      (person) => person.lecturer_person_id === $scope.edit_ksro.lecturer_person_id &&
        person.UID_Language === $scope.edit_ksro.UID_Language &&
        (!isEmpty($scope.edit_ksro.id) && person.id !== $scope.edit_ksro.id || isEmpty($scope.edit_ksro.id))
    );
    
    if (existingPerson) {
      toastr.error("Сотрудник с таким же именем и языком уже существует");
      return;
    }

    // Проверка ограничения по 16 часам на ставку для индивидуальных консультаций
    const lecturerStavka = parseFloat($scope.edit_ksro.stavka) || 0;
    const maxHoursPerSemester = 16 * lecturerStavka;
    
    // Проверяем лимиты ИК отдельно для каждого семестра
    const existingIK = $scope.ksro
      .filter(person => person.lecturer_person_id === $scope.edit_ksro.lecturer_person_id && person.id !== $scope.edit_ksro.id);
    
    // Осенний семестр
    const totalAutumnIK = existingIK
      .reduce((total, person) => total + (parseFloat(person.ik_osen) || 0), 0);
    const totalAutumnIKWithCurrent = totalAutumnIK + (parseFloat($scope.edit_ksro.ik_osen) || 0);
    
    if (totalAutumnIKWithCurrent > maxHoursPerSemester) {
      toastr.error(`Превышен лимит ИК на осенний семестр. Максимально ${maxHoursPerSemester} часов на ставку ${lecturerStavka}. Текущая сумма: ${totalAutumnIKWithCurrent} часов.`);
      return;
    }
    
    // Весенний семестр
    const totalSpringIK = existingIK
      .reduce((total, person) => total + (parseFloat(person.ik_vesna) || 0), 0);
    const totalSpringIKWithCurrent = totalSpringIK + (parseFloat($scope.edit_ksro.ik_vesna) || 0);
    
    if (totalSpringIKWithCurrent > maxHoursPerSemester) {
      toastr.error(`Превышен лимит ИК на весенний семестр. Максимально ${maxHoursPerSemester} часов на ставку ${lecturerStavka}. Текущая сумма: ${totalSpringIKWithCurrent} часов.`);
      return;
    }

    // Проверка ограничения для КСРО
    let maxKSROHoursPerSemester;
    // if (lecturerStavka >= 0.5) 
    {
      // Если ставка 0,5 и более - пропорционально как ИК
      maxKSROHoursPerSemester = 32 * lecturerStavka;
    } 
    // else 
    // {
    //   // Если ставка менее 0,5 - просто не более 16 часов в семестр
    //   maxKSROHoursPerSemester = 16;
    // }
    
    // Проверяем лимиты КСРО отдельно для каждого семестра
    const existingKSRO = $scope.ksro
      .filter(person => person.lecturer_person_id === $scope.edit_ksro.lecturer_person_id && person.id !== $scope.edit_ksro.id);
    
    // Осенний семестр
    const totalAutumnKSRO = existingKSRO
      .reduce((total, person) => total + (parseFloat(person.ksro_osen) || 0), 0);
    const totalAutumnKSROWithCurrent = totalAutumnKSRO + (parseFloat($scope.edit_ksro.ksro_osen) || 0);
    
    if (totalAutumnKSROWithCurrent > maxKSROHoursPerSemester) {
      toastr.error(`Превышен лимит КСРО на осенний семестр. Максимально ${maxKSROHoursPerSemester} часов. Текущая сумма: ${totalAutumnKSROWithCurrent} часов.`);
      return;
    }
    
    // Весенний семестр
    const totalSpringKSRO = existingKSRO
      .reduce((total, person) => total + (parseFloat(person.ksro_vesna) || 0), 0);
    const totalSpringKSROWithCurrent = totalSpringKSRO + (parseFloat($scope.edit_ksro.ksro_vesna) || 0);
    
    if (totalSpringKSROWithCurrent > maxKSROHoursPerSemester) {
      toastr.error(`Превышен лимит КСРО на весенний семестр. Максимально ${maxKSROHoursPerSemester} часов. Текущая сумма: ${totalSpringKSROWithCurrent} часов.`);
      return;
    }

    if (valid)
    {
      $http({url: 'ajax/post/save_ksro.php', method: 'POST', data: $scope.edit_ksro})
        .then(function(response)
        {
          if (response.data.result == 'success')
          {
            toastr.success("Данные сохранены");
            
            $scope.data.show_edit_ksro = false;

            // редактирование
            if ($scope.data.edit_ksro_index !== undefined)
            {
              // const ind = findIndByColumn($scope.ksro, 'id', $scope.edit_ksro.id);
              $scope.ksro[$scope.data.edit_ksro_index] = $scope.edit_ksro;
            }
            // добавление
            else
            {
              $scope.edit_ksro['ids'] = response.data.ids;
              $scope.ksro.push($scope.edit_ksro);
            }

            $scope.cancelKSRO();
          }
          else
          {
            toastr.error("Ошибка");
          }
        });
    }
  }

  $scope.MayEditKSRO = function()
  {
    // CL($scope.system_mode == 'mode_filling');
    return c_roles.zavkaf && $scope.system_mode == 'mode_filling';
  }

  $scope.GetKSROSum = function(param)
  {
    var sum = 0;

    if (param != 'itogo')
    {
      angular.forEach($scope.ksro, function(row)
      {
        if (Number.isFinite(parseFloat(row[param])))
        {
          sum += parseFloat(row[param]);
        }
        
      });
    }
    // itogo
    else
    {
      return $scope.GetKSROSum('ik_osen') + $scope.GetKSROSum('ik_vesna') + $scope.GetKSROSum('ksro_osen') + $scope.GetKSROSum('ksro_vesna');
    }

    return roundToTwo(sum);
  }

})

.controller('AspiranturaCtrl', function($templateCache, $scope, $rootScope, ngDialog, $http, $resource, DTOptionsBuilder, DTColumnDefBuilder, system_mode, aspirantura_selected_chair_id, aspirantura_selected_lecturer_uid, chairs_sprav, $location)
{
  CL('AspiranturaCtrl');

  $scope.c_login = c_login;
  $rootScope.page = 'aspirantura';
  $scope.$_languages = $_languages;
  $scope.system_mode = system_mode.data.mode; 
  $scope.nagruzka_selected_lecturer_uid = aspirantura_selected_lecturer_uid; // Store the lecturer_uid from the route
  $scope.chair_id = $scope.aspirantura_selected_chair_id = aspirantura_selected_chair_id;
  $scope.chairs_sprav = chairs_sprav.data;
  $scope.$_nagruzka_types = $_nagruzka_types;
  $scope.nagruzka_stat = {};

  if (c_roles.sotrudnik)
  {
    $scope._chairs_ids = c_sotrudnik_chairs_ids;
    $scope._lecturer_uids = c_sotrudnik_lecturer_uids;
    $scope._chairs_titles = c_sotrudnik_chairs_titles;
  }

  CL($scope.system_mode);
 
  $scope.c_roles = c_roles;

  $scope.ShowZayavkaTab = function(tab)
  {
    // if (!$scope.IsSelectedStageOpen()) return;

    // CL(tab);

    $scope.visible_tab = tab;

    if (tab == 'zayavka_form' || tab == 'zayavka_finance')
    {
      // $scope.LoadOstatki();
    }

    if (tab == 'zayavka_contents')
    {

    }
  }

  $scope.ShowZayavkaTab('aspirantura_itog_exam');


})



/* Аспирантура: вкладка "Нагрузка по кандидатским экзаменам" */

.component('aspiranturaKandExam', {
    templateUrl: 'aspirantura_kand_exam.tpl.html?' + getRandom(10000, 99999),
    // template: "abc",

    controller: function AspiranturaKandExamCtrl($scope, $rootScope, $timeout, $http, $templateCache, ngDialog, FileUploader, $filter)
    {
      CL('AspiranturaKandExamCtrl');
    }
})


/* Аспирантура: вкладка "Нагрузка по итоговому экзамену" */

.component('aspiranturaItogExam', {
    templateUrl: 'aspirantura_itog_exam.tpl.html?' + getRandom(10000, 99999),
    // Объявляем входящие параметры. '<' означает одностороннее связывание (one-way binding)
    bindings: {
        systemMode: '<' 
    },
    // template: "abc",

    controller: function AspiranturaItogExamCtrl($scope, $rootScope, $timeout, $http, $templateCache, ngDialog, FileUploader, $filter, $controller)
    {
      CL('AspiranturaItogExamCtrl');

      var $ctrl = this; // Сохраняем ссылку на контекст компонента

      $scope._nagruzka_type = 'aspirantura_itog_exam';


      // 1. Формируем объект с локальными зависимостями (locals).
        // AngularJS автоматически подтянет стандартные сервисы ($http, $cookies и т.д.),
        // поэтому сюда нужно передать ТОЛЬКО кастомные зависимости, 
        // которые NagruzkaCtrl не сможет найти самостоятельно в глобальном инжекторе.
        // Строго перечисляем ВСЕ кастомные зависимости, которые есть в сигнатуре NagruzkaCtrl
        $ctrl.$onInit = function() {
            CL('AspiranturaItogExamCtrl initialized');

            $scope._nagruzka_type = 'aspirantura_itog_exam';

            var locals = {
                $scope: $scope,
                nagruzka_type: $scope._nagruzka_type,
                
                // Передаем значение, которое пришло сверху из bindings
                system_mode: $ctrl.systemMode, 
                
                // Остальные заглушки или реальные данные
                nagruzka_selected_chair_id: null,
                lecturer_uid: null,
                nagruzka_stat: {},
                nagruzka: [],
                chairs_sprav: []
            };

            // Инициализируем родительский NagruzkaCtrl
            $controller('NagruzkaCtrl', locals);
        };

        // После этой строчки все методы и переменные, которые NagruzkaCtrl 
        // вешает на $scope, будут доступны внутри этого компонента!

    }
})


/* Аспирантура: вкладка "Руководство аспирантами" */

.component('aspiranturaRukAsp', {
    templateUrl: 'aspirantura_ruk_asp.tpl.html?' + getRandom(10000, 99999),
    // template: "abc",

    controller: function AspiranturaRukAspCtrl($scope, $rootScope, $timeout, $http, $templateCache, ngDialog, FileUploader, $filter)
    {
      CL('AspiranturaRukAspCtrl');
    }
})


/* Аспирантура: вкладка "Руководство соискателями" */

.component('aspiranturaRukSoiskatel', {
    templateUrl: 'aspirantura_ruk_soiskatel.tpl.html?' + getRandom(10000, 99999),
    // template: "abc",

    controller: function AspiranturaRukSoiskatelCtrl($scope, $rootScope, $timeout, $http, $templateCache, ngDialog, FileUploader, $filter)
    {
      CL('AspiranturaRukSoiskatelCtrl');
    }
})



// Add this with your other filters
.filter('formatFio', function() {
  return function(input) {
    if (!input) return '';
    
    // Split the full name into parts
    const parts = input.trim().split(/\s+/);
    
    if (parts.length === 0) return '';
    if (parts.length === 1) return parts[0]; // Just a last name
    
    // Get the last name (first part)
    const lastName = parts[0];
    
    // Process other parts to get initials
    const initials = parts.slice(1).map(part => {
      return part.charAt(0) + '.';
    }).join('');
    
    // \u00A0 - альтернатива &nbsp;
    return lastName + '\u00A0' + initials;
  };
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

// .filter('toFixed', function() {
//   return function(input, decimals) {
//     if (isNaN(input) || input === null || input === '') return input;

//     var num = Number(input);
//     var dec = Number(decimals) || 0;
//     var x = Math.pow(10, dec + 1);

//     return (num + (1 / x)).toFixed(dec);
//   };
// })

.filter('toFixed', function() {
    return function(input, precision) {
        if (input === null || isNaN(input)) return input;
        
        const num = parseFloat(input);
        const fixed = num.toFixed(precision);
        
        // If the decimal part is all zeros, return just the integer part
        if (num % 1 === 0) {
            return num.toString();
        }
        
        return fixed;
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

.filter('formatHours', function() {
  return function(value) {
    if (value === null || value === undefined || value === '' || value === 0) return '';
    
    // Convert to number if it's a string
    const num = parseFloat(value);
    if (isNaN(num) || num === 0) return ''; // Return empty string for NaN or zero
    
    // If it's an integer, return without decimals
    if (num % 1 === 0) {
      return num.toString();
    }
    
    // Otherwise, remove trailing zeros and return as string
    return num.toString().replace(/(\.0*|(?<=\.\d+?)0+)$/, '');
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
    if (arr[i][column] === value)
    {
      // CL(arr[i][column]);
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
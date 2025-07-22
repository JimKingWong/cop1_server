define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/turntablelog/index' + location.search,
                    add_url: 'user/turntablelog/add',
                    edit_url: 'user/turntablelog/edit',
                    del_url: 'user/turntablelog/del',
                    multi_url: 'user/turntablelog/multi',
                    import_url: 'user/turntablelog/import',
                    table: 'user_turntable_log',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'user.username', title: __('User.username'), operate: 'LIKE'},

                        {field: 'user_id', title: __('User_id')},
                        {field: 'type', title: __('Type'), searchList: {"silver":__('Type silver'),"golden":__('Type golden'),"diamond":__('Type diamond')}, formatter: Table.api.formatter.normal},
                        {field: 'turntable_id', title: __('Turntable_id')},
                        {field: 'today_bet', title: __('Today_bet')},
                        {field: 'money', title: __('Money'), operate:'BETWEEN'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});

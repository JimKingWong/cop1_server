define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/risklog/index' + location.search,
                    add_url: 'user/risklog/add',
                    edit_url: 'user/risklog/edit',
                    del_url: 'user/risklog/del',
                    multi_url: 'user/risklog/multi',
                    import_url: 'user/risklog/import',
                    table: 'risk_task_log',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {field: 'admin_id', title: __('Admin_id')},
                        {field: 'user_id', title: __('博主id')},
                        {field: 'user.username', title: __('User.username'), operate: 'LIKE'},
                        {field: 'method_intro', title: __('检测'), operate: 'LIKE'},
                        {field: 'result_intro', title: __('结果'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.status},
                        {field: 'is_pass', title: __('Is_pass'), searchList: {"0":__('Is_pass 0'),"1":__('Is_pass 1')}, formatter: Table.api.formatter.normal},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
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

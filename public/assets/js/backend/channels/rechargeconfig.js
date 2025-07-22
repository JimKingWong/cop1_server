define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'channels/rechargeconfig/index' + location.search,
                    add_url: 'channels/rechargeconfig/add',
                    edit_url: 'channels/rechargeconfig/edit',
                    del_url: 'channels/rechargeconfig/del',
                    multi_url: 'channels/rechargeconfig/multi',
                    import_url: 'channels/rechargeconfig/import',
                    table: 'recharge_config',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'min_money', title: __('Min_money'), operate:'BETWEEN'},
                        {field: 'max_money', title: __('Max_money'), operate:'BETWEEN'},
                        {field: 'typing_amount', title: __('Typing_amount'), operate:'BETWEEN'},
                        {field: 'gift_amount', title: __('Gift_amount'), operate:'BETWEEN'},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.toggle},
                        {field: 'weigh', title: __('Weigh'), operate: false, sortable: true},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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

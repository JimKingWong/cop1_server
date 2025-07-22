define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'reward/vip/index' + location.search,
                    add_url: 'reward/vip/add',
                    edit_url: 'reward/vip/edit',
                    del_url: 'reward/vip/del',
                    multi_url: 'reward/vip/multi',
                    import_url: 'reward/vip/import',
                    table: 'vip_config',
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
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'level', title: __('Level')},
                        {field: 'image', title: __('Image'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'level_reward', title: __('Level_reward'), operate:'BETWEEN'},
                        {field: 'recharge_amount', title: __('Recharge_amount'), operate:'BETWEEN'},
                        {field: 'withdraw_times', title: __('Withdraw_times')},
                        {field: 'withdraw_amount', title: __('Withdraw_amount'), operate:'BETWEEN'},
                        {field: 'bet_amount', title: __('Bet_amount'), operate:'BETWEEN'},
                        {field: 'week_reward', title: __('Week_reward'), operate:'BETWEEN'},
                        {field: 'month_reward', title: __('Month_reward'), operate:'BETWEEN'},
                        {field: 'withdraw_amount_day', title: __('Withdraw_amount_day'), operate:'BETWEEN'},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
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

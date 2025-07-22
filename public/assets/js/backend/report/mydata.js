define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'report/mydata/index' + location.search,
                    add_url: 'report/mydata/add',
                    edit_url: 'report/mydata/edit',
                    del_url: 'report/mydata/del',
                    multi_url: 'report/mydata/multi',
                    import_url: 'report/mydata/import',
                    table: 'mydata',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                search: false,
                fixedRightNumber: 1,
                searchFormVisible: true,
                columns: [
                    [
                        {field: 'date', title: __('Date'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, sortable: true},
                        {field: 'register_users', title: __('Register_users'), operate: false, sortable: true},
                        {field: 'register_recharge_users', title: __('Register_recharge_users'), operate: false, sortable: true},
                        {field: 'repeat_users', title: __('Repeat_users'), operate: false, sortable: true},
                        {field: 'repeat_amount', title: __('Repeat_amount'), operate: false, sortable: true},
                        {field: 'recharge_count', title: __('Recharge_count'), operate: false, sortable: true},
                        {field: 'recharge_money', title: __('Recharge_money'), operate: false, sortable: true},
                        {field: 'user_lost', title: __('User_lost'), operate: false, sortable: true},
                        {field: 'bet_amount', title: __('Bet_amount'), operate: false, sortable: true},
                        {field: 'withdraw_money', title: __('Withdraw_money'), operate: false, sortable: true},
                        {field: 'blogger_withdraw_money', title: __('Blogger_withdraw_money'), operate: false, sortable: true},
                        {field: 'member_withdraw_money', title: __('Member_withdraw_money'), operate: false, sortable: true},
                        {field: 'channel_fee', title: __('Channel_fee'), operate: false, sortable: true},
                        {field: 'api_fee', title: __('Api_fee'), operate: false, sortable: true},
                        {field: 'profit', title: __('Profit'), operate: false, sortable: true},
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

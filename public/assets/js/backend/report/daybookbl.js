define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'report/daybookbl/index' + location.search,
                    table: 'daybookblogger',
                }
            });

            var table = $("#table");

            //当表格数据加载完成时
            table.on('load-success.bs.table', function (e, data) {
                //这里可以获取从服务端获取的JSON数据
                // console.log(data);
                //这里我们手动设置底部的值
                $("#recharge").text(data.extend.recharge);
                $("#withdraw").text(data.extend.withdraw);
                $("#api").text(data.extend.api);
                $("#channel").text(data.extend.channel);
                $("#profit").text(data.extend.profit);
            });

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                searchFormVisible: true,
                search:false,
                columns: [
                    [
                        {field: 'date', title: __('Date'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'user.username', title: __('博主用户名'), operate: 'LIKE'},
                        {field: 'admin.username', title: __('所属业务员'), operate: 'LIKE'},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'recharge_amount', title: __('Recharge_amount'), operate: false, sortable: true},
                        {field: 'withdraw_amount', title: __('Withdraw_amount'), operate: false, sortable: true},
                        {field: 'transfer_amount', title: __('Transfer_amount'), operate: false, sortable: true},
                        {field: 'api_amount', title: __('Api_amount'), operate: false, sortable: true},
                        {field: 'channel_fee', title: __('Channel_fee'), operate: false, sortable: true},
                        {field: 'profit_and_loss', title: __('Profit_and_loss'), operate: false, sortable: true},
                        
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

define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'report/daybook/index' + location.search,
                    add_url: 'report/daybook/add',
                    edit_url: 'report/daybook/edit',
                    del_url: 'report/daybook/del',
                    multi_url: 'report/daybook/multi',
                    import_url: 'report/daybook/import',
                    table: 'daybookadmin',
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
                sortName: 'date',
                fixedColumns: true,
                search:false,
                fixedRightNumber: 1,
                searchFormVisible: true,
                columns: [
                    [
                        {field: 'date', title: __('Date'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'admin_id', title: __('Admin_id')},
                        {field: 'admin.username', title: __('Admin.username'), operate: 'LIKE'},
                        {field: 'salary', title: __('博主工资'), operate: false, sortable: true},
                        {field: 'recharge_amount', title: __('Recharge_amount'), operate: false, sortable: true},
                        {field: 'withdraw_amount', title: __('Withdraw_amount'), operate: false, sortable: true},
                        {field: 'api_amount', title: __('Api_amount'), operate: false, sortable: true},
                        {field: 'channel_fee', title: __('通道费用'), operate: false, sortable: true},
                        {
                            field: 'profit_and_loss', 
                            title: __('Profit_and_loss'), 
                            operate: false,
                            sortable: true,
                            formatter: function (value, row, index) {
                                 if(value > 0){
                                    return '<span style="color:red">'+value+'</span>';
                                }else if(value < 0){
                                    return '<span style="color:green">'+value+'</span>'
                                }
                                return value;
                            }
                        },
                    ]
                ],
                
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

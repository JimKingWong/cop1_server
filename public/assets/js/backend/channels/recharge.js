define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'channels/recharge/index' + location.search,
                    import_url: 'channels/recharge/import',
                    table: 'recharge',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                search: false,
                searchFormVisible: true,
                columns: [
                    [
                        {field: 'user_id', title: __('User_id'), formatter: Table.api.formatter.search},
                        {field: 'user.username', title: __('User.username'), operate: 'LIKE'},
                        {field: 'root_invite', title: __('Data.invite_code'), operate: 'LIKE'},
                        {
                            field: 'channel', 
                            title: __('支付通道'), 
                            operate: false, 
                            table: table, class: 'autocontent', 
                            formatter: function(value, row, index) {
                                let str  = '<span class="text-muted">' + row.channel_title + '</span><br>';
                                    str += '<span class="text-muted">' + row.channel_name + '</span>';
                                return str;
                            }
                        },
                        // {field: 'channel.name', title: __('通道名称'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content, visible: false},
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'cpf', title: __('CPF'), operate: 'LIKE'},
                        {field: 'money', title: __('Money'), operate:'BETWEEN'},
                        {field: 'real_amount', title: __('Real_amount'), operate:'BETWEEN'},
                        {field: 'real_pay_amount', title: __('Real_pay_amount'), operate:'BETWEEN'},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'paytime', title: __('Paytime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        
                    ]
                ],
                responseHandler: function (data) {
                    $("#total_recharge").html(data.retval.total_recharge);
                    $("#total_recharge_num").html(data.retval.total_recharge_num);
                    $("#success_recharge").html(data.retval.success_recharge);
                    $('#today_recharge').html(data.retval.today_recharge);
                    $('#today_recharge_num').html(data.retval.today_recharge_num);
                    $('#today_success_recharge').html(data.retval.today_success_recharge);
                    $('#yestoday_recharge').html(data.retval.yestoday_recharge);
                    $('#yestoday_recharge_num').html(data.retval.yestoday_recharge_num);
                    $('#yestoday_success_recharge').html(data.retval.yestoday_success_recharge);
                    return data;
                }
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

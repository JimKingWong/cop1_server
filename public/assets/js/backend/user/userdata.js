define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init();
            
            //绑定事件
            $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                var panel = $($(this).attr("href"));
                if (panel.length > 0) {
                    Controller.table[panel.attr("id")].call(this);
                    $(this).on('click', function (e) {
                        $($(this).attr("href")).find(".btn-refresh").trigger("click");
                    });
                }
                //移除绑定的事件
                $(this).unbind('shown.bs.tab');
            });
            
            //必须默认触发shown.bs.tab事件
            $('ul.nav-tabs li.active a[data-toggle="tab"]').trigger("shown.bs.tab");
        },
        table: {
            moneylog: function () {
                var moneylog_table = $("#moneylog_table");
                moneylog_table.bootstrapTable({
                    url: 'user/userdata/moneylog',
                    toolbar: '#toolbar1',
                    sortName: 'id',
                    search: false,
                    searchFormVisible: true,
                    columns: [
                        [
                            {field: 'admin.username', title: __('Admin_id'), operate: 'LIKE'},
                            {field: 'user_id', title: __('User_id'), operate: false},
                            {field: 'user.username', title: __('User.username'), operate: false},
                            {field: 'money', title: __('Money'), operate:'BETWEEN'},
                            {field: 'before', title: __('Before'), operate:'BETWEEN'},
                            {field: 'after', title: __('After'), operate:'BETWEEN'},
                            {field: 'type', title: __('Type'), searchList: $.getJSON('user/moneylog/getType'), formatter: Table.api.formatter.normal, visible: false},
                            {field: 'memo', title: __('Memo'), operate: 'LIKE', class: 'autocontent', formatter: Table.api.formatter.normal},
                            {field: 'transaction_id', title: __('Transaction_id'), operate: 'LIKE', class: 'autocontent'},
                            {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        ]
                    ],
                    queryParams: function (params) {
                        //这里可以追加搜索条件
                        var filter = JSON.parse(params.filter);
                        var op = JSON.parse(params.op);
                        //这里可以动态赋值，比如从URL中获取admin_id的值，filter.admin_id=Fast.api.query('admin_id');
                        if(Config.user_id){
                            filter.user_id = Config.user_id;
                            op.user_id = "=";
                        }
                        params.filter = JSON.stringify(filter);
                        params.op = JSON.stringify(op);
                        return params;
                    },
                });

                // 为表格1绑定事件
                Table.api.bindevent(moneylog_table);
            },
            rewardlog: function () {
                var rewardlog_table = $("#rewardlog_table");
                rewardlog_table.bootstrapTable({
                    url: 'user/userdata/rewardlog',
                    extend: {
                        index_url: '',
                        add_url: '',
                        edit_url: '',
                        del_url: '',
                        multi_url: '',
                        table: '',
                    },
                    toolbar: '#toolbar2',
                    sortName: 'id',
                    search: false,
                    searchFormVisible: true,
                    columns: [
                        [
                            {field: 'admin.username', title: __('Admin_id'), operate: 'LIKE'},
                            {field: 'user_id', title: __('User_id'), operate: false},
                            {field: 'user.username', title: __('User.username'), operate: false},
                            {field: 'money', title: __('Money'), operate:'BETWEEN'},
                            {field: 'type', title: __('Type'), searchList: $.getJSON('user/rewardlog/getType'), formatter: Table.api.formatter.normal, visible: false},
                            {field: 'memo', title: __('Memo'), operate: 'LIKE', class: 'autocontent', formatter: Table.api.formatter.normal},
                            {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2')}, formatter: Table.api.formatter.status},
                            {field: 'transaction_id', title: __('Transaction_id'), operate: 'LIKE', class: 'autocontent'},
                            {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                            {field: 'receivetime', title: __('Receivetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        ]
                    ],
                    queryParams: function (params) {
                        //这里可以追加搜索条件
                        var filter = JSON.parse(params.filter);
                        var op = JSON.parse(params.op);
                        //这里可以动态赋值，比如从URL中获取admin_id的值，filter.admin_id=Fast.api.query('admin_id');
                        if(Config.user_id){
                            filter.user_id = Config.user_id;
                            op.user_id = "=";
                        }
                        params.filter = JSON.stringify(filter);
                        params.op = JSON.stringify(op);
                        return params;
                    },
                });

                // 为表格2绑定事件
                Table.api.bindevent(rewardlog_table);
            },
            recharge: function () {
                var recharge_table = $("#recharge_table");
                recharge_table.bootstrapTable({
                    url: 'user/userdata/recharge',
                    toolbar: '#toolbar3',
                    sortName: 'id',
                    search: false,
                    searchFormVisible: true,
                    columns: [
                        [
                            {field: 'user_id', title: __('User_id'), operate: false},
                            {field: 'user.username', title: __('User.username'), operate: false},
                            {field: 'admindata.invite_code', title: __('根邀请码'), operate: 'LIKE', class: 'autocontent', formatter: Table.api.formatter.content},
                            {
                                field: 'channel.title', 
                                title: __('支付通道'), 
                                operate: 'LIKE', 
                                class: 'autocontent', 
                                formatter: function(value, row, index) {
                                    let str  = '<span class="text-muted">' + value + '</span><br>';
                                        str += '<span class="text-muted">' + row.channel.name + '</span>';
                                    return str;
                                }
                            },
                            {field: 'channel.name', title: __('通道名称'), operate: 'LIKE', class: 'autocontent', formatter: Table.api.formatter.content, visible: false},
                            {field: 'order_no', title: __('订单号'), operate: 'LIKE'},
                            {field: 'money', title: __('充值金额'), operate:'BETWEEN'},
                            {field: 'real_amount', title: __('到账金额'), operate:'BETWEEN'},
                            {field: 'real_pay_amount', title: __('实际支付金额'), operate:'BETWEEN'},
                            {field: 'status', title: __('充值状态'), searchList: {"0":__('待付款'),"1":__('已付款'),"2":__('其他')}, formatter: Table.api.formatter.status},
                            {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                            {field: 'paytime', title: __('支付时间'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                            
                        ]
                    ],
                    queryParams: function (params) {
                        //这里可以追加搜索条件
                        var filter = JSON.parse(params.filter);
                        var op = JSON.parse(params.op);
                        //这里可以动态赋值，比如从URL中获取admin_id的值，filter.admin_id=Fast.api.query('admin_id');
                        if(Config.user_id){
                            filter.user_id = Config.user_id;
                            op.user_id = "=";
                        }
                        params.filter = JSON.stringify(filter);
                        params.op = JSON.stringify(op);
                        return params;
                    },
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

                // 为表格2绑定事件
                Table.api.bindevent(recharge_table);
            },
            withdraw: function () {
                var withdraw_table = $("#withdraw_table");
                withdraw_table.bootstrapTable({
                    url: 'user/userdata/withdraw',
                    extend: {
                        index_url: '',
                        dragsort_url: '',
                    },
                    toolbar: '#toolbar4',
                    sortName: 'id',
                    search: false,
                    searchFormVisible: true,
                    columns: [
                        [
                            {field: 'order_no', title: __('订单号'), operate: 'LIKE'},
                            {field: 'user_id', title: __('用户id'), visible: false},
                            {
                                field: 'user', 
                                title: __('用户信息'),
                                operate: false,
                                formatter: function (value, row, index) {
                                    return `<div class="boxs">
                                                <div class="boxs-info">
                                                    <div><span>UID: </span>${row.user_id}</div>
                                                    <div><span>用户名: </span>${row.user.username}</div>
                                                    <div><span>来源: </span>${row.user.origin}</div>
                                                    <div><span>备注: </span>${row.remark || ''}</div>
                                                </div>
                                            </div>`;
                                },
                            },
                            {
                                field: 'user', 
                                title: __('用户数据'),
                                operate: false,
                                formatter: function (value, row, index) {
                                    return `<div class="boxs">
                                                <div class="boxs-info">
                                                    <div><span>余额: </span>${row.user.money}</div>
                                                    <div><span>工资: </span>${row.userdata.commission}</div>
                                                    <div><span>今日盈利: </span>${row.userdata.today_profit}</div>
                                                    <div><span>今日流水: </span>${row.userdata.today_bet}</div>
                                                </div>
                                            </div>`;
                                },
                            },
                            {
                                field: 'wallet', 
                                title: __('钱包信息'),
                                operate: false,
                                formatter: function (value, row, index) {
                                    return `<div class="boxs">
                                                <div class="boxs-info">
                                                    <div><span>名字: </span>${row.wallet.name}</div>
                                                    <div><span>手机号: </span>${row.wallet.phone_number}</div>
                                                    <div><span>类型: </span>${row.wallet.chave_pix}</div>
                                                    <div><span>CPF/CNPJ: </span>${row.wallet.pix}</div>
                                                </div>
                                            </div>`;
                                },
                            },
                            {field: 'user.role', title: __('用户类型'), searchList: {"0":__('会员'),"1":__('博主')}, formatter: Table.api.formatter.status},
                            {field: 'wallet.phone_number', title: __('手机号'), operate: 'LIKE', visible: false},
                            {field: 'wallet.pix', title: __('cpf'), operate: 'LIKE', class: 'autocontent', formatter: Table.api.formatter.content, visible: false},
                            {field: 'wallet.chave_pix', title: __('chave_pix'), searchList: {"PIX_CPF":__('PIX_CPF'),"PIX_PHONE":__('PIX_PHONE'),"PIX_CNPJ":__('PIX_CNPJ')}, visible: false},
                            {field: 'money', title: __('提现金额'), operate:'BETWEEN'},
                            {field: 'real_money', title: __('到账金额'), operate:'BETWEEN'},
                            {field: 'fee', title: __('手续费'), operate:'BETWEEN'},
                            {field: 'remark', title: __('备注'), operate: 'LIKE', class: 'autocontent'},
                            {field: 'status', title: __('提现状态'), searchList: {"0":__('审核中'),"1":__('成功付款'),"2":__('手动拒绝'),"3":__('失败'),"4":__('付款中'),"5":__('异常')}, formatter: Table.api.formatter.status},
                            {field: 'paytime', title: __('代付时间'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                            {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        ]
                    ],
                    responseHandler: function (data) {
                        $("#total_withdraw").html(data.retval.total_withdraw);
                        $("#total_withdraw_num").html(data.retval.total_withdraw_num);
                        $("#success_withdraw").html(data.retval.success_withdraw);
                        $('#today_withdraw').html(data.retval.today_withdraw);
                        $('#today_withdraw_num').html(data.retval.today_withdraw_num);
                        $('#today_success_withdraw').html(data.retval.today_success_withdraw);
                        $('#yestoday_withdraw').html(data.retval.yestoday_withdraw);
                        $('#yestoday_withdraw_num').html(data.retval.yestoday_withdraw_num);
                        $('#yestoday_success_withdraw').html(data.retval.yestoday_success_withdraw);
                        return data;
                    },
                    queryParams: function (params) {
                        //这里可以追加搜索条件
                        var filter = JSON.parse(params.filter);
                        var op = JSON.parse(params.op);
                        //这里可以动态赋值，比如从URL中获取admin_id的值，filter.admin_id=Fast.api.query('admin_id');
                        if(Config.user_id){
                            filter.user_id = Config.user_id;
                            op.user_id = "=";
                        }
                        params.filter = JSON.stringify(filter);
                        params.op = JSON.stringify(op);
                        return params;
                    },
                });

                // 为表格1绑定事件
                Table.api.bindevent(withdraw_table);
            },
            subuser: function () {
                var subuser_table = $("#subuser_table");

                //当表格数据加载完成时
                subuser_table.on('load-success.bs.table', function (e, data) {
                    //这里可以获取从服务端获取的JSON数据
                    console.log(data);
                    //这里我们手动设置底部的值
                    $("#salary").text(data.extend.salary);
                    $("#commission").text(data.extend.commission);
                    $("#valid_users").text(data.extend.valid_users);
                });

                subuser_table.bootstrapTable({
                    url: 'user/userdata/subuser',
                    toolbar: '#toolbar5',
                    search: false,
                    commonSearch: false,
                    columns: [
                        [
                            {field: 'level', title: __('等级')},
                            {field: 'total_user', title: __('总人数')},
                            {field: 'valid_user', title: __('业务员有效用户')},
                            {field: 'total_recharge_num', title: __('充值人数')},
                            {field: 'total_recharge_money', title: __('充值金额')},
                            {field: 'avg_recharge_money', title: __('平均充值')},
                            {field: 'total_withdraw_num', title: __('提现人数')},
                            {field: 'total_withdraw_money', title: __('提现金额')},
                            {field: 'total_bet', title: __('总流水')},
                        ]
                    ],
                    queryParams: function (params) {
                        if(Config.user_id){
                            //这里可以追加搜索条件
                            var filter = {};
                            var op = {};
                            filter.user_id = Config.user_id;
                            op.user_id = "=";
                            filter.withdraw_id = Config.withdraw_id;
                            op.withdraw_id = "=";
                            params.filter = JSON.stringify(filter);
                            params.op = JSON.stringify(op);
                            return params;
                        }
                        
                    },
                });

                // 为表格1绑定事件
                Table.api.bindevent(subuser_table);
            },
            unbind: function () {
                var unbind_table = $("#unbind_table");

                //当表格数据加载完成时
                unbind_table.on('load-success.bs.table', function (e, data) {
                    //这里可以获取从服务端获取的JSON数据
                    console.log(data);
                    //这里我们手动设置底部的值
                    $("#unbind_salary").text(data.extend.salary);
                    $("#unbind_commission").text(data.extend.commission);
                    $("#unbind_valid_users").text(data.extend.valid_users);
                });

                unbind_table.bootstrapTable({
                    url: 'user/userdata/unbind',
                    toolbar: '#toolbar6',
                    search: false,
                    commonSearch: false,
                    columns: [
                        [
                            {field: 'level', title: __('等级')},
                            {field: 'total_user', title: __('总人数')},
                            {field: 'valid_user', title: __('业务员有效用户')},
                            {field: 'total_recharge_num', title: __('充值人数')},
                            {field: 'total_recharge_money', title: __('充值金额')},
                            {field: 'avg_recharge_money', title: __('平均充值')},
                            {field: 'total_withdraw_num', title: __('提现人数')},
                            {field: 'total_withdraw_money', title: __('提现金额')},
                            {field: 'total_bet', title: __('总流水')},
                        ]
                    ],
                    queryParams: function (params) {
                        if(Config.user_id){
                            //这里可以追加搜索条件
                            var filter = {};
                            var op = {};
                            
                            filter.user_id = Config.user_id;
                            op.user_id = "=";
                            params.filter = JSON.stringify(filter);
                            params.op = JSON.stringify(op);
                            return params;
                        }
                        
                    },
                });

                // 为表格1绑定事件
                Table.api.bindevent(unbind_table);
            },
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
            },
        }
    };
    return Controller;
});

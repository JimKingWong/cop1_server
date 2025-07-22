define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'channels/withdraw/index' + location.search,
                    edit_url: 'channels/withdraw/edit',
                    import_url: 'channels/withdraw/import',
                    table: 'withdraw',
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
                        {field: 'order_no', title: __('Order_no'), operate: 'LIKE'},
                        {field: 'user_id', title: __('User_id'), visible: false},
                        {
                            field: 'user', 
                            title: __('用户信息'),
                            operate: false,
                            formatter: function (value, row, index) {
                                return `<div class="boxs">
                                            <div class="boxs-info">
                                                <div><span>UID: </span>${row.user_id}</div>
                                                <a href="javascript:" data-url="channels/withdraw/subuser?user_id=${row.user_id}&withdraw_id=${row.id}" class="dialogit" title="用户数据" data-area=\'["80%", "100%"]\'>
                                                <div><span>用户名: </span>${row.user.username}</div>
                                                </a>
                                                <div><span>来源: </span>${row.admin.nickname}</div>
                                                <div><span>站点: </span>${row.user.origin}</div>
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
                                                <div><span>工资: </span>${row.userdata.salary}</div>
                                                <div><span>充值: </span>${row.userdata.total_recharge}</div>
                                                <div><span>提现: </span>${row.userdata.total_withdraw}</div>
                                                <div><span>总盈利: </span>${row.userdata.total_profit}</div>
                                                <div><span>总流水: </span>${row.userdata.total_bet}</div>
                                            </div>
                                        </div>`;
                            },
                        },
                        {
                            field: 'wallet', 
                            title: __('Wallet_id'),
                            operate: false,
                            formatter: function (value, row, index) {
                                return `<div class="boxs">
                                            <div class="boxs-info">
                                                <div><span>名字: </span>${row.wallet.name}</div>
                                                <div><span>手机号: </span>${row.wallet.area_code} ${row.wallet.phone_number}</div>
                                                <div><span>类型: </span>${row.wallet.chave_pix}</div>
                                                <a href="javascript:" data-url="user/wallet/index?ids=${row.user_id}" class="dialogit" title="用户钱包信息" data-area=\'["80%", "100%"]\'><div><span>CPF/CNPJ: </span>${row.wallet.pix}</div></a>
                                            </div>
                                        </div>`;
                            },
                        },
                        {field: 'user.role', title: __('用户类型'), searchList: {"0":__('会员'),"1":__('博主')}, formatter: Table.api.formatter.status},
                        {field: 'wallet.phone_number', title: __('Wallet.phone_number'), operate: 'LIKE', visible: false},
                        {field: 'wallet.pix', title: __('Wallet.cpf'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content, visible: false},
                        {field: 'wallet.chave_pix', title: __('Wallet.chave_pix'), searchList: {"PIX_CPF":__('PIX_CPF'),"PIX_PHONE":__('PIX_PHONE'),"PIX_CNPJ":__('PIX_CNPJ')}, visible: false},
                        {field: 'money', title: __('Money'), operate:'BETWEEN', sortable: true},
                        {field: 'real_money', title: __('Real_money'), operate:'BETWEEN'},
                        {field: 'fee', title: __('Fee'), operate:'BETWEEN'},
                        {field: 'remark', title: __('Remark'), operate: 'LIKE', table: table, class: 'autocontent'},
                        {field: 'user.remark', title: __('用户备注'), operate: 'LIKE', table: table, class: 'autocontent'},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1'),"2":__('Status 2'),"3":__('Status 3'),"4":__('Status 4'),"5":__('Status 5')}, formatter: Table.api.formatter.status},
                        {field: 'paytime', title: __('Paytime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {
                            field: 'operate', 
                            title: __('Operate'), 
                            table: table, 
                            events: Table.api.events.operate, 
                            buttons: [
                                {
                                    name: 'detail',
                                    title: __('查看凭证'),
                                    text: __('查看凭证'),
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    url: 'channels/withdraw/detail',
                                    hidden: function (row, value, index) {
                                        return row.status == 1 ? false : true;
                                    },
                                    extend: 'data-area=\'["40%","40%"]\'',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    }
                                },
                                {
                                    name: 'refuse',
                                    title: __('拒绝'),
                                    text: __('拒绝'),
                                    classname: 'btn btn-xs btn-danger btn-dialog',
                                    url: 'channels/withdraw/refuse',
                                    hidden: function (row, value, index) {
                                        return row.status == 0 || row.status == 5 ? false : true;
                                    },
                                    extend: 'data-area=\'["40%","40%"]\'',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    }
                                },
                                {
                                    name: 'notify',
                                    title: __('通知成功'),
                                    text: __('通知成功'),
                                    classname: 'btn btn-xs btn-warning btn-magic btn-ajax',
                                    confirm: '确认通知成功 (平台已通知但未处理的单子)？',
                                    url: 'channels/withdraw/notify',
                                    hidden: function (row, value, index) {
                                        return row.status == 4 ? false : true;
                                    },
                                    success: function (data, ret) {
                                        Layer.alert(ret.msg);
                                        $(".btn-refresh").trigger("click");
                                        //如果需要阻止成功提示，则必须使用return false;
                                        //return false;
                                    },
                                    error: function (data, ret) {
                                        console.log(data, ret);
                                        Layer.alert(ret.msg);
                                        return false;
                                    }
                                },
                                {
                                    name: 'pay',
                                    title: __('代付'),
                                    text: __('代付'),
                                    classname: 'btn btn-xs btn-warning btn-dialog',
                                    url: 'channels/withdraw/pay',
                                    extend: 'data-area=\'["40%","40%"]\'',
                                    hidden: function (row, value, index) {
                                        return row.status == 0 || row.status == 5 ? false : true;
                                    },
                                    callback: function (data) {
                                        Toastr.success('操作成功');
                                        $(".btn-refresh").trigger("click");
                                        // parent.$("a..btn-refresh").trigger("click");

                                        // Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    }
                                },
                            ],
                            formatter: Table.api.formatter.operate
                        }
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
                }
                
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        pay: function () {
            $(document).on("click", ".btn-pays", function(){
                let channel = $(this).data('channel');
                let ids = $(this).data('ids');
                let msg = '确认使用' + channel + '通道代付？';
                layer.confirm(msg, function(data){
                    Fast.api.ajax({
                        url: 'channels/withdraw/pay',
                        data: {ids: ids, channel_name: channel},
                    }, function(data, ret){
                        // Layer.alert(ret.msg);
                        // if(ret.code == 1){
                        //     Toastr.success('操作成功');
                        //     $(".btn-refresh").trigger("click");
                        //     Fast.api.close();
                        // }
                        Fast.api.close();
                    }, function(data, ret){
                        Layer.alert(ret.msg);
                        return false;
                    });
                })
            })
            Controller.api.bindevent();
        },
        refuse: function () {
            Controller.api.bindevent();
        },
        detail: function () {
            Controller.api.bindevent();
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

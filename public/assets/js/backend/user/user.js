define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/user/index',
                    add_url: 'user/user/add',
                    edit_url: 'user/user/edit',
                    multi_url: 'user/user/multi',
                    table: 'user',
                }
            });

            var table = $("#table");
           
           

            //在普通搜索渲染后
            table.on('post-common-search.bs.table', function (event, table) {
                var form = $("form", table.$commonsearch);
                $("input[name='origin']", form).addClass("selectpage").data("source", "platform/site/index").data("primaryKey", "url").data("field", "url").data("orderBy", "id desc");
                Form.events.selectpage(form);
            });

           
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'user.id',
                fixedColumns: true,
                fixedRightNumber: 1,
                searchFormVisible: true,
                columns: [
                    [
                        {field: 'origin', title: __('站点'), formatter: Table.api.formatter.search},
                        // {field: 'origin', title: __('站点'), searchList: $.getJSON('platform/site/getSite')},
                        {
                            field: 'id', 
                            title: __('用户Id'), 
                            sortable: true,
                            formatter: function (value, row, index) {
                                let color_class = '';
                                if(row.is_first_recharge){
                                     color_class = 'text-red';
                                }
                                return '<a href="javascript:" data-url="user/userdata/index?ids=' + row['id'] + '" title="用户信息" class="dialogit ' + color_class + '" data-area=\'["80%", "100%"]\'>' + value + '</a>';
                            }
                        },
                        {field: 'parent_id', title: __('上级ID'), sortable: true},
                        {field: 'role', title: __('身份'), searchList: {"0":__('会员'),"1":__('博主')}, formatter: Table.api.formatter.normal, visible: false},
                        {
                            field: 'username', 
                            title: __('Username'), 
                            operate: 'LIKE',
                            formatter: function (value, row, index) {
                                // 所属部门
                                let department = '自然流量';
                                if(row.admin && row.admin.dadmin[0]){
                                    // department = row.admin.dadmin[0].department.name + '<br>' + 
                                    department = row.admin.nickname
                                }
                                
                                let color_class = '';
                                if(row.role == 1){
                                    color_class = 'text-danger'
                                }
                                let str  = `<span>${department}</span><br />`
                                    str += `<span class="${color_class}">${value}</span>`
                                return str;
                            }
                        },
                        {field: 'pay_password', title: __('支付密码')},
                        {field: 'userdata.total_bet', title: __('流水'), sortable: true, operate: 'BETWEEN'},
                        {field: 'userdata.today_bet', title: __('今日流水'), sortable: true, operate: 'BETWEEN'},
                        {field: 'userdata.typing_amount_limit', title: __('提现所需流水'), sortable: true, operate: 'BETWEEN'},
                        {field: 'userdata.total_withdraw', title: __('累计提现'), sortable: true, operate: 'BETWEEN'},
                        {field: 'userdata.total_recharge', title: __('累计充值'), sortable: true, operate: 'BETWEEN'},
                        {field: 'userdata.total_profit', title: __('累计盈利'), sortable: true, operate: 'BETWEEN'},
                        {field: 'userdata.today_profit', title: __('今日盈利'), sortable: true, operate: 'BETWEEN'},
                        {field: 'userdata.salary', title: __('工资'), sortable: true, operate: 'BETWEEN'},
                        {field: 'money', title: __('Money'), operate: 'NUMBER', sortable: true, operate: 'BETWEEN'},
                        {
                            field: 'userdata.invite_num', 
                            title: __('下级/下级充值'), 
                            operate: false,
                            formatter: function (value, row, index) {
                                return row.userdata.invite_num + ' / ' + row.userdata.invite_recharge_num;
                            },
                            sortable: true
                        },
                        {
                            field: 'invite_code', 
                            title: __('自身码||上级码'), 
                            operate: 'LIKE',
                            formatter: function (value, row, index) {
                                if (row.be_invite_code) {
                                    return value + ' || ' + row.be_invite_code;
                                }else{
                                    return value + ' || ';
                                }
                            }
                        },
                        {field: 'be_invite_code', title: __('上级码'), operate: 'LIKE', visible: false},
                        {field: 'root_invite', title: __('代理码'), operate: 'LIKE'},
                        {field: 'remark', title: __('备注'), operate: 'LIKE'},
                        {field: 'jointime', title: __('Jointime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'joinip', title: __('Joinip'), formatter: Table.api.formatter.search},
                        // {field: 'logintime', title: __('最后一次登录时间'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'loginip', title: __('最后一次登录ip'), formatter: Table.api.formatter.search},
                        {
                            field: 'operate', 
                            title: __('Operate'), 
                            table: table, 
                            events: Table.api.events.operate, 
                            buttons: [
                                {
                                    name: 'setting',
                                    title: function(row){
                                        return '设置 (UID: ' + row.id + ')';
                                    },
                                    text: function(row){
                                        return '设置';
                                    },
                                    classname: 'btn btn-xs btn-danger btn-dialog',
                                    url: 'user/user/setting',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    }
                                },
                                {
                                    name: 'userdata',
                                    title: function(row){
                                        return '数据明细 (UID: ' + row.id + ', 用户名: ' + row.username +  ')';
                                    },
                                    text: function(row){
                                        return '数据';
                                    },
                                    classname: 'btn btn-xs btn-warning btn-dialog',
                                    url: 'user/userdata/index',
                                    extend: 'data-area=\'["80%","100%"]\'',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    }
                                },
                            ],
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ],
                queryParams: function (params) {
                    //这里可以追加搜索条件
                    var filter = JSON.parse(params.filter);
                    var op = JSON.parse(params.op);
                    //这里可以动态赋值，比如从URL中获取admin_id的值，filter.admin_id=Fast.api.query('admin_id');
                    filter.is_test = 0;
                    op.is_test = "=";
                    params.filter = JSON.stringify(filter);
                    params.op = JSON.stringify(op);
                    return params;
                },
            });

            // 获取选中项
            $(document).on("click", ".btn-patch", function () {
                Fast.api.open('user/user/patch', '批量添加', {area: ['50%', '60%']});
            });

            $("input[name='origin']").data("params", function(){
                return {custom: {status:1}}
            })

            // 为表格绑定事件
            Table.api.bindevent(table);
        },

        virtual: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/user/index',
                    add_url: 'user/user/add',
                    edit_url: 'user/user/edit',
                    multi_url: 'user/user/multi',
                    table: 'user',
                }
            });

            var table = $("#table");

            //在普通搜索渲染后
            table.on('post-common-search.bs.table', function (event, table) {
                var form = $("form", table.$commonsearch);
                $("input[name='origin']", form).addClass("selectpage").data("source", "platform/site/index").data("params", '{"status":"1"}').data("primaryKey", "url").data("field", "url").data("orderBy", "id desc");
                Form.events.selectpage(form);
            });
           
            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'user.id',
                fixedColumns: true,
                fixedRightNumber: 1,
                searchFormVisible: true,
                columns: [
                    [
                        {field: 'origin', title: __('站点'), formatter: Table.api.formatter.search},
                        // {field: 'origin', title: __('站点'), searchList: $.getJSON('platform/site/getSite')},
                        {
                            field: 'id', 
                            title: __('用户Id'), 
                            sortable: true,
                            formatter: function (value, row, index) {
                                let color_class = '';
                                if(row.is_first_recharge){
                                     color_class = 'text-red';
                                }
                                return '<a href="javascript:" data-url="user/userdata/index?ids=' + row['id'] + '" title="用户信息" class="dialogit ' + color_class + '" data-area=\'["80%", "100%"]\'>' + value + '</a>';
                            }
                        },
                        {field: 'parent_id', title: __('上级ID'), sortable: true},
                        {field: 'role', title: __('身份'), searchList: {"0":__('会员'),"1":__('博主')}, formatter: Table.api.formatter.normal, visible: false},
                        {
                            field: 'username', 
                            title: __('Username'), 
                            operate: 'LIKE',
                            formatter: function (value, row, index) {
                                // 所属部门
                                let department = '自然流量';
                                if(row.admin && row.admin.dadmin[0]){
                                    // department = row.admin.dadmin[0].department.name + '<br>' + 
                                    department = row.admin.nickname
                                }
                                
                                let color_class = '';
                                if(row.role == 1){
                                    color_class = 'text-danger'
                                }
                                let str  = `<span>${department}</span><br />`
                                    str += `<span class="${color_class}">${value}</span>`
                                return str;
                            }
                        },
                        {field: 'userdata.total_bet', title: __('流水'), sortable: true, operate: 'BETWEEN'},
                        {field: 'userdata.today_bet', title: __('今日流水'), sortable: true, operate: 'BETWEEN'},
                        {field: 'userdata.typing_amount_limit', title: __('提现所需流水'), sortable: true, operate: 'BETWEEN'},
                        {field: 'userdata.total_withdraw', title: __('累计提现'), sortable: true, operate: 'BETWEEN'},
                        {field: 'userdata.total_recharge', title: __('累计充值'), sortable: true, operate: 'BETWEEN'},
                        {field: 'userdata.total_profit', title: __('累计盈利'), sortable: true, operate: 'BETWEEN'},
                        {field: 'userdata.today_profit', title: __('今日盈利'), sortable: true, operate: 'BETWEEN'},
                        {field: 'userdata.salary', title: __('工资'), sortable: true, operate: 'BETWEEN'},
                        {field: 'money', title: __('Money'), operate: 'NUMBER', sortable: true, operate: 'BETWEEN'},
                        {
                            field: 'invite_code', 
                            title: __('自身码||上级码'), 
                            operate: 'LIKE',
                            formatter: function (value, row, index) {
                                if (row.be_invite_code) {
                                    return value + ' || ' + row.be_invite_code;
                                }else{
                                    return value + ' || ';
                                }
                            }
                        },
                        {field: 'be_invite_code', title: __('上级码'), operate: 'LIKE', visible: false},
                        {field: 'root_invite', title: __('代理码'), operate: 'LIKE'},
                        {field: 'jointime', title: __('Jointime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'joinip', title: __('Joinip'), formatter: Table.api.formatter.search},
                        // {field: 'logintime', title: __('最后一次登录时间'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'loginip', title: __('最后一次登录ip'), formatter: Table.api.formatter.search},
                        {
                            field: 'operate', 
                            title: __('Operate'), 
                            table: table, 
                            events: Table.api.events.operate, 
                            buttons: [
                                {
                                    name: 'amount',
                                    title: function(row){
                                        return '上分 (UID: ' + row.id + ')';
                                    },
                                    text: function(row){
                                        return '上分';
                                    },
                                    classname: 'btn btn-xs btn-danger btn-dialog',
                                    url: 'user/user/amount',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    }
                                },
                                
                            ],
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ],
                queryParams: function (params) {
                    //这里可以追加搜索条件
                    var filter = JSON.parse(params.filter);
                    var op = JSON.parse(params.op);
                    //这里可以动态赋值，比如从URL中获取admin_id的值，filter.admin_id=Fast.api.query('admin_id');
                    filter.is_test = 1;
                    op.is_test = "=";
                    params.filter = JSON.stringify(filter);
                    params.op = JSON.stringify(op);
                    return params;
                },
            });

             // 获取选中项
            $(document).on("click", ".btn-patch", function () {
                Fast.api.open('user/user/patch', '批量添加', {area: ['50%', '60%']});
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },

        amount: function () {
            Controller.api.bindevent();
        },
        patch: function () {
            Controller.api.bindevent();
        },
        setting: function () {
            $(document).on("click", ".find", function () {
                var invite_code = $('#c-invite_code').val();
                if(invite_code == ''){
                    Layer.msg('请输入PID或业务员邀请码');return;
                }

                $('.invite').empty();
                var url = 'user/user/findParent';
                var data = {invite_code}
                $.post(url, data, function(res){
                    // console.log(res);
                    if(res.code == 0){
                        Layer.msg(res.msg);return;
                    }

                    if(res.flag == 1){
                        var html = `<div class="panel panel-default"><div class="panel-heading">上级的邀请信息</div>
                        <div class="panel-body">
                            <p>用户名: ${res.data.username}</p>
                            <p>昵称: ${res.data.nickname}</p>
                            <p>邮箱: ${res.data.email}</p>
                            <p>业务员邀请码: ${res.data.root_invite}</p>
                            <p>上级邀请码: ${res.data.invite_code}</p>
                        </div></div>`
                    }else{
                        var html = `<div class="panel panel-default"><div class="panel-heading">上级的邀请信息</div>
                        <div class="panel-body">
                            <p>用户名: ${res.data.username}</p>
                            <p>部门: ${res.data.group_name}</p>
                            <p>备注: ${res.data.remark}</p>
                        </div></div>`
                    }

                    $('.invite').append(html);
                })
            });

            $(document).on("change", "#c-unbind_status", function(){
                if($(this).val() == 1){
                    $('.unbind').removeClass('hide');
                }else{
                    $('.unbind').addClass('hide');
                }
            })

            $(document).on("change", "#c-commission_status", function(){
                if($(this).val() == 1){
                    $('.commission').removeClass('hide');
                }else{
                    $('.commission').addClass('hide');
                }
            })
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
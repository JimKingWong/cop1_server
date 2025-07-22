define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/wallet/index' + location.search,
                    multi_url: 'user/wallet/multi',
                    table: 'user_wallet',
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
                searchFormVisible: true,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {
                            field: 'userinfo', 
                            title: __('用户信息'),
                            operate: false,
                            formatter: function (value, row, index) {
                                return row.user_id + '<br/> ' + row.user.username;
                            }
                        },
                        {field: 'user_id', title: __('User_id'), operate: false, visible: false},
                        {field: 'user.username', title: __('User.username'), operate: false, visible: false},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {
                            field: 'area_code', 
                            title: __('Phone_number'), 
                            operate: false,
                            formatter: function (value, row, index) {
                                return value ? value +' '+ row.phone_number : '';
                            }
                        },
                        {field: 'phone_number', title: __('Phone_number'), operate: 'LIKE', visible: false},
                        {field: 'pix_type', title: __('Pix_type'), operate: 'LIKE'},
                        {field: 'chave_pix', title: __('Chave_pix'), operate: 'LIKE'},
                        {field: 'cpf', title: __('Cpf'), operate: 'LIKE'},
                        {field: 'pix', title: __('Pix'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'is_default', title: __('Is_default'), searchList: {"0":__('Is_default 0'),"1":__('Is_default 1')}, formatter: Table.api.formatter.normal},
                        {field: 'status', title: __('状态'), searchList: {"0":__('拉黑'),"1":__('正常')}, formatter: Table.api.formatter.toggle},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        // {
                        //     field: 'operate', 
                        //     title: __('Operate'), 
                        //     table: table, 
                        //     events: Table.api.events.operate, 
                        //     formatter: Table.api.formatter.operate
                        // }
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

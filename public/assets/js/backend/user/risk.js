define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/risk/index' + location.search,
                    // add_url: 'user/risk/add',
                    // edit_url: 'user/risk/edit',
                    // del_url: 'user/risk/del',
                    multi_url: 'user/risk/multi',
                    import_url: 'user/risk/import',
                    table: 'risk_task',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'user.username', title: __('User.username'), operate: 'LIKE'},
                        {field: 'admin_id', title: __('Admin_id')},
                        {field: 'user_id', title: __('User_id')},
                        // {field: 'is_problem', title: __('异常'), searchList: {"0":__('无'),"1":__('是')}, operate: false},
                        {field: 'status', title: __('状态'), searchList: {"0":__('监控'),"1":__('已审核')}, formatter: Table.api.formatter.normal},
                        {field: 'num', title: __('Num')},
                        {field: 'lasttime', title: __('Lasttime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {
                            field: 'operate', 
                            title: __('Operate'), 
                            table: table, 
                            events: Table.api.events.operate, 
                            buttons: [
                                {
                                    name: 'userdata',
                                    title: function(row){
                                        return '检测报告 (UID: ' + row.user_id + ', 用户名: ' + row.user.username +  ', 站点: ' + row.user.origin + ')';
                                    },
                                    text: function(row){
                                        return '详情';
                                    },
                                    classname: 'btn btn-xs btn-warning btn-dialog',
                                    hidden: function(row){
                                        return row.is_problem ? false : true;
                                    },
                                    url: function(row){
                                        return 'user/risklog/detail?task_id=' + row.id;
                                    },
                                    extend: 'data-area=\'["50%","100%"]\'',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    }
                                },
                            ],
                            formatter: Table.api.formatter.operate
                        }
                    ]
                ]
            });

            $(document).on("click", ".btn-remove-list", function () {
                let temp = Table.api.selectedids(table);
                ids = temp.join(',');

                var area = {area: ['40%', '40%']};
                var title = '审核并移除';
                Fast.api.open('user/risk/remove?ids=' + ids, title, area);
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

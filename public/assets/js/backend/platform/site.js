define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'platform/site/index' + location.search,
                    add_url: 'platform/site/add',
                    edit_url: 'platform/site/edit',
                    del_url: 'platform/site/del',
                    multi_url: 'platform/site/multi',
                    import_url: 'platform/site/import',
                    table: 'site',
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
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'url', title: __('Url'), operate: 'LIKE', formatter: Table.api.formatter.url},
                        {field: 'multiple', title: __('打码量倍数'), operate: false},
                        {field: 'cases.title', title: __('Case_id')},
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.toggle},
                        {field: 'onlinetime', title: __('Onlinetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },

        workbench: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'platform/site/workbench' + location.search,
                    table: 'site',
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
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'url', title: __('Url'), operate: 'LIKE', formatter: Table.api.formatter.url},
                         {
                            field: 'url', 
                            title: __('Operate'), 
                            operate: false, 
                            formatter: function (value, row, index) {
                                let btn = `<a href="javascript:void(0);" class="btn btn-xs btn-danger" onclick="copy('` + value + `')">` + __('复制') + `</a>`;
                                return btn;
                            }
                        },
                        {field: 'status', title: __('Status'), searchList: {"0":__('Status 0'),"1":__('Status 1')}, formatter: Table.api.formatter.normal},
                        {field: 'onlinetime', title: __('Onlinetime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                    ]
                ],
                queryParams: function (params) {
                    //这里可以追加搜索条件
                    var filter = JSON.parse(params.filter);
                    var op = JSON.parse(params.op);
                    //这里可以动态赋值，比如从URL中获取admin_id的值，filter.admin_id=Fast.api.query('admin_id');
                    filter.status = 1;
                    op.status = "=";
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
function copy(val){
    var copyContent = val
    var tempTextArea = $('<textarea>')
    tempTextArea.val(copyContent)
    $('body').append(tempTextArea)
    tempTextArea.select();
    document.execCommand('copy');
    tempTextArea.remove();
    alert("复制成功！");
}

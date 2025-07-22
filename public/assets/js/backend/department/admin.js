define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'department/admin/index',
                    add_url: 'department/admin/add',
                    edit_url: 'department/admin/edit',
                    del_url: 'department/admin/del',
                    multi_url: 'department/admin/multi',
                }
            });

            var table = $("#table");

            //在表格内容渲染完成后回调的事件
            table.on('post-body.bs.table', function (e, json) {

            });
            var columnss=[
                {field: 'state', checkbox: true, },
                {
                    field: 'username', 
                    title: __('账号信息'), 
                    operate: "LIKE",
                    width: '10%',
                    formatter: function (value, row, index) {
                        let department = '';
                        if(row.dadmin.length > 0){
                            $.each(row.dadmin, function(i,v){  //arrTmp数组数据
                                if (v.department){
                                    department += department ? ',' + v.department.name : v.department.name;
                                }
                            });
                        }
                        return `
                            <div class="account-info-center">
                                <div class="info-container">
                                    <div class="info-line">
                                        <span class="info-label">用户名：</span>
                                        <span class="info-value">${row.id} ${value || '-'}</span>
                                    </div>
                                    <div class="info-line">
                                        <span class="info-label">昵称：</span>
                                        <span class="info-value">${row.nickname || '-'}</span>
                                    </div>
                                    <div class="info-line">
                                        <span class="info-label">状态：</span>
                                        <span class="info-value">${Table.api.formatter.status(row.status, '', 'status')}</span>
                                    </div>
                                    
                                </div>
                            </div>
                            `;
                            
                    },
                },
                {
                    field: 'role', 
                    title: __('角色信息'), 
                    operate: false,
                    width: '10%',
                    formatter: function (value, row, index) {
                        let department = '';
                        if(row.dadmin.length > 0){
                            $.each(row.dadmin, function(i,v){  //arrTmp数组数据
                                if (v.department){
                                    department += department ? ',' + v.department.name : v.department.name;
                                }
                            });
                        }

                        if (row.groups.length == 0)
                            return '-' ;
                        var groups_text="";
                        $.each(row.groups,function(i,v){  //arrTmp数组数据
                            if (v.get_group){
                                groups_text+=groups_text?','+v.get_group.name:v.get_group.name;
                            }
                        });

                        var str=__('No');
                        if (row.dadmin.length == 0)
                            return str ;
                        $.each(row.dadmin,function(i,v){  //arrTmp数组数据
                            if (v.is_principal==1){
                                str='<span class="text-success">'+__('Yes')+'</span>' ;
                            }
                        });

                        return `
                            <div class="account-info-center">
                                <div class="info-container">
                                    <div class="info-line">
                                        <span class="info-label">负责人：</span>
                                        <span class="info-value">${str}</span>
                                    </div>
                                    <div class="info-line">
                                        <span class="info-label">部门：</span>
                                        <span class="info-value">${Table.api.formatter.flag(department, '', 'department')}</span>
                                    </div>
                                    
                                    <div class="info-line">
                                        <span class="info-label">角色：</span>
                                        <span class="info-value">${Table.api.formatter.flag.call(this, groups_text, row, index)}</span>
                                    </div>
                                </div>
                            </div>
                            `;
                            
                    }
                },
                {field: 'nickname', title: __('Nickname'), operate: "LIKE", visible: false},
                {field: 'status', title: __("Status"), searchList: {"normal":__('Normal'),"hidden":__('离职')}, formatter: Table.api.formatter.status, visible: false},
                {
                    field: 'department_id',
                    title: __('Department'),
                    visible: false,
                    addclass: 'selectpage',
                    extend: 'data-source="department/index/index" data-field="name"',
                    operate: 'in',
                    formatter: Table.api.formatter.search
                },
                {
                    field: 'dadmin',
                    title: __('Department'),
                    formatter: function (value, row, index) {
                        if (value.length == 0)
                            return '-' ;
                        var department="";
                        $.each(value,function(i,v){  //arrTmp数组数据
                            if (v.department){
                                department+=department?','+v.department.name:v.department.name;
                            }
                        });
                        return  Table.api.formatter.flag.call(this, department, row, index);
                    }
                    , operate: false
                    , visible: false
                },
            ];

            columnss.push(
                {
                    field: 'admindata.recharge_amount', 
                    title: __('用户总数 || 充值用户'), 
                    operate: false,
                    formatter: function (value, row, index) {
                        return row.user_count + ' || ' + row.user_recharge_count;
                    }
                },
                {field: 'admindata.recharge_amount', title: __('充值金额'), operate: false, sortable: true},
                {field: 'admindata.withdraw_amount', title: __('提现金额'), operate: false, sortable: true},
                {field: 'admindata.send_amount', title: __('发放工资'), operate: false, sortable: true},
                {
                    field: 'admindata.quota', 
                    title: __('额度'), 
                    operate: false, 
                    sortable: true,
                    formatter: function (value, row, index) {
                        let quota = value ?? '-';
                        return '<input type="text" class="form-control text-center text-weigh" data-id="' + row.id + '" value="' + quota + '" style="width:80px;margin:0 auto;" />';
                    },
                    events: {
                        "dblclick .text-weigh": function (e) {
                            e.preventDefault();
                            e.stopPropagation();
                            return false;
                        }
                    }
                },
                {field: 'remark', title: __('备注'), operate: false},
                {field: 'logintime', title: __('Login time'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true, visible: false},
                {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                    buttons: [
                        {
                            name: 'principal',
                            text: __('Principal'),
                            title: __('Principal set'),
                            icon: 'fa fa-street-view',
                            classname: 'btn btn-xs btn-danger btn-dialog',
                            url: 'department/admin/principal',
                        },
                    ],
                    formatter: function (value, row, index) {
                        if(row.id == Config.admin.id){
                            return '';
                        }
                        return Table.api.formatter.operate.call(this, value, row, index);
                    }});

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                columns: [columnss],
                //启用固定列
                fixedColumns: true,
                //固定右侧列数
                fixedRightNumber: 1,
            });
            

            // 为表格绑定事件
            Table.api.bindevent(table);
            table.off('dbl-click-row.bs.table'); // 取消双击打开编辑的功能
            require(['jstree'], function () {
                //全选和展开
                $(document).on("click", "#checkall", function () {
                    $("#departmenttree").jstree($(this).prop("checked") ? "check_all" : "uncheck_all");
                });
                $(document).on("click", "#expandall", function () {
                    $("#departmenttree").jstree($(this).prop("checked") ? "open_all" : "close_all");
                });
                $('#departmenttree').on("changed.jstree", function (e, data) {
                    console.log(data.selected.join(","));
                    $(".commonsearch-table input[name=department_id]").val(data.selected.join(","));
                    table.bootstrapTable('refresh', {});
                    return false;
                });
                $('#departmenttree').jstree({
                    "themes": {
                        "stripes": true
                    },
                    "checkbox": {
                        "keep_selected_style": false,
                    },
                    "types": {
                        "channel": {
                            "icon": false,
                        },
                        "list": {
                            "icon": false,
                        },
                        "link": {
                            "icon": false,
                        },
                        "disabled": {
                            "check_node": false,
                            "uncheck_node": false
                        }
                    },
                    'plugins': ["types", "checkbox"],
                    "core": {
                        "multiple": true,
                        'check_callback': true,
                        "data": Config.departmentList
                    }
                });
            });

            $(document).on("change", ".text-weigh", function () {
                $(this).data("params", {weigh: $(this).val()});
                // Fast.api.ajax('department/admin/quota', [$(this).data("id")], table, this);
                Fast.api.ajax({
                    url: 'department/admin/quota',
                    data: {ids: $(this).data("id"), quota: $(this).val()},
                }, function(){
                    $(".btn-refresh").trigger("click");
                });
                return false;
            });
        },
        add: function () {
            $(document).on("click", ".send", function(){
                let chat_id = $('#chat_id').val();
                if(chat_id == ''){
                    Toastr.error('请填写飞机ID');
                    return false;
                }
                Fast.api.ajax({
                    url: 'department/admin/send',
                    data: {chat_id: chat_id},
                }, function(data){
                    if(data.code == 1){
                        Toastr.success('发送成功');
                    }
                })
            })
            
            Controller.api.bindevent();
        },
        principal:function(){
            Controller.api.bindevent();
        },
        edit: function () {
            $(document).on("click", ".send", function(){
                let chat_id = $('#chat_id').val();
                if(chat_id == ''){
                    Toastr.error('请填写飞机ID');
                    return false;
                }
                Fast.api.ajax({
                    url: 'department/admin/send',
                    data: {chat_id: chat_id},
                }, function(data){
                    if(data.code == 1){
                        Toastr.success('发送成功');
                    }
                })
            })

            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
                $(document).on("change", "#department_ids", function(){
                    //变更后的回调事件
                    var dname=$(this).find("option:selected").first().text()
                    var nickname=$("#nickname").val();
                    var a = nickname.indexOf("-");

                    if (a!=-1){
                        nickname=nickname.substring(0, a);
                    }
                    dname = dname.replace(/\s*/g,"");
                    nickname+="-"+dname.replace(/&nbsp;|│|└|├\s*/ig, "");
                    $("#nickname").val(nickname);
                });
            },
        }



    };
    return Controller;
});

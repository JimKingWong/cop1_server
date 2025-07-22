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
            omgrecord: function () {
          
                var omgrecord_table = $("#omgrecord_table");

                // 初始化表格
                omgrecord_table.bootstrapTable({
                    url: 'game/record/omgrecord',
                    pk: 'id',
                    toolbar: '#toolbar1',
                    sortName: 'weigh',
                    fixedColumns: true,
                    searchFormVisible: true,
                    search: false,
                    fixedRightNumber: 1,
                    columns: [
                        [
                            {field: 'user_id', title: __('UID'), formatter: Table.api.formatter.search},
                            {field: 'game_id', title: __('游戏ID'), class: 'autocontent', formatter: Table.api.formatter.search},
                            {field: 'image', title: __('游戏ICON'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                            {field: 'transaction_id', title: __('交易ID')},
                            {field: 'transfer_amount', title: __('输赢金额'), operate: false},
                            {field: 'bet_amount', title: __('下注金额'), operate: false},
                            {field: 'win_amount', title: __('派彩金额'), operate: false},
                            {field: 'balance', title: __('下注完余额'), operate: false},
                            {field: 'platform', title: __('厂商'), searchList: {"1":__('Spribe'),"2":__('PG'),"3":__('JILI'),"4":__('PP'),"5":__('OMG_MINI'),"6":__('MiniGame'),"7":__('OMG_CRYPTO'),"8":__('Hacksaw'),"23":__('TADA'),"24":__('CP'), '25': 'ASKME'}, formatter: Table.api.formatter.normal},

                            // {field: 'type', title: __('Type'), searchList: {"0":__('Type 0'),"1":__('Type 1'),"2":__('Type 2'),"3":__('Type 3')}, formatter: Table.api.formatter.flag},
                        
                            {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        ]
                    ],
                });

                // 为表格绑定事件
                Table.api.bindevent(omgrecord_table);
            },

            jdbrecord: function () {
          
                var jdbrecord_table = $("#jdbrecord_table");

                // 初始化表格
                jdbrecord_table.bootstrapTable({
                    url: 'game/record/jdbrecord',
                    pk: 'id',
                    toolbar: '#toolbar2',
                    sortName: 'weigh',
                    fixedColumns: true,
                    searchFormVisible: true,
                    search: false,
                    fixedRightNumber: 1,
                    columns: [
                        [
                            {field: 'user_id', title: __('UID'), formatter: Table.api.formatter.search},
                            {field: 'game_id', title: __('游戏ID'), class: 'autocontent', formatter: Table.api.formatter.search},
                            {field: 'image', title: __('游戏ICON'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                            {field: 'transaction_id', title: __('交易ID')},
                            {field: 'transfer_amount', title: __('输赢金额'), operate: false},
                            {field: 'bet_amount', title: __('下注金额'), operate: false},
                            {field: 'win_amount', title: __('派彩金额'), operate: false},
                            {field: 'balance', title: __('下注完余额'), operate: false},

                            {field: 'platform', title: __('厂商'), searchList: {"1":__('JDB'),"2":__('SPRIBE'), "11":__('AMB'), "13":__('SMARTSOFT')}, formatter: Table.api.formatter.normal},
                            // {field: 'platform', title: __('厂商'), searchList: {"1":__('JDB'),"2":__('SPRIBE'),"3":__('GTF'),"4":__('FC'),"5":__('HRG'),"6":__('YB'),"7":__('MANCALA'),"8":__('ONLYPLAY'),"9":__('INJOY'),"10":__('CREEDROOMZ'), "11":__('AMB'),"12":__('ZESTPLAY'),"13":__('SMARTSOFT'),"14":__('FUNKY GAMES'),"15":__('SWGS'),"16":__('AVIATRIX')}, formatter: Table.api.formatter.normal},

                        
                            {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        ]
                    ],
                });

                // 为表格绑定事件
                Table.api.bindevent(jdbrecord_table);
            },
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});

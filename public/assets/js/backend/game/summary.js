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
                    url: 'game/summary/omgsummary',
                    pk: 'id',
                    toolbar: '#toolbar1',
                    sortName: 'weigh',
                    fixedColumns: true,
                    searchFormVisible: true,
                    search: false,
                    fixedRightNumber: 1,
                    columns: [
                        [
                            {field: 'platform', title: __('厂商'), searchList: {"1":__('Spribe'),"2":__('PG'),"3":__('JILI'),"4":__('PP'),"5":__('OMG_MINI'),"6":__('MiniGame'),"7":__('OMG_CRYPTO'),"8":__('Hacksaw'),"23":__('TADA'),"24":__('CP'), '25': 'ASKME'}},
                            {field: 'game_id', title: __('游戏ID'), class: 'autocontent', formatter: Table.api.formatter.search},
                            {field: 'game_name', title: __('游戏名字'), operate: false},
                            {field: 'image', title: __('游戏ICON'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                            {field: 'transfer_amount', title: __('输赢金额'), operate: false},
                            {field: 'bet_amount', title: __('下注金额'), operate: false},
                            {field: 'bet_count', title: __('注单数'), operate: false},
                            {field: 'win_amount', title: __('派彩金额'), operate: false},
                            {field: 'rtp_rate', title: __('游戏RTP'), operate: false},
                            {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, visible: false},
                        ]
                    ],
                    responseHandler: function (data) {
                        $("#bet_amount").html(data.extend.bet_amount);
                        $("#bet_count").html(data.extend.bet_count);
                        $("#transfer_amount").html(data.extend.transfer_amount);
                        $('#win_amount').html(data.extend.win_amount);

                        return data;
                    }
                });

                // 为表格绑定事件
                Table.api.bindevent(omgrecord_table);
            },

            jdbrecord: function () {
          
                var jdbrecord_table = $("#jdbrecord_table");

                // 初始化表格
                jdbrecord_table.bootstrapTable({
                    url: 'game/summary/jdbsummary',
                    pk: 'id',
                    toolbar: '#toolbar2',
                    sortName: 'weigh',
                    fixedColumns: true,
                    searchFormVisible: true,
                    search: false,
                    fixedRightNumber: 1,
                    columns: [
                        [
                            {field: 'platform', title: __('厂商'), searchList: {"1":__('JDB'),"2":__('SPRIBE'), "11":__('AMB'), "13":__('SMARTSOFT')}},
                            {field: 'game_id', title: __('游戏ID'), class: 'autocontent', formatter: Table.api.formatter.search},
                            {field: 'game_name', title: __('游戏名字'), operate: false},
                            {field: 'image', title: __('游戏ICON'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                            {field: 'transfer_amount', title: __('输赢金额'), operate: false},
                            {field: 'bet_amount', title: __('下注金额'), operate: false},
                            {field: 'bet_count', title: __('注单数'), operate: false},
                            {field: 'win_amount', title: __('派彩金额'), operate: false},
                            {field: 'rtp_rate', title: __('游戏RTP'), operate: false},
                            {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, visible: false},
                        ]
                    ],
                    responseHandler: function (data) {
                        $("#jdb_bet_amount").html(data.extend.bet_amount);
                        $("#jdb_bet_count").html(data.extend.bet_count);
                        $("#jdb_transfer_amount").html(data.extend.transfer_amount);
                        $('#jdb_win_amount').html(data.extend.win_amount);

                        return data;
                    }
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

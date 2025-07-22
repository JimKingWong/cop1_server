define(['jquery', 'bootstrap', 'backend', 'addtabs', 'table', 'echarts', 'echarts-theme', 'template', 'form'], function ($, undefined, Backend, Datatable, Table, Echarts, undefined, Template, Form) {

    var Controller = {
        index: function () {
           Form.events.selectpage($("form"));

           $(document).on("click", ".refreshs", function () {
                location.href = 'dashboard';
            });

           Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
           
            }   
        }
    };

    return Controller;
});

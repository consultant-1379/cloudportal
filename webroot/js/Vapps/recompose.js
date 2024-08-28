$(function() {
     var template_select_box = $('#template');
     var vm_select_box = $('#vmList');

     $('#catalog').change(function(){
        template_select_box.empty();
        vm_select_box.empty();
        if($('#catalog :selected').text() != "-- Select a Catalog --"){
          populate_templates($('#catalog :selected').text());
        }
      });

     $('#template').change(function(){
        vm_select_box.empty();
        if($('#template').val() != "loading" ){
            if($('#template').val() != "template"){
                populate_vms($('#template').val());
            }
        }
     });

    function populate_templates(catalog) {
        template_select_box.empty();
        template_select_box.append('<option value="loading">Loading... </option>');
        var sppPortalURL =  '/VappTemplates/index_api/catalog_name:' + catalog + '/.json';

        $.ajax({
            url: sppPortalURL,
            dataType: "json",
            success: function (json) {
                var template_names = [];
                template_select_box.empty();
                template_select_box.append('<option value="template" id="template">-- Select a Template --</option>');
                $.each(json.vapptemplates, function (i, item) {
                    template_select_box.append('<option value="' + item.vapptemplate_id + '" id="' + item.vapptemplate_name + '">' + item.vapptemplate_name + '</option>');
                    template_names.push(item.vapptemplate_name);
                });
                template_select_box.prop("disabled", false);
            },

            error: function (xhr, textStatus, errorThrown) {
                alert("Issue loading Event List: "+ (errorThrown ? errorThrown : xhr.status));
            }

        });
    }

    function populate_vms(template) {
        vm_select_box.empty();
        vm_select_box.append('<option value="loading">Loading... </option>');
        var sppPortalURL =  '/Vms/vapptemplate_index_api/vapp_template_id:' + template + '/.json'

        $.ajax({
            url: sppPortalURL,
            dataType: "json",
            success: function (json) {
                var vm_names = [];
                vm_select_box.empty();
                $.each(json, function (i, item) {
                    var name = item.name;
                    if( name.toString() != "master_gateway" ){
                        vm_select_box.append('<option value="' + item.vm_id + '" id="vm">' + item.name + '</option>');
                        vm_names.push(item.name);
                    }
                });
                vm_select_box.prop("disabled", false);
            },

            error: function (xhr, textStatus, errorThrown) {
                alert("Issue loading Event List: "+ (errorThrown ? errorThrown : xhr.status));
            }

        });
    }

    function setup() {
        template_select_box.empty();
        vm_select_box.empty();
        template_select_box.append('<option value="loading">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;</option>');
        vm_select_box.append('<option value="loading"></option>');
    }
   setup();

});

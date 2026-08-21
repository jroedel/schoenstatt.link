$(document).ready(function(){
    $roleTitleSelect = $("#roleTitleSelect").selectize({
        create: false,
        sortField: 'text'
    });
    $("form").submit(function(){
        $("input, select").each(function(index, obj){
            if($(obj).val() == "" || $(obj).val() == "false") {
                $(obj).remove();
            }
        });
    });
    $("#clear").click(function(){
        $("select, input[type=\"text\"]").each(function(index, obj){
            $(obj).val("");
        });
        $("input:checkbox:checked").each(function(index, obj){
            $(obj).val(function() {
                return this.defaultValue;
            });
        });
    });
});

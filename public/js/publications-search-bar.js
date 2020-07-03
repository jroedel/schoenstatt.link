$(document).ready(function(){
    $("form").submit(function(){
        $("input, select").each(function(index, obj){
            if($(obj).val() == "" || $(obj).val() == "false") {
                $(obj).remove();
            }
        });
    });
    $("select[name='inLanguage[]']").selectize({
        sortField: 'text'
    });
});
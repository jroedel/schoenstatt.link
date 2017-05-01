$(document).ready(function(){
    $categorySelect = $("#categorySelect").selectize({
        create: false
    });
    $statusSelect = $("#statusSelect").selectize({
        create: false
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
        $categorySelect[0].selectize.setValue(['bishop', 'priest', 'deacon', 'student', 'novice']);
        $statusSelect[0].selectize.setValue(['intern', 'extern', 'intern-exempt', 'extern-exempt', 'associated', 'collaborator', 'none']);
    });
});

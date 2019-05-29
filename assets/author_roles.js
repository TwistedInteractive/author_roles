jQuery(function(){
	var $ = jQuery;
	$("table.author_roles a").click(function(){
		var rel = $(this).attr("rel");
		$("div.sub").slideUp();
		$("td").removeClass("active");
		var match = $("div." + rel, $(this).parent().parent().next());
		$(this).parent().addClass("active");
		match.stop().slideToggle(250, function(){
			if(this.style.display == 'none')
			{
				$("td", $(this).parent().parent().prev()).removeClass("active");
			}
		});
		return false;
	});

	// Check all checkboxes:
	$("table.author_roles th span").each(function(){
		$(this).click(function(){
			var rel = $(this).attr("rel");
			if($("input[type=checkbox][name$='[" + rel + "]']:checked").length == 0)
			{
				$("input[type=checkbox][name$='[" + rel + "]']").attr("checked", "checked");
			} else {
				$("input[type=checkbox][name$='[" + rel + "]']").removeAttr("checked");
			}
		});		
	});

	// Filter:
	$("table.author_roles div.entries input[name$='[use_filter]']").each(function(){
		if($(this).attr("checked"))
		{
			$("div.filter", $(this).parent().parent()).show();
		}
	}).change(function(){
		if($(this).attr("checked"))
		{
			$("div.filter", $(this).parent().parent()).slideDown();
		} else {
			$("div.filter", $(this).parent().parent()).slideUp();
		}
	});
	
	if(typeof roles_hidden_fields != 'undefined')
	{
		for(i=0; i<roles_hidden_fields.length; i++)
		{
			var field_id = roles_hidden_fields[i];
			$("#field-" + field_id).hide();
		}
	}
});
document.addEventListener('DOMContentLoaded',function(){
	var select=document.querySelector('select[name="type"]');
	if(!select)return;
	var form=select.closest('form'),headings=form.querySelectorAll('h3');
	if(headings.length<2)return;
	var emailHeading=headings[0],apiHeading=headings[1];
	function toggleRange(start,end,show){var node=start;while(node&&node!==end){node.style.display=show?'':'none';node=node.nextElementSibling;}}
	function update(){var api=select.value==='api';toggleRange(emailHeading,apiHeading,!api);emailHeading.style.display=api?'none':'';apiHeading.style.display=api?'':'none';var node=apiHeading.nextElementSibling;while(node&&node.tagName!=='P'){node.style.display=api?'':'none';node=node.nextElementSibling;}}
	select.addEventListener('change',update);update();
});

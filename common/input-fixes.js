//fix textareas after html format
window.addEventListener("load", function (event) {

	//το μετατρέψαμε σε javascript
	var arr_elms = document.querySelectorAll("textarea");
	for (var i = 0; i < arr_elms.length; i++) {
		var s = arr_elms[i].value.replace(/^\s+|\s+$/g, '');
		arr_elms[i].value = s;
	}

	//here do the same as above for all inputs
	arr_elms = document.body.getElementsByTagName("input");
	elms_len = arr_elms.length;
	for (var i = 0; i < elms_len; i++) {

		s = arr_elms[i].value;
		s = s.replace(/(^\s*)|(\s*$)/gi, "");
		s = s.replace(/[ ]{2,}/gi, " ");
		s = s.replace(/\n /, "\n");
		arr_elms[i].value = s;
	}
});
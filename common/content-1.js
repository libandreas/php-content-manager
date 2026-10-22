document.addEventListener("DOMContentLoaded", function () {
	var previewLinks = document.querySelectorAll(".uploader-preview-link[href]");
	for (var i = 0; i < previewLinks.length; i++) {
		previewLinks[i].addEventListener("click", function (event) {
			event.preventDefault();

			var href = this.getAttribute("href");
			if (!href) {
				return;
			}

			window.open(
				href,
				"_custom",
				"toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=635,height=510,left=50,top=50,titlebar=yes"
			);
		});
	}
});

document.addEventListener("DOMContentLoaded", function () {
	var uploaderInputs = document.querySelectorAll(".uploader-file-input");
	for (var i = 0; i < uploaderInputs.length; i++) {
		uploaderInputs[i].addEventListener("change", function () {
			if (this.form) {
				this.form.submit();
			}
		});
	}
});

document.addEventListener("DOMContentLoaded", function () {
	var locationSelects = document.querySelectorAll(".admin-location-select");
	for (var i = 0; i < locationSelects.length; i++) {
		locationSelects[i].addEventListener("change", function () {
			if (this.value) {
				window.location.href = this.value;
			}
		});
	}

	var deleteLinks = document.querySelectorAll(".js-confirm-delete");
	for (var j = 0; j < deleteLinks.length; j++) {
		deleteLinks[j].addEventListener("click", function (event) {
			if (!window.confirm("Press enter for delete")) {
				event.preventDefault();
			}
		});
	}

	var uploaderIframes = document.querySelectorAll(".uploader-iframe");
	for (var k = 0; k < uploaderIframes.length; k++) {
		uploaderIframes[k].addEventListener("load", function () {
			if (!this.contentWindow || !this.contentWindow.document) {
				return;
			}

			var docEl = this.contentWindow.document.documentElement;
			if (!docEl) {
				return;
			}

			this.style.height = docEl.scrollHeight + "px";
		});
	}
});

function resizeTextareaField(textarea) {
	if (!textarea) {
		return;
	}

	textarea.style.height = "auto";
	textarea.style.height = textarea.scrollHeight + "px";
}

document.addEventListener("DOMContentLoaded", function () {
	var autoResizeTextareas = document.querySelectorAll(".js-auto-resize");
	for (var i = 0; i < autoResizeTextareas.length; i++) {
		resizeTextareaField(autoResizeTextareas[i]);
		autoResizeTextareas[i].addEventListener("input", function () {
			resizeTextareaField(this);
		});
	}
});

document.addEventListener("DOMContentLoaded", function () {
	if (typeof Quill === "undefined") {
		return;
	}

	var BlockEmbed = Quill.import("blots/block/embed");
	class LineBreak extends BlockEmbed { }
	LineBreak.blotName = "break";
	LineBreak.tagName = "br";
	Quill.register(LineBreak);

	class DividerBlot extends BlockEmbed { }
	DividerBlot.blotName = "divider";
	DividerBlot.tagName = "hr";
	Quill.register(DividerBlot);

	var icons = Quill.import('ui/icons');
	icons['divider'] = '<svg viewBox="0 0 18 18"> <line class="ql-stroke" x1="3" x2="15" y1="9" y2="9"></line> </svg>';

	var quillEditors = window.__cms1QuillEditors || {};
	window.__cms1QuillEditors = quillEditors;

	var quillContainers = document.querySelectorAll(".quill-editor-container[data-target-id]");
	var initializedTargets = {};

	for (var i = 0; i < quillContainers.length; i++) {
		var container = quillContainers[i];
		var targetId = container.getAttribute("data-target-id");
		var textarea = document.getElementById(targetId);

		if (!textarea || initializedTargets[targetId]) {
			continue;
		}
		initializedTargets[targetId] = true;

		textarea.value = textarea.value.trim();

		var quill = new Quill(container, {
			theme: "snow",
			modules: {
				toolbar: {
					container: [
						['bold', 'italic', 'underline', 'strike'],
						['divider'],
						[{ 'list': 'ordered' }, { 'list': 'bullet' }],
						[{ 'header': [1, 2, 3, 4, 5, 6, false] }],
						[{ 'color': [] }, { 'background': [] }],
						[{ 'align': [] }],
						['link'],
						['clean']
					],
					handlers: {
						divider: function () {
							var range = this.quill.getSelection(true);
							this.quill.insertText(range.index, '\n', Quill.sources.USER);
							this.quill.insertEmbed(range.index + 1, 'divider', true, Quill.sources.USER);
							this.quill.setSelection(range.index + 2, Quill.sources.SILENT);
						}
					}
				}
			}
		});

		quill.root.innerHTML = textarea.value;

		(function (q, ta) {
			q.on("text-change", function () {
				ta.value = q.root.innerHTML;
			});
		})(quill, textarea);

		quillEditors[targetId] = quill;

		if (textarea.disabled || textarea.classList.contains("disabled")) {
			quill.enable(false);
		}
	}

});

window.addEventListener("load", function () {
	var textareas = document.querySelectorAll("textarea:not(.quill-html-field)");
	for (var i = 0; i < textareas.length; i++) {
		textareas[i].value = textareas[i].value.replace(/^\s+|\s+$/g, "");
		resizeTextareaField(textareas[i]);
	}

	var inputs = document.body.getElementsByTagName("input");
	for (var j = 0; j < inputs.length; j++) {
		if ((inputs[j].type || "").toLowerCase() === "file") {
			continue;
		}

		var s = inputs[j].value;
		s = s.replace(/(^\s*)|(\s*$)/gi, "");
		s = s.replace(/[ ]{2,}/gi, " ");
		s = s.replace(/\n /, "\n");
		inputs[j].value = s;
	}
});

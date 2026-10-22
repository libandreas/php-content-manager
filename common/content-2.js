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
	var categorySelect = document.getElementById("category-select");
	var categoryInput = document.getElementById("category");
	if (categorySelect && categoryInput) {
		categorySelect.addEventListener("change", function () {
			var selectedOption = this.options[this.selectedIndex];
			categoryInput.value = selectedOption ? selectedOption.text : "";
		});
	}

	var pushCategoryButton = document.getElementById("push-category-button");
	var mainForm = document.getElementById("myForm");
	if (pushCategoryButton && mainForm) {
		pushCategoryButton.addEventListener("click", function (event) {
			event.preventDefault();

			if (typeof mainForm.requestSubmit === "function") {
				mainForm.requestSubmit(pushCategoryButton);
				return;
			}

			var hiddenField = document.createElement("input");
			hiddenField.type = "hidden";
			hiddenField.name = pushCategoryButton.name;
			hiddenField.value = pushCategoryButton.value || "1";
			mainForm.appendChild(hiddenField);
			mainForm.submit();
		});
	}

	var uploaderIframes = document.querySelectorAll(".uploader-iframe");
	for (var i = 0; i < uploaderIframes.length; i++) {
		uploaderIframes[i].addEventListener("load", function () {
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

	var quillContainers = document.querySelectorAll(".quill-editor-container[data-target-id]");
	for (var i = 0; i < quillContainers.length; i++) {
		var container = quillContainers[i];
		var targetId = container.getAttribute("data-target-id");
		var textarea = document.getElementById(targetId);

		if (!textarea) {
			continue;
		}

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
						['link', 'image', 'video'],
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

		quill.clipboard.dangerouslyPasteHTML(textarea.value, 'silent');

		(function (q, ta) {
			q.on("text-change", function () {
				ta.value = q.root.innerHTML;
			});
		})(quill, textarea);

		if (textarea.disabled || textarea.classList.contains("disabled")) {
			quill.enable(false);
		}
	}
});

window.addEventListener("load", function () {
	var textareas = document.querySelectorAll("textarea");
	for (var i = 0; i < textareas.length; i++) {
		textareas[i].value = textareas[i].value.replace(/^\s+|\s+$/g, "");
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

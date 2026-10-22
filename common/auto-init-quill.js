// Function to check if a string contains HTML tags
function containsHTML(str) {

	// Check if the string contains any HTML tags using a regex pattern
	const htmlTagPattern = /<\/?[a-z][\s\S]*>/i;
	return htmlTagPattern.test(str);
}

function initializeQuillEditors() {
	// Get all textareas on the page
	const textareas = document.querySelectorAll('textarea');

	textareas.forEach(function (textarea) {
		// Check if the textarea has already been initialized
		if (textarea.classList.contains('quill-initialized')) {
			return; // Skip this textarea if it's already initialized
		}

		// Trim the textarea's value
		textarea.value = textarea.value.trim();

		// Check if the textarea's value contains HTML
		if (containsHTML(textarea.value)) {
			// Create a container div for the Quill editor
			const quillContainer = document.createElement('div');
			quillContainer.classList.add('quill-container');

			// Insert the Quill container before the textarea
			textarea.parentNode.insertBefore(quillContainer, textarea);

			// Hide the original textarea
			textarea.style.display = 'none';

			// Define a custom blot for line breaks
			const BlockEmbed = Quill.import('blots/block/embed');
			class LineBreak extends BlockEmbed { }
			LineBreak.blotName = 'break';
			LineBreak.tagName = 'br';
			Quill.register(LineBreak);

			// Initialize Quill editor on the container
			const quill = new Quill(quillContainer, {
				theme: 'snow', // or 'bubble'
				modules: {
					toolbar: true // You can customize the toolbar options here
				},
				formats: ['break'] // Add 'break' to formats
			});

			// Set initial content
			quill.root.innerHTML = textarea.value;

			// Update textarea value on Quill content change
			quill.on('text-change', function () {
				textarea.value = quill.root.innerHTML;
			});

			// Check if the textarea has the 'disabled' attribute or class
			if (textarea.disabled || textarea.classList.contains('disabled')) {
				// Disable the Quill editor
				quill.enable(false);
			}

			// Mark the textarea as initialized
			textarea.classList.add('quill-initialized');
		}
	});
}

// Add Quill CSS
if (!document.querySelector('link[href*="quill.snow.css"]')) {
	const quillCSS = document.createElement('link');
	quillCSS.rel = 'stylesheet';
	quillCSS.href = 'https://cdn.quilljs.com/1.3.6/quill.snow.css';
	document.head.appendChild(quillCSS);
}

// Add Quill JS
if (!document.querySelector('script[src*="quill.min.js"]')) {
	const quillScript = document.createElement('script');
	quillScript.src = 'https://cdn.quilljs.com/1.3.6/quill.min.js';
	document.head.appendChild(quillScript);
}

// On window load, initialize Quill editors
document.addEventListener('DOMContentLoaded', function () {

	initializeQuillEditors();
});
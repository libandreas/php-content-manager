/**
 * CONTENT-8.JS: Monaco Editor Initialization
 * Purpose: Initializes Monaco Editor on the page with auto-language detection based on file extension
 * 
 * @deprecated This program is no longer actively used or maintained
 */
require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.21.2/min/vs' } });
require(['vs/editor/editor.main'], function () {
	// Hide the textarea only if the Monaco Editor is loaded
	document.getElementById('editorContent').style.display = 'none';

	var initialContent = document.getElementById('editorContent').value.trim();

	// Get the 'f' parameter from the URL
	var urlParams = new URLSearchParams(window.location.search);
	var fileParam = urlParams.get('f') || 'example.js'; // Default to 'example.js' if 'f' is not present
	fileParam = fileParam.replace(/^\/+/, ''); // Remove leading slashes

	// Create a model with auto-detected language based on the 'f' parameter
	var model = monaco.editor.createModel(initialContent, undefined, monaco.Uri.parse('file:///' + fileParam));

	var editor = monaco.editor.create(document.getElementById('editor'), {
		model: model,
		fontSize: 13,
		wordWrap: 'on',
		automaticLayout: true,
		scrollBeyondLastLine: false,
		minimap: { enabled: true }, // Enable the minimap
		contextmenu: false // Disable the internal context menu
	});

	// Synchronize the content of the Monaco Editor with the hidden textarea
	document.querySelector('form').addEventListener('submit', function () {
		document.getElementById('editorContent').value = editor.getValue();
	});
}, function (error) {
	// Handle the error if the Monaco Editor fails to load
	console.error('Monaco Editor failed to load:', error);
	// Optionally, you can display the textarea if the editor fails to load
	document.getElementById('editorContent').style.display = 'block';
});
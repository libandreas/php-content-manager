// Helper to determine the correct error logging URL
function getErrorLoggingUrl() {
	const protocol = window.location.protocol;
	if (protocol === 'chrome-extension:' || protocol === 'vscode-webview:' || protocol === 'file:') {
		const chatHost = document.getElementById('chat-host');
		if (chatHost && chatHost.value) {
			return chatHost.value.replace(/\/+$/, '') + ERROR_LOG_ENDPOINT;
		}
	}
	return ERROR_LOG_ENDPOINT;
}

// Function to send error to server
function sendErrorToServer(type, message, source = '', line = 0, column = 0, stack = '') {
	// Don't log if running locally (same machine)
	const hostname = window.location.hostname;
	if (hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '[::1]') {
		return;
	}

	// Don't check source for manual logs (log/warn/error)
	const errorData = {
		type: type,
		message: String(message),
		source: source,
		line: line,
		column: column,
		stack: stack
	};

	// Send to server
	const formData = new FormData();
	formData.append('js_error', JSON.stringify(errorData));

	fetch(getErrorLoggingUrl(), {
		method: 'POST',
		body: formData
	}).catch(err => {
		// Silently fail if logging fails
		originalConsoleReporting.error('Failed to log error to server:', err);
	});
}

// Helper to capture stack trace
function captureStack() {
	try {
		throw new Error();
	} catch (e) {
		let stack = e.stack || '';
		// Split into lines
		let lines = stack.split('\n');

		// Remove the 'Error' header line if present (Chrome/V8)
		if (lines.length > 0 && lines[0].trim() === 'Error') {
			lines.shift();
		}

		// Remove the first 2 frames:
		// 1. matchErrorsStack
		// 2. console.log/warn/error wrapper
		if (lines.length > 2) {
			lines.splice(0, 2);
		}

		return lines.join('\n');
	}
}

// Common browser/extension warnings to ignore (not real app errors)
window.__ERH_IGNORED_ERROR_PATTERNS__ = window.__ERH_IGNORED_ERROR_PATTERNS__ || [
	'ResizeObserver loop',                    // Benign browser warning
	'Extension context invalidated',          // Extension reload/update
	'Script error.',                          // Cross-origin errors (no details available)
	'message channel closed',                 // Chrome extension messaging
	'The message port closed',                // Chrome extension messaging
	'A listener indicated an asynchronous response', // Chrome extension callback
];

// Helper to check if message should be ignored
function shouldIgnoreError(message) {
	const patterns = window.__ERH_IGNORED_ERROR_PATTERNS__;
	for (const pattern of patterns) {
		if (message.includes(pattern)) {
			return true;
		}
	}
	return false;
}

// Endpoint for sending JavaScript errors to server
const ERROR_LOG_ENDPOINT = '/common/errors-handler.php';

// Store original console methods
const originalConsoleReporting = {
	log: console.log,
	warn: console.warn,
	error: console.error
};

// Override console.warn - writes to console AND sends to server
console.warn = function (...args) {
	// First: write to console normally (not hidden!)
	originalConsoleReporting.warn.apply(console, args);

	// Then: send to server in parallel
	const message = args.map(arg =>
		typeof arg === 'object' ? JSON.stringify(arg) : String(arg)
	).join(' ');

	// Check if should be ignored
	if (shouldIgnoreError(message)) {
		return;
	}

	const stack = captureStack();

	// Only show confirmation popup instead of auto-sending
	sendErrorToServer('warn', message, '', 0, 0, stack);
};

// Override console.error - writes to console AND sends to server
console.error = function (...args) {
	// First: write to console normally (not hidden!)
	originalConsoleReporting.error.apply(console, args);

	// Then: send to server in parallel
	const message = args.map(arg =>
		typeof arg === 'object' ? JSON.stringify(arg) : String(arg)
	).join(' ');

	// Check if should be ignored
	if (shouldIgnoreError(message)) {
		return;
	}

	const stack = captureStack();

	// Only show confirmation popup instead of auto-sending
	sendErrorToServer('error', message, '', 0, 0, stack);
};

// Catch uncaught errors
window.addEventListener('error', function (event) {
	const message = event.message || 'Unknown error';

	// Check if should be ignored
	if (shouldIgnoreError(message)) {
		return;
	}

	const source = event.filename || '';
	const line = event.lineno || 0;
	const column = event.colno || 0;
	const stack = event.error && event.error.stack ? event.error.stack : '';

	// Show confirmation popup
	sendErrorToServer('uncaught error', message, source, line, column, stack);
});

// Catch unhandled promise rejections
window.addEventListener('unhandledrejection', function (event) {
	const message = event.reason ? String(event.reason) : 'Unhandled promise rejection';

	// Check if should be ignored
	if (shouldIgnoreError(message)) {
		return;
	}

	let stack = '';

	if (event.reason && event.reason.stack) {
		stack = event.reason.stack;
	}

	sendErrorToServer('unhandled rejection', message, '', 0, 0, stack);
});
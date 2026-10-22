function rel2abs_in_html(html, baseUrl) {
	baseUrl = baseUrl.replace(/www\./i, '');
	html = html.replace(/content: \(/g, 'content: url(');
	html = html.replace(/background-image: \(/g, 'background-image: url(');

	function isFullUrl(url) {
		return /^([a-z][a-z0-9+\-.]*:)?\/\//i.test(url);
	}

	function shouldSkipUrl(url) {
		return !url
			|| /^\s*$/.test(url)
			|| /^#/i.test(url)
			|| /^(data|javascript|mailto|tel|blob|about):/i.test(url);
	}

	function rel2abs(rel, base) {
		try {
			return new URL(rel, base).href;
		} catch {
			return rel;
		}
	}

	function looksLikeRelativeUrl(url, attr) {
		if (shouldSkipUrl(url) || isFullUrl(url)) {
			return false;
		}

		if (attr === 'content') {
			return /^(\/|\.\/|\.\.\/|[^\s?#]+\.[a-z0-9]{1,8}(?:[?#].*)?|\?)/i.test(url);
		}

		return true;
	}

	function absolutize(url, attr) {
		if (!looksLikeRelativeUrl(url, attr)) {
			return url;
		}

		return rel2abs(url, baseUrl);
	}

	html = html.replace(
		/(src|action|background|content|poster)\s*=\s*(['"])([^'"]+)\2/gi,
		function (match, attr, quote, url) {
			return attr + '=' + quote + absolutize(url, attr.toLowerCase()) + quote;
		}
	);

	html = html.replace(
		/(href)\s*=\s*(['"])([^'"]+)\2/gi,
		function (match, attr, quote, url) {
			return attr + '=' + quote + absolutize(url, attr.toLowerCase()) + quote;
		}
	);

	html = html.replace(
		/(srcset)\s*=\s*(['"])([^'"]+)\2/gi,
		function (match, attr, quote, value) {
			const updatedValue = value
				.split(',')
				.map(function (candidate) {
					const trimmedCandidate = candidate.trim();
					if (!trimmedCandidate) {
						return trimmedCandidate;
					}

					const parts = trimmedCandidate.split(/\s+/);
					parts[0] = absolutize(parts[0], attr.toLowerCase());
					return parts.join(' ');
				})
				.join(', ');

			return attr + '=' + quote + updatedValue + quote;
		}
	);

	html = html.replace(
		/url\((['"]?)([^'")]+)\1\)/gi,
		function (match, quote, url) {
			const absoluteUrl = absolutize(url, 'css-url');
			return 'url(' + quote + absoluteUrl + quote + ')';
		}
	);

	return html;
}

// Function to load the document from the provided URL and convert relative URLs to absolute
function loadDocumentFromUrlForControlPanel() {
	// Get the 't' parameter from the URL (this is now a path only, not full URL)
	const urlParams = new URLSearchParams(window.location.search);
	let targetPath = urlParams.get('t');
	if (!targetPath) {
		console.error('No target URL provided.');
		return;
	}

	// Build the full URL handling both absolute URLs and relative paths
	let targetUrl;
	try {
		targetUrl = new URL(targetPath, window.location.origin).href;
	} catch (e) {
		console.error('Invalid target URL:', targetPath);
		return;
	}

	const loadingDiv = document.createElement('div');
	loadingDiv.id = 'loading-indicator';
	loadingDiv.innerText = 'Loading...';
	loadingDiv.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);font-size:24px;';
	document.body.appendChild(loadingDiv);
	const existingFloatingTools = document.getElementById('floating-tools-div');
	const xhr = new XMLHttpRequest();
	xhr.withCredentials = false;
	xhr.open('GET', targetUrl, false);
	xhr.send();
	if (xhr.status === 200) {
		// Convert all relative URLs to absolute using rel2abs_in_html
		const absHtml = rel2abs_in_html(xhr.responseText, targetUrl);
		const parser = new DOMParser();
		const newDoc = parser.parseFromString(absHtml, 'text/html');
		const newFloatingTools = newDoc.querySelector('#floating-tools-div');
		if (newFloatingTools) {
			newFloatingTools.remove();
		}
		const scripts = newDoc.getElementsByTagName('script');
		while (scripts.length > 0) {
			scripts[0].remove();
		}
		const allElements = newDoc.getElementsByTagName('*');
		const inlineEvents = ['onclick', 'onblur', 'onchange', 'ondblclick', 'onfocus', 'onkeydown', 'onkeypress', 'onkeyup', 'onload', 'onmousedown', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onreset', 'onselect', 'onsubmit', 'onunload', 'oncontextmenu', 'oninput', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onscroll', 'onwheel', 'oncopy', 'oncut', 'onpaste'];
		for (let element of allElements) {
			const attrs = element.attributes;
			for (let i = attrs.length - 1; i >= 0; i--) {
				const attrName = attrs[i].name.toLowerCase();
				if (inlineEvents.includes(attrName) || (attrName === 'href' && attrs[i].value.toLowerCase().startsWith('javascript:'))) {
					element.removeAttribute(attrs[i].name);
				}
			}
		}
		document.documentElement.innerHTML = '';
		document.write(newDoc.documentElement.innerHTML);
		document.close();
		if (existingFloatingTools) {
			document.body.appendChild(existingFloatingTools);
		}
		const loadingIndicator = document.getElementById('loading-indicator');
		if (loadingIndicator) {
			loadingIndicator.remove();
		}
	} else {
		console.error(xhr.status);
	}
}

function applyDebugOutlines() {
	const style = document.createElement('style');
	const urlParams = new URLSearchParams(window.location.search);
	const mode = urlParams.get('mode');
	const outlineColor = mode === 'all' ? 'red' : 'royalblue';
	const css = `
		img:not(#floating-tools-div img),
		a:not(#floating-tools-div a),
		p:not(#floating-tools-div p),
		ul:not(#floating-tools-div ul),
		h1:not(#floating-tools-div h1),
		h2:not(#floating-tools-div h2),
		h3:not(#floating-tools-div h3),
		h4:not(#floating-tools-div h4) {
			outline: 1px dashed ${outlineColor} !important;
		}
	`;
	style.textContent = css;
	document.head.appendChild(style);
}

function loadFroalaCDN(callback) {
	const head = document.head;

	// Create link element for Froala CSS
	const link = document.createElement('link');
	link.rel = 'stylesheet';
	link.href = 'https://cdnjs.cloudflare.com/ajax/libs/froala-editor/4.0.10/css/froala_editor.pkgd.min.css';
	head.appendChild(link);

	// Create script element for Froala JS
	const script = document.createElement('script');
	script.src = 'https://cdnjs.cloudflare.com/ajax/libs/froala-editor/4.0.10/js/froala_editor.pkgd.min.js';
	script.onload = function () {
		// After Froala JS is loaded, load the Embedly plugin
		const embedlyScript = document.createElement('script');
		embedlyScript.src = 'https://cdn.jsdelivr.net/npm/froala-editor@latest/js/third_party/embedly.min.js';
		embedlyScript.onload = callback; // Call the callback once the plugin is loaded
		embedlyScript.onerror = function () {
			console.warn('Embedly plugin failed to load.');
			callback(); // Proceed even if the plugin fails to load
		};
		head.appendChild(embedlyScript);
	};
	script.onerror = function () {
		// If Froala JS fails to load, make the body content-editable
		document.body.contentEditable = 'true';
		console.warn('Froala Editor CDN failed to load. Falling back to contentEditable.');
	};
	head.appendChild(script);
}

function removeLonelyBrTags() {
	// Get all elements in the document body
	const elements = document.body.getElementsByTagName('*');

	// Iterate over each element
	Array.from(elements).forEach(element => {
		// Check if the element's only child is a <br> tag
		if (element.children.length === 1 && element.children[0].tagName === 'BR') {
			// Remove the <br> tag
			element.removeChild(element.children[0]);
		}
	});
}

function initializeFroalaEditor() {

	// Initialize the Froala Editor on the body element
	new FroalaEditor('body', {

		htmlDoNotWrapTags: ['#save-live-changes'],
		htmlAllowedEmptyTags: ['#save-live-changes'],
		toolbarInline: true, // Enables balloon mode
		toolbarVisibleWithoutSelection: true, // Show toolbar without text selection
		imageUpload: true,
		imageUploadMethod: 'base64', // Use Base64 for image upload
		imageAllowedTypes: ['jpeg', 'jpg', 'png', 'gif'], // Allowed image types
		toolbarButtons: [
			'undo', 'redo',
			'bold', 'italic', 'underline',
			'insertImage', 'insertLink', 'insertTable',
			'formatOL', 'formatUL', 'outdent', 'indent',
			'emoticons', 'specialCharacters', 'insertHR',
			'embedly'
		],
		htmlAllowedAttrs: ['.*'], // Allow all attributes
		htmlAllowedTags: ['.*'], // Allow all tags
		blankPageHtml: '', // Prevents inserting <br> when the editor is empty
		events: {
			'initialized': function () {
				// Call the function after the editor is initialized
				removeLonelyBrTags();

				// Exclude the element with ID 'floating-tools-div' from being editable
				const floatingToolsDiv = document.getElementById('floating-tools-div');
				if (floatingToolsDiv) {
					floatingToolsDiv.setAttribute('contentEditable', 'false');
				}
			},
			"image.beforeUpload": function (files) {
				var editor = this;
				if (files.length) {
					// Create a File Reader.
					var reader = new FileReader();
					// Set the reader to insert images when they are loaded.
					reader.onload = function (e) {
						var result = e.target.result;
						editor.image.insert(result, null, null, editor.image.get());
					};
					// Read image as base64.
					reader.readAsDataURL(files[0]);
				}
				editor.popups.hideAll();
				// Stop default upload chain.
				return false;
			}
		},
		// Disable character counter
		charCounterCount: false
	});

	// Add custom CSS to change the caret color
	const style = document.createElement('style');
	style.innerHTML = `

	`;
	document.head.appendChild(style);
}

// Function to get the XPath of a node
function getXPath(node) {
	if (node.nodeType === Node.DOCUMENT_NODE) {
		return '';
	}
	if (node.id) {
		return `//*[@id="${node.id}"]`;
	}
	let index = 1;
	let sibling = node.previousSibling;
	while (sibling) {
		if (sibling.nodeType === node.nodeType && sibling.nodeName === node.nodeName) {
			index++;
		}
		sibling = sibling.previousSibling;
	}
	let nodeName = node.nodeType === Node.TEXT_NODE ? 'text()' : node.nodeName.toLowerCase();
	return getXPath(node.parentNode) + '/' + nodeName + '[' + index + ']';
}

// Recursive function to walk through the nodes and compare them
function walk(oldNode, newNode, path, changes) {
	if (!oldNode && !newNode) {
		return;
	}

	const targetElements = ['p', 'ul', 'h1', 'h2', 'h3', 'h4', 'blockquote', 'marquee'];

	if (oldNode?.nodeType === Node.ELEMENT_NODE &&
		newNode?.nodeType === Node.ELEMENT_NODE &&
		(targetElements.includes(oldNode.nodeName.toLowerCase()) ||
			targetElements.includes(newNode.nodeName.toLowerCase()))) {

		if (oldNode.nodeName !== newNode.nodeName) {
			changes.push({
				type: 'replaced',
				path: getXPath(oldNode),
				node: serializeNode(newNode)
			});
			return;
		}

		let attrChanges = compareAttributes(oldNode, newNode);
		if (attrChanges.added.length > 0 || attrChanges.removed.length > 0 || attrChanges.modified.length > 0) {
			changes.push({
				type: 'attributes_changed',
				path: getXPath(oldNode),
				attributes: attrChanges
			});
		}

		if (oldNode.innerHTML !== newNode.innerHTML) {
			changes.push({
				type: 'replaced',
				path: getXPath(oldNode),
				node: serializeNode(newNode)
			});
		}
	}

	if (oldNode?.nodeType === Node.ELEMENT_NODE && newNode?.nodeType === Node.ELEMENT_NODE) {
		const oldChildren = Array.from(oldNode.childNodes);
		const newChildren = Array.from(newNode.childNodes);
		const maxLength = Math.max(oldChildren.length, newChildren.length);

		for (let i = 0; i < maxLength; i++) {
			walk(oldChildren[i], newChildren[i], path + '/' + i, changes);
		}
	}
}

// Function to compare attributes of two elements
function compareAttributes(oldElement, newElement) {

	let added = [];
	let removed = [];
	let modified = [];

	let oldAttrs = oldElement.attributes;
	let newAttrs = newElement.attributes;

	const filterFrClasses = (classValue) => {
		return classValue
			.split(' ')
			.filter(cls => !cls.startsWith('fr-'))
			.join(' ');
	};

	for (let i = 0; i < newAttrs.length; i++) {
		let name = newAttrs[i].name;
		let newValue = newAttrs[i].value;

		if (name === 'class') {
			newValue = filterFrClasses(newValue);
		}

		let oldValue = oldElement.getAttribute(name);
		if (name === 'class') {
			oldValue = oldValue ? filterFrClasses(oldValue) : null;
		}

		if (oldValue === null) {
			added.push({ name: name, value: newValue });
		} else if (oldValue !== newValue) {
			modified.push({ name: name, oldValue: oldValue, newValue: newValue });
		}
	}

	for (let i = 0; i < oldAttrs.length; i++) {
		let name = oldAttrs[i].name;
		let value = oldAttrs[i].value;

		if (name === 'class') {
			value = filterFrClasses(value);
		}

		if (!newElement.hasAttribute(name)) {
			removed.push({ name: name, value: value });
		}
	}

	const hasFrClassChange = () => {
		for (const attr of added) {
			if (attr.name === 'class') {
				const frClasses = newElement.getAttribute('class').split(' ').filter(cls => cls.startsWith('fr-'));
				if (frClasses.length > 0) return true;
			}
		}
		for (const attr of removed) {
			if (attr.name === 'class') {
				const frClasses = oldElement.getAttribute('class').split(' ').filter(cls => cls.startsWith('fr-'));
				if (frClasses.length > 0) return true;
			}
		}
		for (const attr of modified) {
			if (attr.name === 'class') {
				const oldFrClasses = attr.oldValue.split(' ').filter(cls => cls.startsWith('fr-'));
				const newFrClasses = attr.newValue.split(' ').filter(cls => cls.startsWith('fr-'));
				if (oldFrClasses.length !== newFrClasses.length) return true;
			}
		}
		return false;
	};

	if (hasFrClassChange()) {
		added = added.filter(attr => attr.name !== 'class');
		removed = removed.filter(attr => attr.name !== 'class');
		modified = modified.filter(attr => attr.name !== 'class');
	}

	return { added, removed, modified };
}

// Function to serialize a node for JSON output
function serializeNode(node) {

	if (!node) return null;

	if (node.nodeType === Node.TEXT_NODE || node.nodeType === Node.CDATA_SECTION_NODE) {
		return {
			nodeType: node.nodeType,
			textContent: node.textContent
		};
	}

	if (node.nodeType === Node.ELEMENT_NODE) {
		const filteredAttributes = Array.from(node.attributes)
			.filter(attr => {
				if (attr.name === 'class') {
					return !attr.value.split(' ').some(cls => cls.startsWith('fr-'));
				}
				return true;
			})
			.map(attr => ({
				name: attr.name,
				value: attr.value
			}));

		return {
			nodeType: node.nodeType,
			nodeName: node.nodeName,
			attributes: filteredAttributes,
			childNodes: Array.from(node.childNodes).map(child => serializeNode(child))
		};
	}

	return {
		nodeType: node.nodeType,
		nodeName: node.nodeName
	};
}

// Function to compare two HTML documents
function compareDocuments(oldNode, newNode) {


	let changes = [];

	const oldBody = oldNode.querySelector('body');
	const newBody = newNode.querySelector('body');

	const oldFloatingTools = oldBody?.querySelector('#floating-tools-div');
	const newFloatingTools = newBody?.querySelector('#floating-tools-div');
	oldFloatingTools?.remove();
	newFloatingTools?.remove();

	const oldHtml = document.implementation.createHTMLDocument('');
	oldHtml.body.appendChild(oldBody.cloneNode(true));
	const newHtml = document.implementation.createHTMLDocument('');
	newHtml.body.appendChild(newBody.cloneNode(true));

	if (oldHtml.body && newHtml.body) {
		walk(oldHtml.body, newHtml.body, '', changes);
	}

	return changes;
}

// this script runs only on content-4.php
window.addEventListener('load', function () {

	// Check if the current page is 'content-4.php' because we load that on translate cms
	const currentPage = window.location.pathname.split('/').pop();
	if (currentPage != 'content-4.php') {
		return;
	}

	// Load the document from the provided URL
	loadDocumentFromUrlForControlPanel();

	// Apply debug outlines
	applyDebugOutlines();

	// Load Froala Editor class from CDN
	if (typeof FroalaEditor === 'undefined') {
		loadFroalaCDN(initializeFroalaEditor);
	}
	else {
		initializeFroalaEditor();
	}

	let oldDoc;
	let isOldDocInitialized = false;

	// Continuously check and add event listener to the 'save-changes' button every 2 seconds
	setInterval(function () {
		// Initialize oldDoc if not done yet
		if (!isOldDocInitialized) {
			if (typeof FroalaEditor !== 'undefined' && FroalaEditor.INSTANCES && FroalaEditor.INSTANCES.length > 0) {
				oldDoc = new DOMParser().parseFromString(FroalaEditor.INSTANCES[0].html.get(), 'text/html');
				isOldDocInitialized = true;
			} else if (document.body.innerHTML) {
				oldDoc = new DOMParser().parseFromString(document.body.innerHTML, 'text/html');
				isOldDocInitialized = true;
			}
		}

		const saveChangesButton = document.getElementById('save-live-changes');
		if (saveChangesButton && !saveChangesButton.dataset.listenerAttached && isOldDocInitialized) {
			saveChangesButton.addEventListener('click', function () {

				// Show loading cursor
				const loadingCursor = document.createElement('div');
				loadingCursor.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.8);cursor:wait;z-index:9999;';
				document.body.appendChild(loadingCursor);

				// Get the new version of the document
				let newDoc;
				if (typeof FroalaEditor !== 'undefined' && FroalaEditor.INSTANCES && FroalaEditor.INSTANCES.length > 0) {
					newDoc = new DOMParser().parseFromString(FroalaEditor.INSTANCES[0].html.get(), 'text/html');
				} else {
					newDoc = new DOMParser().parseFromString(document.body.innerHTML, 'text/html');
				}

				// Run the compare function and get the JSON output
				const changes = compareDocuments(oldDoc, newDoc);

				// Prepare the data to send with proper formatting
				const formData = new FormData();
				formData.append('save-changes', 'true');
				formData.append('changes', JSON.stringify(changes));

				// Send the form data to the server via POST request
				fetch(window.location.href, {
					method: 'POST',
					body: formData
				})
					.then(response => {
						if (response.ok) {

							// Get the 't' parameter from the current URL for redirection (path only)
							const urlParams = new URLSearchParams(window.location.search);
							const redirectPath = urlParams.get('t');

							if (redirectPath) {
								// Handle both absolute URLs and relative paths
								try {
									window.location.href = new URL(redirectPath, window.location.origin).href;
								} catch (e) {
									window.location.href = redirectPath;
								}
							} else {
								// Fallback if 't' parameter was missing
								console.error("Redirect URL (from 't' parameter) not available. Reloading current page.");
								if (loadingCursor) loadingCursor.remove(); // Remove cursor before reload
								window.location.reload();
							}

						} else {
							console.error('Failed to save changes.');
						}
					})
					.catch(error => {
						console.error(error);
						alert('Failed to save changes. Please try again.');
					});
			});
			// Mark that the event listener has been attached
			saveChangesButton.dataset.listenerAttached = 'true';
		}
	}, 2000);
});

// this script runs only on pages ending with .html
document.addEventListener('DOMContentLoaded', function () {
	// Prevent infinite loop from document.write triggering DOMContentLoaded again
	if (window.contentReplacementInProgress) {
		return;
	}

	// Only run if current page path ends with .html (excluding query parameters)
	const currentPath = window.location.pathname;
	if (!currentPath.endsWith('.html')) {
		return;
	}

	window.contentReplacementInProgress = true;

	// Check if URL is in cache
	var xhrCheck = new XMLHttpRequest();
	xhrCheck.open('GET', '/urls-cache.txt', true);
	xhrCheck.onreadystatechange = function () {
		if (xhrCheck.readyState === 4) {

			// Call content-4.php with current URL
			const currentUrl = window.location.href;
			const encodedUrl = encodeURIComponent(currentUrl);
			const contentUrl = `/common/content-4.php?render-page-with-php=yes&t=${encodedUrl}`;

			const xhttp = new XMLHttpRequest();
			xhttp.onload = function () {
				if (this.status === 200) {
					// Replace the entire document using document.write
					document.open();
					document.write(this.responseText);
					document.close();

					// Clean up all hrefs by removing render-page-with-php parameter
					setTimeout(function () {
						const allLinks = document.querySelectorAll('a[href]');

						allLinks.forEach(function (link) {
							const originalHref = link.href;
							const originalHrefAttr = link.getAttribute('href');

							let wasModified = false;

							try {
								const url = new URL(link.href);

								if (url.searchParams.has('render-page-with-php')) {
									url.searchParams.delete('render-page-with-php');
									const cleanUrl = url.toString();
									link.href = cleanUrl;
									wasModified = true;
								}
							} catch (e) {
								// Check both href and getAttribute for render-page-with-php
								const checkHref = originalHrefAttr || originalHref;

								if (checkHref.includes('render-page-with-php=')) {
									const cleanHref = checkHref.replace(/[?&]render-page-with-php=[^&]*/g, '').replace(/\?&/, '?').replace(/[?&]$/, '');
									link.setAttribute('href', cleanHref);
									wasModified = true;
								}
							}

							// Final verification
							const finalHref = link.href;
							const finalHrefAttr = link.getAttribute('href');

							// Double check if render-page-with-php still exists
							if (finalHref.includes('render-page-with-php=') || (finalHrefAttr && finalHrefAttr.includes('render-page-with-php='))) {
								console.error('Rrender-page-with-php still found after cleanup!');
							}
						});

						// Additional verification scan
						setTimeout(function () {
							const allLinksAfter = document.querySelectorAll('a[href]');
							let remainingParamsCount = 0;

							allLinksAfter.forEach(function (link, index) {
								const href = link.href;
								const hrefAttr = link.getAttribute('href');

								if (href.includes('render-page-with-php=') || (hrefAttr && hrefAttr.includes('render-page-with-php='))) {
									remainingParamsCount++;
									console.error(`Still found render-page-with-php in link ${index + 1}:\n  href: ${href}\n  getAttribute("href"): ${hrefAttr}`);
								}
							});

							if (remainingParamsCount !== 0) {
								console.error(`Found ${remainingParamsCount} links still containing render-page-with-php`);
							}
						}, 50);

					}, 100); // Small delay to ensure DOM is fully ready

				} else if (this.status >= 300 && this.status < 400) {
					console.error(`Redirect status ${this.status} received nothing to do on cache mode`);
				} else {
					console.error(`Received status code ${this.status}`);
				}
			};

			xhttp.onerror = function () {
				console.error('XMLHttpRequest error occurred');
			};

			xhttp.onabort = function () {
				console.warn('XMLHttpRequest was aborted');
			};

			xhttp.open("GET", contentUrl);
			xhttp.send();
		}
	};

	xhrCheck.onerror = function () {
		console.error('Error occurred while checking urls-cache.txt');
	};

	xhrCheck.send();
});
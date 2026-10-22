window.addEventListener('load', async function () {
	const normalize = text => String(text || '').replace(/[\s\u00A0]+/gu, ' ').trim();
	const skipSelector = 'script, style, noscript, textarea, code, pre, #floating-tools-div';
	const response = await fetch('/live-translates.json', {
		cache: 'no-store',
		credentials: 'same-origin'
	});
	if (!response.ok) return;

	const database = await response.json();
	const defaultLanguage = database['default-language'] || 'en';
	const language = normalize(new URLSearchParams(window.location.search).get('lang') || defaultLanguage).toLowerCase();
	if (!language || language === defaultLanguage) return;

	const translations = (database['global-translate-strings']?.[language] || [])
		.filter(item => Array.isArray(item) && item[0] && item[1])
		.sort((first, second) => String(second[0]).length - String(first[0]).length)
		.map(item => ({
			pattern: new RegExp(normalize(item[0]).split(/[\s\u00A0]+/u).map(part => part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('(?:\\s|\\u00A0)+'), 'giu'),
			replacement: String(item[1])
		}));
	if (!translations.length) return;

	const replaceText = value => {
		const original = normalize(value);
		if (!original) return null;
		let translated = original;
		for (const translation of translations) translated = translated.replace(translation.pattern, translation.replacement);
		return translated === original ? null : translated;
	};

	const translateNode = root => {
		if (!root) return;
		if (root.nodeType === Node.TEXT_NODE) {
			if (root.parentElement?.closest(skipSelector)) return;
			const translated = replaceText(root.nodeValue);
			const spacing = String(root.nodeValue).match(/^(\s*)(.*?)(\s*)$/us);
			if (translated) root.nodeValue = spacing ? spacing[1] + translated + spacing[3] : translated;
			return;
		}
		if (root.nodeType !== Node.ELEMENT_NODE && root.nodeType !== Node.DOCUMENT_NODE) return;

		const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
			acceptNode: node => node.parentElement?.closest(skipSelector) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT
		});
		const nodes = [];
		while (walker.nextNode()) nodes.push(walker.currentNode);
		for (const node of nodes) translateNode(node);
	};

	translateNode(document.body);
	new MutationObserver(mutations => {
		for (const mutation of mutations) {
			for (const node of mutation.addedNodes) translateNode(node);
		}
	}).observe(document.body, { childList: true, subtree: true });
});

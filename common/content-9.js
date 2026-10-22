(() => {
	'use strict';
	if (location.pathname === new URL('content-9.php', document.currentScript.src).pathname) return;

	const origin = location.protocol === 'http:' || location.protocol === 'https:'
		? location.origin
		: new URL(document.querySelector('meta[property="og:url"]').content).origin;
	const endpoint = new URL('/common/content-9.php', origin);

	async function send(data) {
		const pageUrl = new URL(location.href);
		pageUrl.search = '';
		pageUrl.hash = '';
		const url = new URL(endpoint);
		url.search = new URLSearchParams({ ...data, location: pageUrl.href }).toString();
		try {
			const response = await fetch(url, {
				credentials: 'omit', cache: 'no-store',
				keepalive: true, referrerPolicy: 'no-referrer'
			});
			if (!response.ok) throw new Error(`HTTP ${response.status}`);
		} catch (error) {
			console.error(`[Horizon Analytics] ${data.type}-save-failed | message=${error.message}`);
		}
	}

	// Record displayed notifications without intercepting console output.
	const recordedNotifications = new WeakSet();
	function recordNotification(notification) {
		if (recordedNotifications.has(notification)) return;
		const type = ['warning', 'notice', 'correct', 'error'].find(type => notification.classList.contains('erh-' + type));
		const message = notification.querySelector('.erh-message')?.textContent.trim();
		if (!type || !message) return;
		recordedNotifications.add(notification);
		send({ type, class: 'trigger', inner: Array.from(message).slice(0, 2000).join('') });
	}

	new MutationObserver(records => {
		for (const record of records) {
			for (const node of record.addedNodes) {
				const element = node instanceof Element ? node : node.parentElement;
				if (!element) continue;
				const notification = element.closest('.erh-notification');
				if (notification) recordNotification(notification);
				element.querySelectorAll('.erh-notification').forEach(recordNotification);
			}
		}
	}).observe(document.documentElement, { childList: true, subtree: true });
	document.querySelectorAll('.erh-notification').forEach(recordNotification);

	window.addEventListener('click', event => {
		if (!event.isTrusted) return;
		const clicked = event.composedPath().find(node => node instanceof Element);
		if (!clicked) return;
		const target = clicked.closest('button, a, input[type="button"], input[type="submit"], input[type="reset"]');
		if (!target || target.isContentEditable) return;
		if (target.closest('.odyssey-admin, .cms-link, #floating-tools-div') || /(?:^|\s)(?:odyssey-|luna-)/.test(target.getAttribute('class') || '')) return;
		const inner = (target instanceof HTMLInputElement ? target.value : target.innerText).trim();
		if (!inner) return;
		send({ type: 'click', inner });
	}, { capture: true, passive: true });

	// Licences
	async function recordLicence() {
		if (typeof getStorageData !== 'function' || typeof validateLicenseKey !== 'function') return;
		try {
			await navigator.locks.request('horizon-licence', async () => {
				const licence = String(await getStorageData('.skia/license.txt') || '').trim().toUpperCase();
				if (!licence || localStorage.getItem('horizon-last-licence') === licence) return;
				const validation = await validateLicenseKey(licence);
				if (!validation.valid) return;
				const currentLicence = String(await getStorageData('.skia/license.txt') || '').trim().toUpperCase();
				if (currentLicence !== licence) return;
				const pageUrl = new URL(location.href);
				pageUrl.search = '';
				pageUrl.hash = '';
				const response = await fetch(endpoint, {
					method: 'POST',
					body: new URLSearchParams({ type: 'licence', location: pageUrl.href, inner: licence }),
					credentials: 'omit', cache: 'no-store', keepalive: true, referrerPolicy: 'no-referrer'
				});
				if (!response.ok) throw new Error(`HTTP ${response.status}`);
				localStorage.setItem('horizon-last-licence', licence);
			});
		} catch (error) {
			console.error(`[Horizon Analytics] licence-save-failed | message=${error.message}`);
		}
	}

	// AI Providers and AI Models
	async function recordAiSelections() {
		if (typeof getStorageData !== 'function') return;
		for (const [type, storagePath] of [
			['ai_provider', '.skia/api-provider.txt'],
			['ai_model', '.skia/chat-model.txt']
		]) {
			try {
				await navigator.locks.request('horizon-' + type, async () => {
					const value = await getStorageData(storagePath);
					if (typeof value !== 'string' || !value.trim()) return;
					const lastValueKey = 'horizon-last-' + type;
					if (localStorage.getItem(lastValueKey) === value) return;
					const pageUrl = new URL(location.href);
					pageUrl.search = '';
					pageUrl.hash = '';
					const response = await fetch(endpoint, {
						method: 'POST',
						body: new URLSearchParams({ type, location: pageUrl.href, inner: value }),
						credentials: 'omit', cache: 'no-store', keepalive: true, referrerPolicy: 'no-referrer'
					});
					if (!response.ok) throw new Error(`HTTP ${response.status}`);
					localStorage.setItem(lastValueKey, value);
				});
			} catch (error) {
				console.error(`[Horizon Analytics] ${type}-save-failed | message=${error.message}`);
			}
		}
	}

	if (document.readyState === 'complete') {
		void recordLicence();
		void recordAiSelections();
	} else {
		window.addEventListener('load', recordLicence, { once: true });
		window.addEventListener('load', recordAiSelections, { once: true });
	}
})();


// Centralized error removal function
function removeError(element) {
	// Close all error notifications
	const notifications = document.querySelectorAll('.erh-notification');
	notifications.forEach(notification => notification.remove());
}

function removeSingleNotification(element) {
	const notification = element?.closest('.erh-notification');
	if (notification) {
		notification.remove();
	}
}

const TRIGGER_CORRECT_PERMANENT_STORAGE_PREFIX = 'erh:trigger-correct-permanent:';
const TRIGGER_CORRECT_PERMANENT_STORAGE_LIMIT = 8;
const TRIGGER_CORRECT_PERMANENT_STORAGE_MAX_AGE_MS = 90 * 24 * 60 * 60 * 1000;

function getTriggerCorrectPermanentStorageScope() {
	return String(window.APP_MARKETPLACE_PURCHASE_URL || '').trim();
}

function getTriggerCorrectPermanentStoragePrefix() {
	return `${TRIGGER_CORRECT_PERMANENT_STORAGE_PREFIX}${getTriggerCorrectPermanentStorageScope()}:`;
}

function getTriggerCorrectPermanentStorage() {
	try {
		return typeof localStorage !== 'undefined' ? localStorage : null;
	} catch (error) {
		return null;
	}
}

function getTriggerCorrectPermanentStorageKey(message) {
	let hash = 0;
	const hashSource = String(message || '');
	for (let i = 0; i < hashSource.length; i += 1) {
		hash = ((hash << 5) - hash) + hashSource.charCodeAt(i);
		hash |= 0;
	}
	return `${getTriggerCorrectPermanentStoragePrefix()}msg_${Math.abs(hash)}`;
}

function getTriggerCorrectPermanentRecords(storage) {
	const records = [];
	if (!storage) {
		return records;
	}

	const storagePrefix = getTriggerCorrectPermanentStoragePrefix();
	for (let i = 0; i < storage.length; i += 1) {
		const storageKey = storage.key(i);
		if (!storageKey || !storageKey.startsWith(storagePrefix)) {
			continue;
		}

		const rawValue = storage.getItem(storageKey);
		if (!rawValue) {
			records.push({ storageKey, parsed: null, remove: true });
			continue;
		}

		try {
			const parsed = JSON.parse(String(rawValue));
			records.push({
				storageKey,
				parsed,
				remove: !parsed || typeof parsed !== 'object' || Array.isArray(parsed)
			});
		} catch (error) {
			records.push({ storageKey, parsed: null, remove: true });
		}
	}

	return records;
}

function cleanupTriggerCorrectPermanentStorage(storage, keepStorageKey) {
	if (!storage) {
		return;
	}

	const now = Date.now();
	const records = getTriggerCorrectPermanentRecords(storage);
	const validRecords = [];

	records.forEach(({ storageKey, parsed, remove }) => {
		const timestamp = Number.parseInt(parsed?.timestamp, 10);
		const closedAt = Number.parseInt(parsed?.closedAt, 10);
		const recordTime = Number.isFinite(closedAt) ? closedAt : timestamp;
		const isExpired = Number.isFinite(recordTime) && now - recordTime > TRIGGER_CORRECT_PERMANENT_STORAGE_MAX_AGE_MS;

		if (remove || isExpired) {
			try {
				if (storageKey !== keepStorageKey) {
					storage.removeItem(storageKey);
				}
			} catch (error) {
				// Best-effort cleanup only.
			}
			return;
		}

		validRecords.push({
			storageKey,
			parsed,
			sortTime: Number.isFinite(recordTime) ? recordTime : 0
		});
	});

	validRecords
		.sort((a, b) => b.sortTime - a.sortTime)
		.slice(TRIGGER_CORRECT_PERMANENT_STORAGE_LIMIT)
		.forEach(({ storageKey }) => {
			try {
				if (storageKey !== keepStorageKey) {
					storage.removeItem(storageKey);
				}
			} catch (error) {
				// Best-effort cleanup only.
			}
		});
}

function setTriggerCorrectPermanentRecord(storage, storageKey, record) {
	if (!storage || !storageKey) {
		return false;
	}

	cleanupTriggerCorrectPermanentStorage(storage, storageKey);

	try {
		storage.setItem(storageKey, JSON.stringify(record));
		return true;
	} catch (error) {
		cleanupTriggerCorrectPermanentStorage(storage, storageKey);
		try {
			storage.setItem(storageKey, JSON.stringify(record));
			return true;
		} catch (retryError) {
			return false;
		}
	}
}

function restoreTriggerCorrectPermanentNotifications() {
	const storage = getTriggerCorrectPermanentStorage();
	if (!storage) {
		return;
	}

	cleanupTriggerCorrectPermanentStorage(storage);

	getTriggerCorrectPermanentRecords(storage).forEach(({ storageKey, parsed, remove }) => {
		if (remove || !parsed || parsed.closed === true) {
			return;
		}

		const parsedMessage = String(parsed.message || '').trim();
		if (!parsedMessage) {
			return;
		}

		const parsedTimestamp = Number.parseInt(parsed.timestamp, 10);
		createTriggerCorrectPermanentElement({
			message: parsedMessage,
			timestamp: Number.isFinite(parsedTimestamp) ? parsedTimestamp : Date.now(),
			closed: false,
			closedAt: null
		}, storageKey);
	});
}

function createTriggerCorrectPermanentElement(record, storageKey) {
	if (!record || !storageKey || !document.body) {
		return false;
	}

	const existingNotification = document.querySelector(`.trigger-correct-permanent[data-permanent-storage-key="${storageKey}"]`);
	if (existingNotification) {
		return existingNotification;
	}

	const successDiv = document.createElement('div');
	successDiv.className = 'erh-notification erh-correct trigger-correct-permanent';
	if (record.message === 'Image detected! You can now drag images directly from the sidebar onto your page without Save As. Try it by dragging the ↔️ Move button.') {
		successDiv.classList.add('image-move-notice');
	}
	successDiv.dataset.permanentStorageKey = storageKey;
	successDiv.dataset.timestamp = String(record.timestamp || '');

	const messageText = document.createElement('span');
	messageText.className = 'erh-message';
	messageText.innerHTML = `<strong>${record.message.charAt(0).toUpperCase() + record.message.slice(1)}</strong>`;

	const closeButton = document.createElement('span');
	closeButton.innerHTML = '&times;';
	closeButton.className = 'erh-close';
	closeButton.title = 'Close';
	closeButton.dataset.permanentStorageKey = storageKey;

	successDiv.appendChild(messageText);
	successDiv.appendChild(closeButton);

	document.body.insertBefore(successDiv, document.body.firstChild);
	return successDiv;
}

// Event delegation for closing error notifications
document.addEventListener('click', function (event) {
	if (event.target.classList.contains('erh-close')) {
		const permanentStorageKey = event.target.dataset.permanentStorageKey;
		if (event.target.closest('.image-move-notice')) document.body.classList.add('image-move-hint-dismissed');
		if (permanentStorageKey) {
			const storage = getTriggerCorrectPermanentStorage();
			try {
				const rawValue = storage?.getItem(permanentStorageKey);
				if (rawValue) {
					const parsed = JSON.parse(String(rawValue));
					if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
						const closedRecord = {
							...parsed,
							closed: true,
							closedAt: Date.now()
						};
						if (!setTriggerCorrectPermanentRecord(storage, permanentStorageKey, closedRecord)) {
							storage?.removeItem(permanentStorageKey);
						}
					}
				}
			} catch (error) {
				try {
					storage?.removeItem(permanentStorageKey);
				} catch (removeError) {
					// The notification can still close even if storage is unavailable.
				}
			}
			removeSingleNotification(event.target);
			return;
		}
		removeError(event.target);
	}
});

// Create error notification element
function createErrorNotification(message, type, stack) {
	// Map old type names to new prefixed classes
	const typeClass = getTypeClass(type);

	// Create the appropriate element type
	const elementType = (typeClass === 'erh-error') ? 'div' : 'div';
	const errorDiv = document.createElement(elementType);
	errorDiv.className = `erh-notification ${typeClass} erh-auto-hide`;
	errorDiv.dataset.errorType = typeClass;
	errorDiv.id = 'erh_' + Date.now() + Math.floor(Math.random() * 1000);

	// Create message content
	const messageElement = (typeClass === 'erh-error' || typeClass === 'erh-warning') ? 'a' : 'span';
	const messageText = document.createElement(messageElement);
	messageText.className = 'erh-message';

	if (messageElement === 'a') {
		messageText.innerHTML = `<strong>${message.charAt(0).toUpperCase() + message.slice(1)}</strong>`;
		messageText.classList.add('erh-message-link');
	} else {
		messageText.innerHTML = `<strong>${message.charAt(0).toUpperCase() + message.slice(1)}</strong>`;
	}

	// Add stack trace if provided
	if (stack) {
		const stackElement = document.createElement('pre');
		stackElement.className = 'erh-stack';
		stackElement.style.cssText = 'margin: 8px 0 0 0; padding: 8px; background: rgba(0,0,0,0.2); border-radius: 4px; font-size: 11px; max-height: 150px; overflow: auto; white-space: pre-wrap; word-break: break-all;';
		stackElement.textContent = stack;
		errorDiv.appendChild(stackElement);
	}

	// Create close button
	const closeButton = document.createElement('span');
	closeButton.innerHTML = '&times;';
	closeButton.className = 'erh-close';
	closeButton.title = 'Close';

	errorDiv.appendChild(messageText);
	if (stack) {
		const stackElement = errorDiv.querySelector('.erh-stack');
		if (stackElement) {
			errorDiv.appendChild(stackElement);
		}
	}
	errorDiv.appendChild(closeButton);

	// Add to document
	document.body.insertBefore(errorDiv, document.body.firstChild);

	return errorDiv;
}

// Helper function to map old type names to new prefixed classes
function getTypeClass(type) {
	switch (type) {
		case 'new-error-handler':
			return 'erh-error';
		case 'trigger-correct':
			return 'erh-correct';
		case 'trigger-notice':
			return 'erh-notice';
		case 'trigger-warning':
			return 'erh-warning';
		default:
			return 'erh-error';
	}
}

// Standard notification functions
function trigger_error(message) {
	// Create error element
	const errorDiv = document.createElement('div');
	errorDiv.className = 'erh-notification erh-error new-error-handler erh-auto-hide';

	// Create message content
	const messageText = document.createElement('a');
	messageText.className = 'erh-message';
	// Ensure message is a string before processing
	const safeMessage = String(message || 'An unknown error occurred');
	messageText.innerHTML = `<strong>${safeMessage.charAt(0).toUpperCase() + safeMessage.slice(1)}</strong>`;

	// Create close button
	const closeButton = document.createElement('span');
	closeButton.innerHTML = '&times;';
	closeButton.className = 'erh-close';
	closeButton.title = 'Close';
	closeButton.onclick = () => removeError(closeButton);

	errorDiv.appendChild(messageText);
	errorDiv.appendChild(closeButton);

	document.body.insertBefore(errorDiv, document.body.firstChild);
}

function trigger_correct(message) {
	const successDiv = document.createElement('div');
	successDiv.className = 'erh-notification erh-correct trigger-correct erh-auto-hide';

	const messageText = document.createElement('span');
	messageText.className = 'erh-message';
	messageText.innerHTML = `<strong>${message.charAt(0).toUpperCase() + message.slice(1)}</strong>`;

	const closeButton = document.createElement('span');
	closeButton.innerHTML = '&times;';
	closeButton.className = 'erh-close';
	closeButton.title = 'Close';
	closeButton.onclick = () => removeError(closeButton);

	successDiv.appendChild(messageText);
	successDiv.appendChild(closeButton);

	document.body.insertBefore(successDiv, document.body.firstChild);
}

function trigger_correct_permanent(message, keep = false) {
	const safeMessage = String(message || '').trim();
	if (!safeMessage) {
		return false;
	}

	if (keep !== true) {
		const notification = createErrorNotification(safeMessage, 'trigger-correct');
		notification.classList.remove('erh-auto-hide');
		return notification;
	}

	const storage = getTriggerCorrectPermanentStorage();
	const storageKey = getTriggerCorrectPermanentStorageKey(safeMessage);

	try {
		const records = getTriggerCorrectPermanentRecords(storage);
		for (const recordInfo of records) {
			if (recordInfo.remove || !recordInfo.parsed) {
				continue;
			}

			const parsed = recordInfo.parsed;
			const existingMessage = String(parsed.message || '').trim();
			if (existingMessage === safeMessage && parsed.closed === true) {
				return false;
			}
			if (existingMessage === safeMessage) {
				const timestamp = Number.parseInt(parsed.timestamp, 10);
				return createTriggerCorrectPermanentElement({
					message: existingMessage,
					timestamp: Number.isFinite(timestamp) ? timestamp : Date.now(),
					closed: false,
					closedAt: null
				}, recordInfo.storageKey);
			}
		}
	} catch (error) {
		// Storage is optional for permanent notifications.
	}

	const timestamp = Date.now();
	const record = {
		message: safeMessage,
		timestamp,
		closed: false,
		closedAt: null
	};

	setTriggerCorrectPermanentRecord(storage, storageKey, record);

	return createTriggerCorrectPermanentElement(record, storageKey);
}

function trigger_notice(message) {
	const noticeDiv = document.createElement('div');
	noticeDiv.className = 'erh-notification erh-notice trigger-notice erh-auto-hide';

	const messageText = document.createElement('span');
	messageText.className = 'erh-message';
	messageText.innerHTML = `<strong>${message.charAt(0).toUpperCase() + message.slice(1)}</strong>`;

	const closeButton = document.createElement('span');
	closeButton.innerHTML = '&times;';
	closeButton.className = 'erh-close';
	closeButton.title = 'Close';
	closeButton.onclick = () => removeError(closeButton);

	noticeDiv.appendChild(messageText);
	noticeDiv.appendChild(closeButton);

	document.body.insertBefore(noticeDiv, document.body.firstChild);
}

function trigger_warning(message) {
	const warningDiv = document.createElement('div');
	warningDiv.className = 'erh-notification erh-warning trigger-warning erh-auto-hide';

	const messageText = document.createElement('span');
	messageText.className = 'erh-message';
	messageText.innerHTML = `<strong>${message.charAt(0).toUpperCase() + message.slice(1)}</strong>`;

	const closeButton = document.createElement('span');
	closeButton.innerHTML = '&times;';
	closeButton.className = 'erh-close';
	closeButton.title = 'Close';
	closeButton.onclick = () => removeError(closeButton);

	warningDiv.appendChild(messageText);
	warningDiv.appendChild(closeButton);

	document.body.insertBefore(warningDiv, document.body.firstChild);
}

window.trigger_correct_permanent = trigger_correct_permanent;

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', function () {
		restoreTriggerCorrectPermanentNotifications();
	});
} else {
	restoreTriggerCorrectPermanentNotifications();
}

<?php
declare(strict_types=1);

// Included pages only add the Analytics link to their HTML output.
if (realpath(__FILE__) !== realpath($_SERVER['SCRIPT_FILENAME'])) {
	require_once __DIR__ . '/functions.php';
	ob_start(static function ($html) {
		if (str_contains($html, '<html') && is_user_admin_back_end()) {
			$url = update_url_params(['t' => urlencode(get_current_url())], path2url(__FILE__));
			$html = attach_admin_link($url, 'Analytics', 'darkred', $html, '📊');
		}
		return $html;
	});
	return;
}

$previousColumns = ['received_at', 'ip', 'element', 'inner', 'location', 'operating_system', 'browser'];
$previousFullColumns = array_merge($previousColumns, ['title']);
$previousOrderedColumns = ['location', 'ip', 'element', 'inner', 'title', 'operating_system', 'received_at', 'browser'];
$columnLabels = ['location' => 'Location', 'ip' => 'IP', 'country' => 'Country', 'langs_accept' => 'Langs Accept', 'element' => 'Element ID',
	'inner' => 'Inner', 'title' => 'Title', 'user_agent' => 'User Agent', 'received_at' => 'Received At', 'type' => 'Type', 'referrer' => 'Referrer', 'class' => 'Class'];
$legacyLabels = ['Element' => 'element', 'Operating System' => 'operating_system', 'Browser' => 'browser', 'IP' => 'ip', 'Country' => 'country'];
$columns = array_keys($columnLabels);
$csvColumns = array_values($columnLabels);
$legacyColumns = ['id', 'received_at', 'ip', 'page', 'protocol', 'operating_system', 'browser',
	'browser_version', 'user_agent', 'tag', 'element', 'classes', 'path', 'button'];
$coordinateColumns = array_merge($legacyColumns, ['label', 'input', 'x', 'y', 'element_x',
	'element_y', 'element_width', 'element_height', 'viewport_width', 'viewport_height', 'scroll_x', 'scroll_y']);
$csvPath = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/analytics.csv';

// Read both earlier CSV schemas without inventing missing historical data.
function content9_csv_row(array $header, array $values): array
{
	if (count($header) !== count($values)) throw new RuntimeException('Invalid CSV row.');
	$row = array_combine($header, $values);
	if (isset($row['location'])) return $row + ['title' => ''];
	return [
		'received_at' => $row['received_at'], 'ip' => $row['ip'], 'element' => $row['element'],
		'inner' => $row['label'] ?? '', 'location' => $row['page'],
		'operating_system' => $row['operating_system'],
		'browser' => trim($row['browser'] . ' ' . $row['browser_version']),
		'user_agent' => $row['user_agent'] ?? '', 'title' => '', 'class' => $row['classes'] ?? ''
	];
}

// Event processing is public and never loads the admin view.
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
if (isset($input['location']) || isset($input['id']) || isset($input['inner']) || isset($input['title'])) {
	header('Access-Control-Allow-Origin: *');
	header('Cache-Control: no-store');
	header('Content-Type: text/plain; charset=utf-8');
	if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
		header('Allow: GET, POST');
		http_response_code(405);
		exit('GET or POST required.');
	}
	$type = $input['type'] ?? 'click';
	if (!is_string($type) || !in_array($type, ['click', 'request', 'warning', 'error', 'notice', 'correct', 'licence', 'ai_provider', 'ai_model'], true)) {
		http_response_code(400);
		exit('Invalid event type.');
	}
	$isSelection = in_array($type, ['licence', 'ai_provider', 'ai_model'], true);
	if ($isSelection) {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			header('Allow: POST');
			http_response_code(405);
			exit('POST required for selections.');
		}
		$value = $input['inner'] ?? '';
		if (!is_string($value) || trim($value) === '' || strlen($value) > 8000) {
			http_response_code(400);
			exit('Invalid selection.');
		}
	}
	if ($type === 'licence') {
		$licence = $input['inner'] ?? '';
		if (!is_string($licence) || strlen($licence) > 8000 || !preg_match('/^[A-Za-z0-9-]+$/D', $licence)) {
			http_response_code(400);
			exit('Invalid licence.');
		}
		$input['inner'] = strtoupper($licence);
	}
	$click = [];
	foreach (['location' => 16000, 'id' => 1024, 'inner' => 8000, 'title' => 8000, 'referrer' => 16000, 'class' => 8000] as $name => $limit) {
		$value = in_array($name, ['id', 'title'], true) ? '' : ($input[$name] ?? (in_array($name, ['inner', 'referrer', 'class'], true) || ($type === 'request' && $name !== 'location') ? '' : null));
		if (!is_string($value) || strlen($value) > $limit || preg_match('//u', $value) !== 1) {
			http_response_code(400);
			exit('Invalid event field: ' . $name);
		}
		$click[$name] = $value;
	}
	if (!preg_match('/^[a-z][a-z0-9+.-]*:/i', $click['location'])) {
		http_response_code(400);
		exit('Full location required.');
	}
	if (rawurldecode((string) parse_url($click['location'], PHP_URL_PATH)) === rawurldecode($_SERVER['SCRIPT_NAME'])) {
		http_response_code(204);
		exit;
	}
	// IP and country are determined exclusively on the server, never from client parameters.
	$ip = $isSelection ? '' : ($_SERVER['REMOTE_ADDR'] ?? '');
	if (!filter_var($ip, FILTER_VALIDATE_IP)) $ip = '';
	$langsAccept = $isSelection ? '' : ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
	$userAgent = $isSelection ? '' : ($_SERVER['HTTP_USER_AGENT'] ?? '');
	if (strlen($userAgent) > 4096 || strlen($langsAccept) > 4096 || preg_match('//u', $langsAccept) !== 1) {
		http_response_code(400);
		exit('Invalid client information.');
	}

	$receivedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\\TH:i:s.u\\Z');
	// Resolve the server-observed IP using the existing country cache.
	$country = $isSelection ? '' : 'Unknown';
	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) &&
		!preg_match('/^(?:192\\.0\\.2\\.|198\\.51\\.100\\.|203\\.0\\.113\\.|2001:db8:)/i', $ip)) {
		$cache = null;
		$request = null;
		try {
			$cacheDirectory = dirname($csvPath) . '/cache';
			if (!is_dir($cacheDirectory) && !mkdir($cacheDirectory, 0750, true) && !is_dir($cacheDirectory)) {
				throw new RuntimeException('Could not create country cache.');
			}
			$cachePath = $cacheDirectory . '/ips.txt';
			$cache = fopen($cachePath, 'c+b');
			if ($cache === false || !flock($cache, LOCK_EX)) throw new RuntimeException('Could not lock country cache.');
			$cacheHit = false;
			$cachedIps = [];
			while (($cachedLine = fgets($cache)) !== false) {
				$cached = explode("\t", trim($cachedLine));
				if (count($cached) !== 3 || !filter_var($cached[0], FILTER_VALIDATE_IP) || !ctype_digit($cached[1]) ||
					($cached[2] !== 'Unknown' && !preg_match('/^[A-Z]{2}$/', $cached[2]))) continue;
				unset($cachedIps[$cached[0]]);
				$cachedIps[$cached[0]] = implode("\t", $cached) . "\n";
				if (count($cachedIps) > 999) array_shift($cachedIps);
				if (count($cached) === 3 && $cached[0] === $ip && ctype_digit($cached[1]) && (int) $cached[1] > time() &&
					($cached[2] === 'Unknown' || preg_match('/^[A-Z]{2}$/', $cached[2]))) {
					$country = $cached[2];
					$cacheHit = true;
				}
			}
			if (!$cacheHit) {
				try {
				if (!function_exists('curl_init')) throw new RuntimeException('cURL is unavailable.');
				$request = curl_init('https://api.country.is/' . rawurlencode($ip));
				curl_setopt_array($request, [
					CURLOPT_CAINFO => __DIR__ . '/cacert.pem',
					CURLOPT_HTTPHEADER => ['Accept: application/json'],
					CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT_MS => 800, CURLOPT_TIMEOUT_MS => 2000
				]);
				$response = curl_exec($request);
				$status = curl_getinfo($request, CURLINFO_HTTP_CODE);
				if ($response === false || $status !== 200) throw new RuntimeException('HTTP ' . $status . ' ' . curl_error($request));
				$result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
				if (!is_array($result) || !is_string($result['ip'] ?? null) ||
					!filter_var($result['ip'], FILTER_VALIDATE_IP) || inet_pton($result['ip']) !== inet_pton($ip) ||
					!is_string($result['country'] ?? null) || !preg_match('/^[A-Z]{2}$/', $result['country'])) {
					throw new RuntimeException('Invalid country API response.');
				}
				$country = $result['country'];
				} catch (Throwable $error) {
					$country = 'Unknown';
				} finally {
					if ($request !== null && $request !== false) curl_close($request);
				}
				$cacheValue = $ip . "\t" . (time() + ($country === 'Unknown' ? 300 : 2592000)) . "\t" . $country . "\n";
				unset($cachedIps[$ip]);
				$cachedIps[$ip] = $cacheValue;
				if (count($cachedIps) > 999) array_shift($cachedIps);
				$cacheText = implode('', $cachedIps);
				if (!rewind($cache) || fwrite($cache, $cacheText) !== strlen($cacheText) ||
					!ftruncate($cache, strlen($cacheText)) || !fflush($cache)) {
					throw new RuntimeException('Could not save country cache.');
				}
			}
		} catch (Throwable $error) {
			error_log('[Horizon Analytics] country-cache-failed | message=' . $error->getMessage());
		} finally {
			if (is_resource($cache)) {
				flock($cache, LOCK_UN);
				fclose($cache);
			}
		}
	}
	$file = null;
	$migration = null;
	try {
		$row = [
			'location' => $click['location'], 'ip' => $ip, 'country' => $country,
			'langs_accept' => $langsAccept, 'element' => $click['id'],
			'inner' => $click['inner'], 'title' => $click['title'], 'user_agent' => $userAgent,
			'received_at' => $receivedAt,
			'type' => $type, 'referrer' => $type === 'request' ? $click['referrer'] : '', 'class' => $click['class']
		];
		// Keep spreadsheet formula prefixes literal when the CSV is opened.
		foreach ($row as &$value) {
			$value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
			if (preg_match('/^[\\s\\x00-\\x1f]*[=+@-]/u', $value)) $value = "'" . $value;
		}
		unset($value);
		$file = fopen($csvPath, 'c+b');
		if ($file === false) throw new RuntimeException('Could not open analytics.csv.');
		if (!flock($file, LOCK_EX)) throw new RuntimeException('Could not lock analytics.csv.');
		$storedHeader = fgetcsv($file, 0, ',', '"', '');
		$header = is_array($storedHeader)
			? array_map(static fn($name) => $legacyLabels[$name] ?? (array_search($name, $columnLabels, true) ?: $name), $storedHeader)
			: false;
		$writeColumns = $header === false ? $columns : $header;
		$missingColumns = is_array($header) ? array_values(array_diff(['ip', 'country', 'langs_accept', 'type', 'referrer', 'class'], $header)) : [];
		$needsColumns = $missingColumns && !array_diff(array_diff($columns, ['ip', 'country', 'langs_accept', 'type', 'referrer', 'class']), $header);
		if (in_array($header, [$legacyColumns, $coordinateColumns, $previousColumns, $previousFullColumns, $previousOrderedColumns], true) ||
			($header === $columns && $storedHeader !== $csvColumns) || $needsColumns) {
			// Keep historical OS/browser values when the original User Agent was not recorded.
			$writeColumns = $needsColumns ? array_merge($header, $missingColumns)
				: array_merge($columns, array_values(array_intersect(['operating_system', 'browser'], $header)));
			$writeLabels = array_map(static fn($name) => $columnLabels[$name] ?? (array_search($name, $legacyLabels, true) ?: $name), $writeColumns);
			$migration = tmpfile();
			if ($migration === false || fputcsv($migration, $writeLabels, ',', '"', '') === false) throw new RuntimeException('Could not prepare CSV migration.');
			while (($values = fgetcsv($file, 0, ',', '"', '')) !== false) {
				$previousRow = content9_csv_row($header, $values);
				if (($previousRow['type'] ?? '') === '') $previousRow['type'] = 'click';
				foreach ($previousRow as &$value) $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
				unset($value);
				if (fputcsv($migration, array_map(static fn($name) => $previousRow[$name] ?? '', $writeColumns), ',', '"', '') === false) throw new RuntimeException('Could not migrate CSV row.');
			}
			if (!feof($file)) throw new RuntimeException('Could not read legacy CSV.');
			$length = ftell($migration);
			if (!rewind($migration) || !rewind($file) || stream_copy_to_stream($migration, $file) !== $length || !ftruncate($file, $length)) throw new RuntimeException('Could not save CSV migration.');
		} elseif ($header === false) {
			$stats = fstat($file);
			if ($stats === false || $stats['size'] !== 0) throw new RuntimeException('Could not read CSV header.');
			if (fputcsv($file, $csvColumns, ',', '"', '') === false) throw new RuntimeException('Could not write CSV header.');
		} elseif (count(array_unique($header)) !== count($header) || array_diff($columns, $header)) {
			throw new RuntimeException('Unexpected analytics.csv columns.');
		}
		$values = array_map(static fn($name) => $row[$name] ?? '', $writeColumns);
		if (fseek($file, 0, SEEK_END) !== 0 || fputcsv($file, $values, ',', '"', '') === false || !fflush($file)) throw new RuntimeException('Could not save event.');
		http_response_code(204);
	} catch (Throwable $error) {
		trigger_error('[Horizon Analytics] event-save-failed | message=' . $error->getMessage(), E_USER_WARNING);
		http_response_code(500);
		echo 'Could not save event.';
	} finally {
		if (is_resource($migration)) fclose($migration);
		if (is_resource($file)) {
			flock($file, LOCK_UN);
			fclose($file);
		}
	}
	exit;
}

// Admin uses the existing content pages' authentication.
require_once __DIR__ . '/functions.php';
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');
if (!is_user_admin_back_end()) {
	$returnParams = array_filter($_GET, static fn($key) => in_array($key, ['date_from', 'date_to', 'path', 't'], true) ||
		preg_match('/^(?:sort|page)_(?:requests|clicks|warnings|errors|notices|correct)$/D', $key), ARRAY_FILTER_USE_KEY);
	$returnParams['login_return'] = '1';
	$returnUrl = path2url(__FILE__) . '?' . http_build_query($returnParams);
	$loginUrl = update_url_params(['t' => urlencode($returnUrl)], path2url(__DIR__ . '/content-0.php'));
	if (!isset($_GET['login_return'])) {
		header('Location: ' . $loginUrl, true, 302);
		exit;
	}
	http_response_code(403);
	echo '<p>Sign-in failed or administrator access is unavailable.</p>';
	echo '<p><a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">Sign in again</a></p>';
	exit;
}

$backUrl = is_string($_GET['t'] ?? null) ? urldecode($_GET['t']) : '/';
if (!preg_match('~^(https?://|/(?!/))~i', $backUrl)) $backUrl = '/';
ob_start(static function ($html) use ($backUrl) {
	if (!is_iframe()) {
		$html = attach_admin_link('"' . htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') . '"', 'Return', 'Blue', $html, '&#8617;');
	}
	return $html;
});

// Date boundaries use the same timezone as the displayed timestamps.
$dateTimezone = new DateTimeZone(date_default_timezone_get());
$today = new DateTimeImmutable('today', $dateTimezone);
$previousMonth = $today->modify('first day of last month');
$defaultStart = $previousMonth->setDate(
	(int) $previousMonth->format('Y'),
	(int) $previousMonth->format('m'),
	min((int) $today->format('d'), (int) $previousMonth->format('t'))
);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
if (!is_string($dateFrom) || !is_string($dateTo)) {
	http_response_code(400);
	exit('Invalid date range.');
}
if ($dateFrom === '') $dateFrom = $defaultStart->format('Y-m-d');
if ($dateTo === '') $dateTo = $today->format('Y-m-d');
foreach ([$dateFrom, $dateTo] as $dateValue) {
	$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue, $dateTimezone);
	if ($date === false || $date->format('Y-m-d') !== $dateValue) {
		http_response_code(400);
		exit('Invalid date. Use YYYY-MM-DD.');
	}
}
$startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateFrom, $dateTimezone);
$endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateTo, $dateTimezone);
if ($startDate > $endDate) {
	http_response_code(400);
	exit('Start Date must be on or before End Date.');
}
// An exclusive next-day boundary includes the entire selected End Date.
$endExclusive = $endDate->modify('+1 day');
$selectedPath = $_GET['path'] ?? '';
if (!is_string($selectedPath) || ($selectedPath !== '' && !str_starts_with($selectedPath, '/'))) {
	http_response_code(400);
	exit('Invalid path.');
}
$rangeParams = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'path' => $selectedPath];
$rangeParams['t'] = urlencode($backUrl);

$header = [];
$records = [];
$fieldColumns = [];
$paths = [];
$file = null;
$readError = '';
try {
	if (file_exists($csvPath)) {
		$file = fopen($csvPath, 'rb');
		if ($file === false || !flock($file, LOCK_SH)) throw new RuntimeException('Could not open analytics.csv.');
		$header = fgetcsv($file, 0, ',', '"', '');
		if (!is_array($header) || in_array(null, $header, true) || in_array('', $header, true) ||
			count(array_unique($header)) !== count($header)) throw new RuntimeException('Invalid CSV header.');
		foreach ($header as $column => $label) $fieldColumns[strtolower(str_replace(' ', '_', $label))] = $column;
		$locationColumn = $fieldColumns['location'] ?? $fieldColumns['page'] ?? null;
		$receivedAtColumn = $fieldColumns['received_at'] ?? null;
		if ($receivedAtColumn === null) throw new RuntimeException('Missing Received At column.');
		// Collect every available path and retain only records matching both filters.
		while (($values = fgetcsv($file, 0, ',', '"', '')) !== false) {
			if ($values === [null]) continue;
			if (count($values) !== count($header)) throw new RuntimeException('Invalid CSV row.');
			$timestamp = $values[$receivedAtColumn];
			if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $timestamp)) {
				throw new RuntimeException('Invalid Received At timestamp.');
			}
			$receivedAt = new DateTimeImmutable($timestamp);
			$dateErrors = DateTimeImmutable::getLastErrors();
			if ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count'])) {
				throw new RuntimeException('Invalid Received At timestamp.');
			}
			$location = $locationColumn !== null ? $values[$locationColumn] : '';
			$url = parse_url($location);
			$path = rawurldecode($url['path'] ?? '/');
			$scheme = strtolower($url['scheme'] ?? '');
			if ($scheme === 'file' || str_starts_with($scheme, 'vscode-') || $scheme === 'chrome-extension' ||
				preg_match('/(?:^|\.)vscode-cdn\.net$/i', $url['host'] ?? '')) {
				$www = strpos($path, '/www/');
				if ($www !== false) $path = substr($path, $www);
			}
			$path = '/' . ltrim($path, '/');
			$path = preg_replace('~/index\.(?:html|php)$~i', '/', $path);
			if ($path !== '/' && !str_contains(basename($path), '.')) $path = rtrim($path, '/') . '/';
			$paths[$path] = true;
			if ($receivedAt < $startDate || $receivedAt >= $endExclusive) continue;
			if ($selectedPath !== '' && $path !== $selectedPath) continue;
			$records[] = $values;
		}
		if (!feof($file)) throw new RuntimeException('Could not read analytics.csv.');
		$records = array_reverse($records);
	}
} catch (Throwable $error) {
	trigger_error('[Horizon Analytics] admin-read-failed | message=' . $error->getMessage(), E_USER_WARNING);
	http_response_code(500);
	$readError = 'Could not read analytics.csv.';
	$header = [];
	$records = [];
} finally {
	if (is_resource($file)) {
		flock($file, LOCK_UN);
		fclose($file);
	}
}

if ($selectedPath !== '') $paths[$selectedPath] = true;
ksort($paths, SORT_NATURAL | SORT_FLAG_CASE);

// Compatibility columns exist only in the view; opening Analytics never writes to the CSV.
$typeColumn = $fieldColumns['type'] ?? null;
if ($header && $typeColumn === null) {
	$typeColumn = count($header);
	$header[] = 'Type';
	foreach ($records as &$record) $record[] = 'click';
	unset($record);
}
if (!in_array('Langs Accept', $header, true) && !in_array('langs_accept', $header, true)) {
	$column = $header ? max(array_keys($header)) + 1 : 0;
	$header[$column] = 'Langs Accept';
	foreach ($records as &$record) $record[$column] = '';
	unset($record);
}
$dateFormatter = class_exists(IntlDateFormatter::class)
	? new IntlDateFormatter(null, IntlDateFormatter::MEDIUM, IntlDateFormatter::MEDIUM, $dateTimezone->getName())
	: null;

// Expand User Agent only in the view; the CSV keeps the original request header.
if ($readError === '') {
	$userAgentColumn = array_search('User Agent', $header, true);
	if ($userAgentColumn === false) $userAgentColumn = array_search('user_agent', $header, true);
	if ($userAgentColumn !== false) {
		$osColumn = array_search('Operating System', $header, true);
		if ($osColumn === false) $osColumn = array_search('operating_system', $header, true);
		$browserColumn = array_search('Browser', $header, true);
		if ($browserColumn === false) $browserColumn = array_search('browser', $header, true);
		$displayHeader = [];
		foreach ($header as $column => $label) {
			if ($column === $userAgentColumn) {
				array_push($displayHeader, 'Operating System', 'Browser');
			} elseif ($column !== $osColumn && $column !== $browserColumn) {
				$displayHeader[] = $label;
			}
		}
		foreach ($records as &$record) {
			$userAgent = $record[$userAgentColumn];
			$operatingSystem = $userAgent === '' && $osColumn !== false ? $record[$osColumn] : 'Unknown';
			$browser = $userAgent === '' && $browserColumn !== false ? $record[$browserColumn] : 'Unknown';
			foreach (['Android' => '/Android/i', 'iOS' => '/iPhone|iPad|iPod/i',
				'Windows' => '/Windows/i', 'Chrome OS' => '/CrOS/i',
				'macOS' => '/Macintosh|Mac OS X/i', 'Linux' => '/Linux/i'] as $name => $pattern) {
				if (preg_match($pattern, $userAgent)) {
					$operatingSystem = $name;
					break;
				}
			}
			foreach (['Microsoft Edge' => '/(?:Edg|EdgA|EdgiOS|Edge)\/([\d.]+)/',
				'Opera' => '/(?:OPR|OPiOS)\/([\d.]+)/', 'Samsung Internet' => '/SamsungBrowser\/([\d.]+)/',
				'Firefox' => '/(?:Firefox|FxiOS)\/([\d.]+)/', 'Chromium' => '/Chromium\/([\d.]+)/',
				'Chrome' => '/(?:Chrome|CriOS)\/([\d.]+)/', 'Safari' => '/Version\/([\d.]+).*Safari\//',
				'Internet Explorer' => '/(?:MSIE |rv:)([\d.]+).*Trident|Trident.*rv:([\d.]+)/'] as $name => $pattern) {
				if (preg_match($pattern, $userAgent, $matches)) {
					$browser = $name . ' ' . ($matches[1] !== '' ? $matches[1] : ($matches[2] ?? ''));
					break;
				}
			}
			if (stripos($userAgent, 'JetBrains') !== false) {
				$browser = 'JetBrains';
			}
			$displayRow = [];
			foreach ($record as $column => $cell) {
				if ($column === $userAgentColumn) {
					array_push($displayRow, $operatingSystem, $browser);
				} elseif ($column !== $osColumn && $column !== $browserColumn) {
					$displayRow[] = $cell;
				}
			}
			$record = $displayRow;
		}
		unset($record);
		$header = $displayHeader;
	}
}

$fieldColumns = [];
foreach ($header as $column => $label) {
	$fieldColumns[strtolower(str_replace(' ', '_', $label))] = $column;
}
$languageColumn = $fieldColumns['langs_accept'] ?? null;

// Group stored values within the selected date and path filters.
$selectionGroups = ['licence' => [], 'ai_provider' => [], 'ai_model' => []];
foreach ($records as $record) {
	$type = $record[$fieldColumns['type'] ?? -1] ?? '';
	if (!isset($selectionGroups[$type])) continue;
	$value = $record[$fieldColumns['inner'] ?? -1] ?? '';
	if ($value === '') continue;
	$receivedAt = $record[$fieldColumns['received_at']];
	$key = 'value:' . $value;
	if (!isset($selectionGroups[$type][$key])) $selectionGroups[$type][$key] = [$value, 0, $receivedAt, $receivedAt];
	$selectionGroups[$type][$key][1]++;
	if ($receivedAt < $selectionGroups[$type][$key][2]) $selectionGroups[$type][$key][2] = $receivedAt;
	if ($receivedAt > $selectionGroups[$type][$key][3]) $selectionGroups[$type][$key][3] = $receivedAt;
}

// Keep the CSV schema and historical values; hide these fields in the admin view.
$hiddenColumns = [];
foreach (['element_id', 'element', 'title'] as $field) {
	if (!isset($fieldColumns[$field])) continue;
	$column = $fieldColumns[$field];
	$hiddenColumns[] = $column;
	unset($header[$column]);
	foreach ($records as &$record) unset($record[$column]);
	unset($record);
}

$typeColumn = $fieldColumns['type'] ?? null;
$classColumn = $fieldColumns['class'] ?? null;
$clickRecords = [];
$requestRecords = [];
$warningRecords = [];
$errorRecords = [];
$noticeRecords = [];
$correctRecords = [];
foreach ($records as $record) {
	$type = $typeColumn !== null ? ($record[$typeColumn] ?: 'click') : 'click';
	if (in_array($type, ['warning', 'error', 'notice', 'correct'], true) &&
		($classColumn === null || ($record[$classColumn] ?? '') !== 'trigger')) continue;
	if ($typeColumn !== null) unset($record[$typeColumn]);
	if ($classColumn !== null) unset($record[$classColumn]);
	if (in_array($type, ['warning', 'error', 'notice', 'correct'], true)) {
		foreach (['element_id', 'element', 'title', 'referrer'] as $field) {
			if (isset($fieldColumns[$field])) unset($record[$fieldColumns[$field]]);
		}
	}
	if ($type === 'request') $requestRecords[] = $record;
	elseif ($type === 'warning') $warningRecords[] = $record;
	elseif ($type === 'error') $errorRecords[] = $record;
	elseif ($type === 'notice') $noticeRecords[] = $record;
	elseif ($type === 'correct') $correctRecords[] = $record;
	elseif ($type === 'click') $clickRecords[] = $record;
}
if ($typeColumn !== null) unset($header[$typeColumn]);
if ($classColumn !== null) unset($header[$classColumn]);

$referrerCounts = [];
foreach ($requestRecords as $record) {
	$location = isset($fieldColumns['location']) ? $record[$fieldColumns['location']] : '';
	$referrer = isset($fieldColumns['referrer']) ? $record[$fieldColumns['referrer']] : '';
	$locationHost = preg_replace('/^www\./', '', strtolower((string) parse_url($location, PHP_URL_HOST)));
	$referrerHost = preg_replace('/^www\./', '', strtolower((string) parse_url($referrer, PHP_URL_HOST)));
	$source = $referrerHost === '' ? 'Direct / Unknown'
		: ($referrerHost === $locationHost ? 'Internal' : ucfirst($referrerHost));
	$referrerCounts[$source] = ($referrerCounts[$source] ?? 0) + 1;
}

$charts = ['referrers' => ['title' => 'Referrers', 'counts' => $referrerCounts, 'total' => count($requestRecords)],
	'os' => ['title' => 'Operating System', 'counts' => []],
	'browser' => ['title' => 'Browser', 'counts' => []],
	'country' => ['title' => 'Country', 'counts' => []],
	'language' => ['title' => 'Language: First preference', 'counts' => []],
	'language_second' => ['title' => 'Language: Second preference', 'counts' => []],
	'inner' => ['title' => 'Inner', 'counts' => []]];
foreach ($records as $record) {
	if ($typeColumn !== null && !in_array($record[$typeColumn] ?: 'click', ['request', 'click'], true)) continue;
	$operatingSystem = isset($fieldColumns['operating_system']) ? $record[$fieldColumns['operating_system']] : 'Unknown';
	$browser = isset($fieldColumns['browser']) ? $record[$fieldColumns['browser']] : 'Unknown';
	$browser = preg_replace('/\s+\d[\d.]*$/', '', $browser);
	$country = isset($fieldColumns['country']) ? $record[$fieldColumns['country']] : '';
	$inner = isset($fieldColumns['inner']) ? trim($record[$fieldColumns['inner']]) : '';
	$languages = [];
	foreach (explode(',', $languageColumn !== null ? $record[$languageColumn] : '') as $preference) {
		if (!preg_match('/^\s*([a-z]{1,8}(?:-[a-z0-9]{1,8})*)(?:\s*;\s*q=(0(?:\.\d{0,3})?|1(?:\.0{0,3})?))?\s*$/i', $preference, $match)) continue;
		$quality = isset($match[2]) ? (float) $match[2] : 1;
		if ($quality === 0.0) continue;
		$language = strtolower(explode('-', $match[1])[0]);
		$languages[$language] = max($languages[$language] ?? 0, $quality);
	}
	// Regional variants of the same language count as one preference.
	arsort($languages, SORT_NUMERIC);
	$languages = array_keys($languages);
	$language = $languages[0] ?? 'Unknown';
	$secondLanguage = $languages[1] ?? 'Not specified';
	if (class_exists(Locale::class)) {
		if (isset($languages[0])) $language = Locale::getDisplayLanguage($language, 'en');
		if (isset($languages[1])) $secondLanguage = Locale::getDisplayLanguage($secondLanguage, 'en');
	}
	foreach (['os' => $operatingSystem ?: 'Unknown', 'browser' => $browser ?: 'Unknown',
		'country' => $country !== '' ? $country : 'Unknown',
		'language' => $language, 'language_second' => $secondLanguage,
		'inner' => $inner !== '' ? $inner : '(No Inner)'] as $chart => $label) {
		$charts[$chart]['counts'][$label] = ($charts[$chart]['counts'][$label] ?? 0) + 1;
	}
}
foreach ($charts as &$chart) arsort($chart['counts']);
unset($chart);
$total = count($requestRecords) + count($clickRecords);
$browserHeader = $header;
foreach (['element_id', 'element', 'title', 'referrer'] as $field) {
	if (isset($fieldColumns[$field])) unset($browserHeader[$fieldColumns[$field]]);
}
if (isset($fieldColumns['inner'])) $browserHeader[$fieldColumns['inner']] = 'Message';
// Each event type has independent sorting and pagination within the selected path.
$tableTypes = [
	'licence' => ['name' => 'licences', 'title' => 'Licences',
		'header' => ['Licence', 'Activations', 'First activation', 'Last activation']],
	'ai_provider' => ['name' => 'ai_providers', 'title' => 'AI Providers',
		'header' => ['AI Provider', 'Records', 'First recorded', 'Last recorded']],
	'ai_model' => ['name' => 'ai_models', 'title' => 'AI Models',
		'header' => ['AI Model', 'Records', 'First recorded', 'Last recorded']],
	'request' => ['name' => 'requests', 'title' => 'Requests', 'header' => $header],
	'click' => ['name' => 'clicks', 'title' => 'Clicks', 'header' => $header],
	'warning' => ['name' => 'warnings', 'title' => 'Warnings', 'header' => $browserHeader],
	'error' => ['name' => 'errors', 'title' => 'Trigger errors', 'header' => $browserHeader],
	'notice' => ['name' => 'notices', 'title' => 'Notices', 'header' => $browserHeader],
	'correct' => ['name' => 'correct', 'title' => 'Success', 'header' => $browserHeader],
];
$tables = [];
$eventRecords = ['request' => $requestRecords, 'click' => $clickRecords, 'warning' => $warningRecords, 'error' => $errorRecords, 'notice' => $noticeRecords, 'correct' => $correctRecords];
foreach ($selectionGroups as $type => $groups) $eventRecords[$type] = array_values($groups);
foreach ($tableTypes as $type => $definition) {
	$tableId = $definition['name'];
	$tables[$tableId] = $definition + [
		'id' => 'table-' . $tableId, 'rows' => $eventRecords[$type],
		'sortParameter' => 'sort_' . $tableId, 'pageParameter' => 'page_' . $tableId,
		'defaultSort' => isset($selectionGroups[$type]) ? [1 => 'desc'] : [],
	];
}
unset($records, $clickRecords, $requestRecords, $warningRecords, $errorRecords, $noticeRecords, $correctRecords, $eventRecords);
$params = $rangeParams;
foreach ($tables as &$table) {
	$sort = $_GET[$table['sortParameter']] ?? $table['defaultSort'];
	if (!is_array($sort)) {
		http_response_code(400);
		exit('Invalid sort.');
	}
	foreach ($sort as $column => $direction) {
		if (!in_array($table['name'], ['licences', 'ai_providers', 'ai_models'], true) && in_array($column, $hiddenColumns, true)) {
			unset($sort[$column]);
			continue;
		}
		if (!ctype_digit((string) $column) || !array_key_exists((int) $column, $table['header']) ||
			!in_array($direction, ['asc', 'desc'], true)) {
			http_response_code(400);
			exit('Invalid sort column or direction.');
		}
	}
	// One comparator for every table and column, including ISO date strings.
	if ($sort) {
		usort($table['rows'], static function ($left, $right) use ($sort) {
			foreach ($sort as $column => $direction) {
				$result = strnatcasecmp((string) $left[$column], (string) $right[$column]);
				if ($result !== 0) return $direction === 'asc' ? $result : -$result;
			}
			return 0;
		});
	}
	$page = $_GET[$table['pageParameter']] ?? '1';
	if (!is_string($page) || !ctype_digit($page) || (int) $page < 1) {
		http_response_code(400);
		exit('Invalid page.');
	}
	$table['total'] = count($table['rows']);
	$table['pageCount'] = max(1, (int) ceil($table['total'] / 10));
	$table['page'] = min((int) $page, $table['pageCount']);
	$table['sort'] = $sort;
	$table['rows'] = array_slice($table['rows'], ($table['page'] - 1) * 10, 10);
	if ($sort) $params[$table['sortParameter']] = $sort;
	if ($table['page'] > 1) $params[$table['pageParameter']] = $table['page'];
}
unset($table);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title>Horizon Analytics &amp; Diagnostics</title>
	<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%8C%85%3C/text%3E%3C/svg%3E">
	<link rel="stylesheet" href="content-9.css">
</head>
<body>
	<header>
		<div>
			<h1>🌅 Horizon Analytics &amp; Diagnostics</h1>
			<span class="summary">Track clicks and explore activity across your pages.</span>
		</div>
		<a href="?<?= htmlspecialchars(http_build_query($params), ENT_QUOTES, 'UTF-8') ?>">Refresh</a>
	</header>
	<?php if ($readError !== ''): ?>
		<p class="error"><?= htmlspecialchars($readError, ENT_QUOTES, 'UTF-8') ?></p>
	<?php else: ?>
		<form method="get" id="analytics-filters">
			<input type="hidden" name="t" value="<?= htmlspecialchars(urlencode($backUrl), ENT_QUOTES, 'UTF-8') ?>">
			<label for="path">Path</label>
			<select name="path" id="path">
				<option value="">All pages</option>
				<?php foreach ($paths as $path => $available): ?>
					<option value="<?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?>"<?= $path === $selectedPath ? ' selected' : '' ?>><?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?></option>
				<?php endforeach; ?>
			</select>
			<label for="date-from">Start Date</label>
			<input type="date" name="date_from" id="date-from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>">
			<label for="date-to">End Date</label>
			<input type="date" name="date_to" id="date-to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>">
			<?php foreach ($tables as $table): ?>
			<?php foreach ($table['sort'] as $column => $direction): ?>
				<input type="hidden" name="<?= $table['sortParameter'] ?>[<?= (int) $column ?>]" value="<?= $direction ?>">
			<?php endforeach; ?>
			<?php endforeach; ?>
		</form>
		<?php foreach ($tables as $table): ?>
			<section id="<?= $table['id'] ?>">
				<header>
					<h2><?= $table['title'] ?></h2>
					<?php if ($table['sort']): ?>
						<?php $reset = $params; unset($reset[$table['sortParameter']]); $reset[$table['pageParameter']] = 1; ?>
						<a href="?<?= htmlspecialchars(http_build_query($reset), ENT_QUOTES, 'UTF-8') ?>#<?= $table['id'] ?>">Reset sorting</a>
					<?php endif; ?>
				</header>
				<div class="analytics-card">
					<div class="table-scroll">
						<table>
							<thead>
								<tr>
									<?php foreach ($table['header'] as $column => $label): ?>
										<th>
											<?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
											<?php foreach (['asc' => '↑', 'desc' => '↓'] as $direction => $arrow):
												$sort = $table['sort'];
												unset($sort[$column]);
												$link = $params;
												$link[$table['sortParameter']] = [$column => $direction] + $sort;
												$link[$table['pageParameter']] = 1;
												$active = ($table['sort'][$column] ?? '') === $direction;
											?>
												<a href="?<?= htmlspecialchars(http_build_query($link), ENT_QUOTES, 'UTF-8') ?>#<?= $table['id'] ?>"<?= $active ? ' class="active"' : '' ?> aria-label="<?= htmlspecialchars($label . ' ' . $direction, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $arrow ?></a>
											<?php endforeach; ?>
											<?php $priority = array_search($column, array_keys($table['sort']), true); ?>
											<?php if ($priority !== false): ?><small><?= $priority + 1 ?></small><?php endif; ?>
										</th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($table['rows'] as $row): ?>
									<tr>
										<?php foreach ($row as $cell):
											if (is_string($cell) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $cell)) {
												$date = (new DateTimeImmutable((string) $cell))->setTimezone($dateTimezone);
												$cell = $dateFormatter ? $dateFormatter->format($date) : $date->format('Y-m-d H:i:s');
											}
										?>
											<td><?php if (is_string($cell) && preg_match('~^https?://~i', $cell) && filter_var($cell, FILTER_VALIDATE_URL)): ?><a href="<?= htmlspecialchars($cell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($cell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a><?php else: ?><?= htmlspecialchars((string) $cell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?php endif; ?></td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
								<?php if (!$table['rows']): ?><tr><td colspan="<?= max(1, count($table['header'])) ?>">No <?= $table['name'] ?> in this range.</td></tr><?php endif; ?>
							</tbody>
						</table>
					</div>
					<nav aria-label="<?= $table['title'] ?> pages">
						<?php if ($table['page'] > 1): ?>
							<?php $link = $params; $link[$table['pageParameter']] = $table['page'] - 1; ?>
							<a href="?<?= htmlspecialchars(http_build_query($link), ENT_QUOTES, 'UTF-8') ?>#<?= $table['id'] ?>">Previous</a>
						<?php endif; ?>
						<?php
							$pageLinks = array_unique(array_merge([1], range(max(1, $table['page'] - 2), min($table['pageCount'], $table['page'] + 2)), [$table['pageCount']]));
							$previousPage = 0;
							foreach ($pageLinks as $number):
								$link = $params;
								$link[$table['pageParameter']] = $number;
						?>
							<?php if ($number > $previousPage + 1): ?><span>…</span><?php endif; ?>
							<?php if ($number === $table['page']): ?>
								<span aria-current="page"><?= $number ?></span>
							<?php else: ?>
								<a href="?<?= htmlspecialchars(http_build_query($link), ENT_QUOTES, 'UTF-8') ?>#<?= $table['id'] ?>"><?= $number ?></a>
							<?php endif; ?>
							<?php $previousPage = $number; ?>
						<?php endforeach; ?>
						<?php if ($table['page'] < $table['pageCount']): ?>
							<?php $link = $params; $link[$table['pageParameter']] = $table['page'] + 1; ?>
							<a href="?<?= htmlspecialchars(http_build_query($link), ENT_QUOTES, 'UTF-8') ?>#<?= $table['id'] ?>">Next</a>
						<?php endif; ?>
						<span class="summary"><?= number_format($table['total']) ?> results</span>
					</nav>
				</div>
			</section>
		<?php endforeach; ?>
		<div class="charts">
			<?php foreach ($charts as $chart): $chartTotal = $chart['total'] ?? $total; ?>
				<section>
					<h2><?= $chart['title'] ?></h2>
					<div class="analytics-card">
						<?php if ($chartTotal): ?>
							<ul class="stat-list">
								<?php foreach ($chart['counts'] as $label => $count):
									$percent = 100 * $count / $chartTotal;
									$percentText = rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.');
								?>
									<li>
										<span class="stat-percent"><?= $percent < 0.1 ? '&lt;0.1' : $percentText ?>%</span>
										<span class="stat-label"><meter class="stat-fill" min="0" max="100" value="<?= number_format($percent, 4, '.', '') ?>" aria-hidden="true"></meter><?= htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
										<span class="stat-count"><?= number_format($count) ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else: ?>
							<p class="summary">No activity in this range.</p>
						<?php endif; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<script>
		document.getElementById('analytics-filters')?.addEventListener('change', function (event) {
			if (event.target.matches('select, input[type="date"]')) this.requestSubmit();
		});
	</script>
	<footer id="copyright" class="summary"><small>&copy; Andreas Express &bull; Program Ver:
			<?php echo date('ymd', filemtime(__FILE__)); ?> &bull; All Rights Reserved
		</small></footer>
</body>
</html>


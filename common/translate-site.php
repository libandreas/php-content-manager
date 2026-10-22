<?php

function normalize_translation_whitespace($text)
{
	$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	return preg_replace('/[\s\x{00A0}]+/u', ' ', trim($text));
}

function build_flexible_text_pattern($value)
{
	$normalized = normalize_translation_whitespace($value);
	if ($normalized === '') {
		return '//u';
	}

	$parts = preg_split('/[\s\x{00A0}]+/u', $normalized);
	$quoted_parts = array_map(function ($part) {
		return preg_quote($part, '/');
	}, $parts);

	return '/' . implode('(?:\s|\x{00A0})+', $quoted_parts) . '/iu';
}

// Function to translate text content and specific attributes
function translateHtmlWithStrings($html, $translations)
{
	if (empty($html) || empty($translations) || !is_array($translations)) {
		return $html;
	}

	// Ensure that longer phrases are replaced before shorter ones, which prevents partial replacements that could occur if a shorter key is a substring of a longer key.
	uksort($translations, function ($a, $b) {
		return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
	});

	$replace_case_insensitive = function ($search, $replace, $subject, &$count = 0) {
		if (!is_array($search)) {
			$search = [$search];
		}
		if (!is_array($replace)) {
			$replace = [$replace];
		}

		foreach ($search as $key => $value) {
			$search[$key] = build_flexible_text_pattern($value);
		}

		return preg_replace($search, $replace, $subject, -1, $count);
	};

	$protectedBlocks = [];
	$html = preg_replace_callback(
		'/<(script|style|noscript|textarea)\b[^>]*>.*?<\/\1>/isu',
		function ($matches) use (&$protectedBlocks) {
			$key = '__MYL_PROTECTED_BLOCK_' . count($protectedBlocks) . '__';
			$protectedBlocks[$key] = $matches[0];
			return $key;
		},
		$html
	);

	// Translate direct text nodes only, so wrapper tags like <a> and <span> remain untouched.
	$html = preg_replace_callback(
		'/(>)([^<>]+)(<)/u',
		function ($matches) use ($translations, $replace_case_insensitive) {
			$originalText = $matches[2];
			$normalizedText = normalize_translation_whitespace($originalText);

			if ($normalizedText === '') {
				return $matches[0];
			}

			$translatedText = $replace_case_insensitive(
				array_keys($translations),
				array_values($translations),
				$normalizedText
			);

			if ($translatedText === null || $translatedText === $normalizedText) {
				return $matches[0];
			}

			if (preg_match('/^(\s*)(.*?)(\s*)$/us', $originalText, $parts)) {
				return $matches[1] . $parts[1] . $translatedText . $parts[3] . $matches[3];
			}

			return $matches[1] . $translatedText . $matches[3];
		},
		$html
	);

	// Translate common visible attributes.
	$html = preg_replace_callback(
		'/\b(title|alt|aria-label|placeholder)\s*=\s*"([^"]*)"/i',
		function ($matches) use ($translations, $replace_case_insensitive) {
			$attribute = $matches[1];
			$value = normalize_translation_whitespace($matches[2]);
			$translatedValue = $replace_case_insensitive(
				array_keys($translations),
				array_values($translations),
				$value
			);
			return $attribute . '="' . $translatedValue . '"';
		},
		$html
	);

	// Translate input button labels, but do not touch user-entered form values.
	$html = preg_replace_callback(
		'/<input\b(?=[^>]*\btype\s*=\s*["\'](?:button|submit|reset)["\'])(?=[^>]*\bvalue\s*=\s*["\'][^"\']*["\'])[^>]*>/i',
		function ($matches) use ($translations, $replace_case_insensitive) {
			return preg_replace_callback(
				'/\bvalue\s*=\s*"([^"]*)"/i',
				function ($valueMatches) use ($translations, $replace_case_insensitive) {
					$value = normalize_translation_whitespace($valueMatches[1]);
					$translatedValue = $replace_case_insensitive(
						array_keys($translations),
						array_values($translations),
						$value
					);

					return 'value="' . $translatedValue . '"';
				},
				$matches[0]
			);
		},
		$html
	);

	if (!empty($protectedBlocks)) {
		$html = strtr($html, $protectedBlocks);
	}

	return $html;
}

function get_translations_strings_from_db_json($langcode)
{
	// get translations
	$translations = [];
	$db_file = $_SERVER['DOCUMENT_ROOT'] . '/live-translates.json';

	// check if file exists
	if (!file_exists($db_file)) {
		return $translations;
	}

	// Read and decode JSON file
	$db_content = json_decode(file_get_contents($db_file), true);

	// Check if global-translate-strings exists and has entries for this language
	if (isset($db_content['global-translate-strings'][$langcode])) {
		$items = $db_content['global-translate-strings'][$langcode];

		// Sort items by source string length (longest first)
		usort($items, function ($a, $b) {
			return mb_strlen($b[0], 'UTF-8') - mb_strlen($a[0], 'UTF-8');
		});

		foreach ($items as $item) {
			if (isset($item[0], $item[1])) {
				if (strpos($item[0], '*') !== false) {
					$pattern = '/' . str_replace('*', '(.*?)', preg_quote($item[0], '/')) . '/i';
					$replacement = str_replace('*', '$1', $item[1]);
					$html_string = preg_replace($pattern, $replacement, $html_string);
				} else {
					$translations[$item[0]] = $item[1];
				}
			}
		}
	}

	return $translations;
}

function process_urls_for_translation($html_string, $langcode, $default_lang = 'en')
{
	$site_domain = $_SERVER['HTTP_HOST'];

	return preg_replace_callback(
		'/<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>/i',
		function ($matches) use ($langcode, $site_domain, $default_lang) {
			$anchor_tag = $matches[0];
			$url = $matches[1];

			if (strpos($url, '#') === 0) {
				return $anchor_tag;
			}
			if (strpos($url, 'mailto:') === 0) {
				return $anchor_tag;
			}
			if (preg_match('/class=["\'][^"\']*lang[^"\']*["\']/', $anchor_tag)) {
				return $anchor_tag;
			}

			$url_domain = parse_url($url, PHP_URL_HOST);
			if ($url_domain && $url_domain !== $site_domain) {
				return $anchor_tag;
			}

			$param_value = ($langcode === $default_lang) ? false : $langcode;
			$new_url = update_url_params(['lang' => $param_value], false, false, $url);
			return str_replace($url, $new_url, $anchor_tag);
		},
		$html_string
	);
}

function update_og_locale($html, $langcode)
{

	// More robust pattern that preserves any existing attributes
	$pattern = '/(<meta[^>]*property=["\']og:locale["\'][^>]*content=["\'])[^"\']*(["\'][^>]*>)/i';
	$replacement = '${1}' . $langcode . '${2}';

	// Handle alternate order (content before property)
	$pattern2 = '/(<meta[^>]*content=["\'])[^"\']*(["\'][^>]*property=["\']og:locale["\'][^>]*>)/i';
	$replacement2 = '${1}' . $langcode . '${2}';

	// Handle alternate order (property before content)
	$html = preg_replace($pattern, $replacement, $html);
	$html = preg_replace($pattern2, $replacement2, $html);

	return $html;
}

function update_og_url($html, $current_url)
{

	// More robust pattern that preserves any existing attributes
	$pattern = '/(<meta[^>]*property=["\']og:url["\'][^>]*content=["\'])[^"\']*(["\'][^>]*>)/i';
	$replacement = '${1}' . $current_url . '${2}';

	// Handle alternate order (content before property)
	$pattern2 = '/(<meta[^>]*content=["\'])[^"\']*(["\'][^>]*property=["\']og:url["\'][^>]*>)/i';
	$replacement2 = '${1}' . $current_url . '${2}';

	$html = preg_replace($pattern, $replacement, $html);
	$html = preg_replace($pattern2, $replacement2, $html);

	return $html;
}

function setup_translate_html_buffer($html)
{
	// Translation via DOM paths was removed because it created more problems than it solved.
	// Only string replacements from live-translates.json are supported.

	if (is_inside_any_admin() or is_iframe()) {
		return $html;
	}

	// $html = 'eimai edo';

	$lg_file = $_SERVER['DOCUMENT_ROOT'] . "/live-translates.json";
	$default_lang = 'en';
	if (file_exists($lg_file)) {
		$db_content = json_decode(file_get_contents($lg_file), true);
		if (isset($db_content['default-language'])) {
			$default_lang = $db_content['default-language'];
		}
	}
	$langcode = isset($_GET['lang']) ? $_GET['lang'] : $default_lang;

	$current_url = get_current_url();
	// Apply string translations from live-translates.json.
	$translations = get_translations_strings_from_db_json($langcode);
	if (!empty($translations)) {
		$html = translateHtmlWithStrings($html, $translations);
	}

	// Process URLs and meta tags
	$html = process_urls_for_translation($html, $langcode, $default_lang);

	// Update canonical URL, og:url, og:locale, and html lang attributes
	$param_value = ($langcode === $default_lang) ? false : $langcode;
	$current_url = update_url_params(['lang' => $param_value], false, false, $current_url);
	$html = update_canonical_2($html, $current_url);
	$html = update_og_url($html, $current_url);
	$html = update_og_locale($html, $langcode);
	$langcode = strtolower(trim((string) $langcode));
	$langcode = preg_replace('/[^a-z-]/', '', $langcode);
	if (strpos($langcode, '-') !== false) {
		$langcode = explode('-', $langcode)[0];
	}
	if (preg_match('/^[a-z]{2,3}$/', $langcode)) {
		if (preg_match('/<html\b[^>]*\blang\s*=\s*["\'][^"\']*["\']/i', $html)) {
			$html = preg_replace('/(<html\b[^>]*\blang\s*=\s*["\'])[^"\']*(["\'])/i', '${1}' . $langcode . '${2}', $html, 1);
		} else {
			$html = preg_replace('/<html\b([^>]*)>/i', '<html$1 lang="' . $langcode . '">', $html, 1);
		}
	}

	// Load the runtime translator for text injected after page load by form plugins or other JavaScript.
	if ($langcode !== $default_lang && !str_contains($html, 'translate-site.js')) {
		$script_tags = '<script defer src="/common/translate-site.js" data-translate-site-runtime="1"></script>' . PHP_EOL .
			'<script defer src="/wp-content/plugins/translate-site/common/translate-site.js" data-translate-site-runtime="1"></script>';

		if (stripos($html, '</body>') !== false) {
			$html = str_ireplace('</body>', $script_tags . PHP_EOL . '</body>', $html);
		} else {
			$html .= PHP_EOL . $script_tags;
		}
	}

	return $html;
}

// load functions
include_once __DIR__ . '/functions.php';
if (file_exists(__DIR__ . '/polyfills.php')) {
	include_once __DIR__ . '/polyfills.php';
}

// παραγωγη σελιδας
ob_start('setup_translate_html_buffer');

//if is not that page
if (realpath(__FILE__) != realpath($_SERVER["SCRIPT_FILENAME"])) {

	// Language detection and redirection
	if (!isset($_GET['lang']) && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {

		// Build redirect URL
		$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
		$redirect_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		// Check if langcode is in the path and convert it to query parameter
		if (preg_match('/\/([a-z]{2})\//i', $redirect_url, $matches)) {
			$path_lang = $matches[1];
			$db_file = __DIR__ . '/../live-translates.json';
			if (file_exists($db_file)) {
				$db_content = json_decode(file_get_contents($db_file), true);

				$lang_exists = isset($db_content['global-translate-strings'][strtolower($path_lang)]);

				if ($lang_exists) {
					$redirect_url = preg_replace('/\/([a-z]{2})\//i', '/', $redirect_url);
					$redirect_url .= (strpos($redirect_url, '?') === false) ? '?' : '&';
					$redirect_url .= 'lang=' . $path_lang;
					echo '<meta http-equiv="refresh" content="0;url=' . $redirect_url . '">';
					exit;
				}
			}
		}

		// Get browser language and extract first two characters
		$full_browser_lang = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
		$browser_lang = substr($full_browser_lang, 0, 2);

		// Check for language in live-translates.json only for the first visit
		$db_file = __DIR__ . '/../live-translates.json';
		if (file_exists($db_file) && !isset($_COOKIE['first-visit'])) {
			$db_content = json_decode(file_get_contents($db_file), true);

			// Default language code
			$default_lang = 'en';
			if (isset($db_content['default-language'])) {
				$default_lang = $db_content['default-language'];
			}

			// Check if browser language is the same as the default language
			if ($browser_lang !== $default_lang) {
				$lang_exists = isset($db_content['global-translate-strings'][$browser_lang]);

				if ($lang_exists) {
					$redirect_url .= (strpos($redirect_url, '?') === false) ? '?' : '&';
					$redirect_url .= 'lang=' . $browser_lang;
					echo '<meta http-equiv="refresh" content="0;url=' . $redirect_url . '">';
					exit;
				}
			}
		}

		// Set a cookie to track the first visit
		if (!isset($_COOKIE['first-visit'])) {
			setcookie('first-visit', '1', time() + (86400 * 30), "/"); // Cookie expires in 30 days
		}
	}

	// Set a cookie to track the first visit
	if (!isset($_COOKIE['first-visit'])) {
		setcookie('first-visit', '1', time() + (86400 * 30), "/"); // Cookie expires in 30 days
	}
	return;
}

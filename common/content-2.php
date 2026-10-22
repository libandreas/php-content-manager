<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/polyfills.php';

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']) && !is_user_admin_back_end()) {
	header('Cache-Control: no-store');
	$returnParams = $_GET;
	unset($returnParams['username'], $returnParams['passwd'], $returnParams['login'], $returnParams['logout'], $returnParams['delete-option']);
	$returnParams['login_return'] = '1';
	$returnUrl = path2url(__FILE__) . '?' . http_build_query($returnParams);
	$loginUrl = update_url_params(['t' => urlencode($returnUrl)], path2url(__DIR__ . '/content-0.php'));
	if (!isset($_GET['login_return'])) {
		header('Location: ' . $loginUrl, true, 302);
		exit;
	}
	http_response_code(403);
	header('Content-Type: text/html; charset=utf-8');
	echo '<p>Sign-in failed or administrator access is unavailable.</p>';
	echo '<p><a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">Sign in again</a></p>';
	exit;
}


require_once __DIR__ . '/../vendor/autoload.php';

function news_article_directory($today)
{
	if (!preg_match('/^\d+$/D', (string) $today)) {
		throw new InvalidArgumentException('Invalid article ID.');
	}
	return dirname(__DIR__) . '/users/global/news/' . $today;
}

function news_html_to_markdown($html)
{
	if (trim($html) === '') {
		return '';
	}

	$document = new DOMDocument();
	$previous_errors = libxml_use_internal_errors(true);
	$document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>');
	libxml_clear_errors();
	libxml_use_internal_errors($previous_errors);
	$xpath = new DOMXPath($document);

	// Quill's editor decorations are not article content.
	foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " ql-ui ")]') as $node) {
		$node->parentNode->removeChild($node);
	}

	// Quill 2 stores both list types inside <ol>; restore semantic list tags.
	foreach (iterator_to_array($xpath->query('//ol[li/@data-list]')) as $list) {
		$current_list = null;
		foreach (iterator_to_array($list->childNodes) as $item) {
			if (!$item instanceof DOMElement || $item->tagName !== 'li') {
				continue;
			}
			$tag = $item->getAttribute('data-list') === 'bullet' ? 'ul' : 'ol';
			if ($current_list === null || $current_list->tagName !== $tag) {
				$current_list = $document->createElement($tag);
				$list->parentNode->insertBefore($current_list, $list);
			}
			$item->removeAttribute('data-list');
			$current_list->appendChild($item);
		}
		$list->parentNode->removeChild($list);
	}

	// Make Quill formatting readable outside the editor's stylesheet.
	foreach ($xpath->query('//*[@class]') as $node) {
		$styles = [];
		foreach (preg_split('/\s+/', $node->getAttribute('class')) as $class) {
			if (preg_match('/^ql-align-(center|right|justify)$/', $class, $match)) {
				$styles[] = 'text-align:' . $match[1];
			}
			if (preg_match('/^ql-indent-(\d+)$/', $class, $match)) {
				$styles[] = 'padding-left:' . ((int) $match[1] * 3) . 'em';
			}
		}
		if ($styles) {
			$node->setAttribute('style', rtrim($node->getAttribute('style'), ';') . ';' . implode(';', $styles));
		}
	}

	// Keep complete rich blocks as HTML; converting their children would lose formatting.
	$preserved = [];
	$body = $document->getElementsByTagName('body')->item(0);
	foreach (iterator_to_array($body->childNodes) as $node) {
		if (!$node instanceof DOMElement) {
			continue;
		}
		$rich_nodes = $xpath->query('descendant-or-self::*[@style or @class or @width or @height or @target or @rel or self::u or self::s or self::iframe or self::video or self::audio or self::table or self::div]', $node);
		if ($rich_nodes->length === 0) {
			continue;
		}
		$token = 'CMSRICHBLOCK' . count($preserved) . 'END' . bin2hex(random_bytes(6));
		$preserved[$token] = $document->saveHTML($node);
		$placeholder = $document->createElement('p', $token);
		$body->replaceChild($placeholder, $node);
	}

	$converter = new League\HTMLToMarkdown\HtmlConverter([
		'header_style' => 'atx',
		'strip_tags' => false,
		'use_autolinks' => false,
	]);
	$markdown = $converter->convert($document->saveHTML($body));
	return trim(strtr($markdown, $preserved));
}

function news_description_from_html($html)
{
	$html = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $html);
	$text = html_entity_decode(preg_replace('/<[^>]+>/', ' ', $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$text = trim(preg_replace('/[\s\x{00a0}]+/u', ' ', $text));
	if (mb_strlen($text, 'UTF-8') <= 155) {
		return $text;
	}
	$excerpt = mb_substr($text, 0, 152, 'UTF-8');
	$last_space = mb_strrpos($excerpt, ' ', 0, 'UTF-8');
	if ($last_space !== false) {
		$excerpt = mb_substr($excerpt, 0, $last_space, 'UTF-8');
	}
	return rtrim($excerpt, " \t\n\r.,;:!?") . '...';
}

function news_markdown_fields($markdown)
{
	$markdown = str_replace(["\r\n", "\r"], "\n", ltrim($markdown, "\xEF\xBB\xBF"));
	if (!preg_match('/\A# ([^\n]*)(?:\n|$)/', $markdown, $heading)) {
		throw new RuntimeException('Article must start with a Markdown title.');
	}
	$title = html_entity_decode(strip_tags((new Parsedown())->text($heading[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$body = ltrim(substr($markdown, strlen($heading[0])), "\n");
	$image = '';
	$image_alt = '';
	$first_line = explode("\n", $body, 2)[0];
	if (preg_match('/^!\[[^\r\n]*\]\(\s*\)$/', $first_line)) {
		$body = ltrim(substr($body, strlen($first_line)), "\n");
	} elseif (str_starts_with($first_line, '![')) {
		// Parsedown's image parser retains angle brackets around Markdown destinations.
		$image_line = preg_replace_callback('/\]\(<([^>\r\n]*)>\)$/', static function ($match) {
			return '](' . str_replace(['(', ')'], ['%28', '%29'], $match[1]) . ')';
		}, $first_line);
		$image_html = (new Parsedown())->text($image_line);
		if (preg_match('~^<p><img\s[^>]+\s?/></p>$~s', $image_html)) {
			$document = new DOMDocument();
			$document->loadHTML('<?xml encoding="UTF-8">' . $image_html);
			$element = $document->getElementsByTagName('img')->item(0);
			$image = $element->getAttribute('src');
			$image_alt = $element->getAttribute('alt');
			$body = ltrim(substr($body, strlen($first_line)), "\n");
		}
	}
	return ['title' => $title, 'image' => $image, 'image-alt' => $image_alt, 'markdown' => $body];
}

function read_news_article($today)
{
	$directory = news_article_directory($today);
	if (!is_file($directory . '/main.md')) {
		return [];
	}
	$article = [];
	foreach (glob($directory . '/*.txt') as $file) {
		$field = basename($file, '.txt');
		if (in_array($field, ['title', 'image', 'image-alt', 'timestamp', 'text'], true)) {
			continue;
		}
		$value = file_get_contents($file);
		if ($field === 'category' || $field === 'categories') {
			$value = preg_split('/\r\n|\r|\n/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
		}
		$article[$field] = $value;
	}
	$main = news_markdown_fields(file_get_contents($directory . '/main.md'));
	$article['title'] = $main['title'];
	$article['image'] = $main['image'];
	$article['image-alt'] = $main['image-alt'];
	$article['timestamp'] = filemtime($directory . '/main.md');
	$article['html'] = (new Parsedown())->text($main['markdown']);
	if (trim($article['description'] ?? '') === '') {
		$article['description'] = news_description_from_html($article['html']);
	}

	// Uploaded media use their variant name; .txt holds an explicit URL if needed.
	foreach (glob($directory . '/*') as $file) {
		$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg', 'bmp', 'mp4', 'webm', 'mov', 'ogv'], true)) {
			continue;
		}
		$field = pathinfo($file, PATHINFO_FILENAME);
		if ($field === 'image') {
			continue;
		}
		$article[$field] = '/users/global/news/' . $today . '/' . basename($file);
	}
	// Article links are computed for this request, never stored.
	if (is_file(dirname(__DIR__) . '/posts/index.php')) {
		$slug = slugify($article['title'] ?? '');
		$article['article-url'] = '/posts/' . ($slug !== '' ? $slug : (string) $today);
	} else {
		$article['article-url'] = '/web/news.php?today=' . rawurlencode((string) $today);
	}
	return $article;
}

function read_news_entries()
{
	$articles = [];
	foreach (glob(dirname(__DIR__) . '/users/global/news/*/main.md') as $file) {
		$today = basename(dirname($file));
		if (ctype_digit($today)) {
			$articles[$today] = read_news_article($today);
		}
	}
	return $articles;
}

function save_news_article($today, array $article)
{
	$directory = news_article_directory($today);
	$main = is_file($directory . '/main.md')
		? news_markdown_fields(file_get_contents($directory . '/main.md'))
		: ['title' => '', 'image' => '', 'image-alt' => '', 'markdown' => ''];
	foreach (['title', 'image', 'image-alt'] as $field) {
		if (array_key_exists($field, $article)) {
			$main[$field] = (string) $article[$field];
		}
	}
	if (array_key_exists('html', $article)) {
		$main['markdown'] = news_html_to_markdown((string) $article['html']);
	}
	$title = preg_replace('/[\r\n]+/', ' ', $main['title']);
	$title = preg_replace('/([\\\\`*_{}\[\]()#+.!<>])/', '\\\\$1', $title);
	$markdown = '# ' . $title . "\n\n";
	if ($main['image'] !== '') {
		$alt = str_replace(['\\', '[', ']', "\r", "\n"], ['\\\\', '\\[', '\\]', ' ', ' '], $main['image-alt']);
		$image = str_replace(['<', '>', "\r", "\n", ' '], ['%3C', '%3E', '%0D', '%0A', '%20'], $main['image']);
		$markdown .= '![' . $alt . '](<' . $image . ">)\n\n";
	}
	$markdown .= $main['markdown'];
	if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
		throw new RuntimeException('Could not create article directory.');
	}
	if (file_put_contents($directory . '/main.md', $markdown, LOCK_EX) === false) {
		throw new RuntimeException('Could not save article Markdown.');
	}
	clearstatcache(true, $directory . '/main.md');
	if (array_key_exists('description', $article)) {
		if (trim((string) $article['description']) === '') {
			if (is_file($directory . '/description.txt') && !unlink($directory . '/description.txt')) {
				throw new RuntimeException('Could not remove empty meta description.');
			}
		} elseif (file_put_contents($directory . '/description.txt', (string) $article['description'], LOCK_EX) === false) {
			throw new RuntimeException('Could not save meta description.');
		}
	}
	foreach ($article as $field => $value) {
		if (in_array($field, ['article-url', 'url-encoded', 'timestamp', 'title', 'image', 'image-alt', 'description', 'html', 'text'], true)) {
			continue;
		}
		if ($field === 'url') {
			$image_path = parse_url((string) $value, PHP_URL_PATH) ?? '';
			if (!str_starts_with((string) $value, 'data:image/') && !in_array(strtolower(pathinfo($image_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg', 'bmp', 'ico'], true)) {
				continue;
			}
		}
		if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/D', (string) $field)) {
			throw new InvalidArgumentException('Invalid article field.');
		}
		$file = $directory . '/' . $field . '.txt';
		$content = is_array($value) ? implode("\n", $value) : (string) $value;
		if (file_put_contents($file, $content, LOCK_EX) === false) {
			throw new RuntimeException('Could not save article field: ' . $field);
		}
	}
}

function relocate_news_article($today, $new_today, $copy = false)
{
	$source = news_article_directory($today);
	$destination = news_article_directory($new_today);
	if (is_dir($destination)) {
		throw new RuntimeException('An article already exists with this ID.');
	}
	if ($copy) {
		if (!mkdir($destination, 0755, true)) {
			throw new RuntimeException('Could not create article copy.');
		}
		foreach (glob($source . '/*') as $file) {
			if (is_file($file) && !copy($file, $destination . '/' . basename($file))) {
				throw new RuntimeException('Could not copy article file.');
			}
		}
	} elseif (!rename($source, $destination)) {
		throw new RuntimeException('Could not move article directory.');
	}
	foreach (glob($destination . '/*') as $file) {
		if (in_array(pathinfo($file, PATHINFO_EXTENSION), ['md', 'txt'], true)) {
			$content = str_replace('/users/global/news/' . $today . '/', '/users/global/news/' . $new_today . '/', file_get_contents($file));
			if (file_put_contents($file, $content, LOCK_EX) === false) {
				throw new RuntimeException('Could not update article references.');
			}
		}
	}
}

// Generate helpers
function call_categories_from_db($today = false)
{
	// Read the article files.
	$db_data = read_news_entries();

	$categories_view = array();

	if (isset($db_data)) {
		foreach ($db_data as $entry_date => $news_data) {
			if ($today !== false && (string) $entry_date !== (string) $today)
				continue;

			if (isset($news_data['category'])) {
				foreach ($news_data['category'] as $category) {
					$categories_view[] = mb_strtolower(trim($category));
				}
			}
		}
	}

	$categories_view = array_remove_empty_values($categories_view);
	$categories_view = array_remove_duplicate_values($categories_view);
	return $categories_view;
}

// Scan HTML for custom fields with class pattern text-*
function scan_custom_fields_from_html_2($html)
{
	if ($html == null) {
		return [];
	}

	// Exclude default keys that are handled separately in the CMS
	$excluded_defaults = ['html'];

	// Create a new DOMDocument instance
	$dom = new DOMDocument();

	// Suppress errors due to malformed HTML
	libxml_use_internal_errors(true);
	$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
	libxml_clear_errors();

	$xpath = new DOMXPath($dom);

	// Query for all elements with classes containing 'text-' or 'html-'
	$nodes = $xpath->query("//*[@class]");

	$custom_fields = [];

	if ($nodes->length > 0) {
		foreach ($nodes as $node) {
			$classes = $node->getAttribute('class');
			$class_array = preg_split('/\s+/', trim($classes));

			foreach ($class_array as $class) {
				if (in_array($class, $excluded_defaults, true)) {
					continue;
				}

				// Check if class matches pattern text-* or html-*
				if (preg_match('/^(text|html)-(.+)$/', $class, $matches)) {
					$field_name = $matches[1] . '-' . $matches[2];
					$custom_fields[] = $field_name;
				}
			}
		}
	}

	// Remove duplicates and return
	return array_unique($custom_fields);
}

// Clean editor source before loading it into Quill.
function clean_cms2_editor_html($html)
{
	if ($html == null) {
		return $html;
	}

	$html = str_replace(["\r\n", "\r"], "\n", $html);
	$html = preg_replace('/>\s+</u', '><', $html);
	$html = preg_replace('/<p(?:\s[^>]*)?>\s*(?:&nbsp;|\x{00a0}|<br\s*\/?>)?\s*<\/p>/iu', '', $html);
	$html = preg_replace('/[ \t]*\n+[ \t]*/u', ' ', $html);

	return trim($html);
}

// Text fields treat source line endings as noise, but preserve real user breaks as <br>.
function clean_cms2_text_for_textarea($text)
{
	if ($text == null) {
		return $text;
	}

	$line_break_marker = '%%CMS2_TEXT_BR%%';
	$text = preg_replace('/<br\s*\/?>/iu', $line_break_marker, $text);
	$text = str_replace(["\r\n", "\r"], "\n", $text);
	$text = preg_replace('/[ \t]*\n+[ \t]*/u', ' ', $text);
	$text = preg_replace('/[ \t]{2,}/u', ' ', $text);
	$text = str_replace($line_break_marker, "\n", $text);

	return trim($text);
}

function prepare_cms2_text_for_save($text)
{
	if ($text == null) {
		return $text;
	}

	$text = str_replace(["\r\n", "\r"], "\n", trim($text));
	$text = preg_replace('/[ \t]*\n[ \t]*/u', "\n", $text);

	return str_replace("\n", '<br>', $text);
}

function has_img_tag_with_class_image_2($html)
{
	if ($html == null) {
		return false;
	}

	$dom = new DOMDocument();
	libxml_use_internal_errors(true);
	$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
	libxml_clear_errors();

	$xpath = new DOMXPath($dom);
	$nodes = $xpath->query("//img[contains(concat(' ', normalize-space(@class), ' '), ' image ')]");

	return $nodes->length > 0;
}

function has_video_tag_with_class_video_2($html)
{
	if ($html == null) {
		return false;
	}

	$dom = new DOMDocument();
	libxml_use_internal_errors(true);
	$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
	libxml_clear_errors();

	$xpath = new DOMXPath($dom);
	$nodes = $xpath->query("//video[contains(concat(' ', normalize-space(@class), ' '), ' video ')]");

	return $nodes->length > 0;
}

function return_variables_for_group_item($today)
{
	// Read the article files.
	$db = read_news_entries();

	// Consolidated data access
	$newsData = $db[$today] ?? [];

	// Variant handling with existence check
	$variants = array_filter(
		array_keys($newsData),
		fn($v) => !in_array($v, ['title', 'description', 'image', 'menu-title', 'url', 'url-encoded', 'file', 'filename'])
	);

	// Process variants
	$data_file = [];
	foreach ($variants as $variant) {
		$data_file[$variant] = $newsData[$variant] ?? '';
	}

	// Core fields with fallbacks
	$data_file['title'] = $newsData['title'] ?? 'Untitled';
	$data_file['description'] = $newsData['description'] ?? '';
	$data_file['keywords'] = $newsData['keywords'] ?? '';
	$data_file['menu-title'] = $newsData['menu-title'] ?? $data_file['title'];

	// Date handling
	$date_format = trim($newsData['date-format'] ?? '') ?: 'd/m/Y H:i';
	$timestamp = $newsData['timestamp'] ?? time();
	$data_file['last-update'] = date($date_format, $timestamp);
	$publish_date = DateTime::createFromFormat('YmdHis', (string) $today);
	$data_file['publish-date'] = $publish_date ? $publish_date->format($date_format) : '';

	$data_file['today'] = $today;
	$data_file['article-url'] = $newsData['article-url'];

	// Image handling with existence check
	$data_file['image'] = $newsData['image'] ?? '';

	// HTML content
	$data_file['html'] = $newsData['html'] ?? '';

	// Type-safe category handling
	$categories = [];
	if (!empty($newsData['category'])) {
		$categories = is_array($newsData['category'])
			? $newsData['category']
			: explode(',', (string) $newsData['category']);
	}

	if (!empty($categories)) {
		$data_file['categories'] = implode(',', $categories);
		$data_file['category'] = $categories[0];
	}

	return $data_file;
}

function return_variables_for_group_loop()
{
	// Read the article files.
	$db_data = read_news_entries();

	// Initialize data array
	$data = array();

	// Process global user's news entries
	if (isset($db_data)) {
		foreach ($db_data as $today => $news_data) {
			$data[] = return_variables_for_group_item($today);
		}
	}

	// Sort by 'today' in ascending order
	usort($data, function ($a, $b) {
		return $a['today'] - $b['today'];
	});

	return $data;
}

function shouldContinueVariantForms($variant)
{
	$test_variant = mb_strtolower(str_replace(" ", "-", $variant));

	// Exclude patterns (including text-* and html-*)
	if (preg_match('/^(category|location|image|text|html)-/', $test_variant)) {
		return true;
	}

	$excluded_variants = [
		'image',
		'title',
		'description',
		'keywords',
		'menu-title',
		'date-format',
		'timestamp',
		'html',
		'category',
		'article-url',
		'url',
		'url-encoded',
		'file',
		'filename',
		'today'
	];

	return in_array($test_variant, $excluded_variants);
}

function render_group_items_in_html($html)
{
	if ($html == null) {
		return $html;
	}

	// Create a new DOMDocument with UTF-8 encoding
	$dom = new DOMDocument();

	// Suppress warnings due to malformed HTML
	libxml_use_internal_errors(true);

	// Load the HTML content
	$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);

	// Clear any errors
	libxml_clear_errors();

	// Get the data array from content-2.php
	$data_array = return_variables_for_group_loop();

	if (empty($data_array)) {
		// No data to process
		return $html;
	}

	// Create a DOMXPath to query the document
	$xpath = new DOMXPath($dom);
	// Find all elements with data-type="group-item"
	$group_items = $xpath->query('//*[@data-type="group-item"]');

	// If no group items found, return original HTML without DOM serialization
	if ($group_items->length === 0) {
		return $html;
	}

	foreach ($group_items as $group_item) {
		// Get the data-category, data-min, data-max, data-limit, and data-order attributes
		$category_attr = $group_item->getAttribute('data-category');
		$category_attr_normalized = mb_strtolower(str_replace('-', ' ', $category_attr));
		$min_attr = (int) $group_item->getAttribute('data-min');
		$max_attr = (int) $group_item->getAttribute('data-max');
		$limit_attr = (int) $group_item->getAttribute('data-limit');
		$order_attr = strtolower($group_item->getAttribute('data-order'));

		// Get a fresh copy of data_array for sorting
		$sorted_data_array = $data_array;

		// Sort the data array based on the order attribute
		if ($order_attr === 'desc') {
			$sorted_data_array = array_reverse($sorted_data_array);
		}

		// We'll collect new nodes here
		$new_nodes = [];
		$count = 0;

		foreach ($sorted_data_array as $file => $data_item) {

			// Check if the category matches
			$categories = call_categories_from_db($data_item['today']);
			if (!in_array($category_attr_normalized, $categories, true)) {
				continue; // Skip this item if the category doesn't match
			}

			// Skip items based on the min attribute
			if ($count < $min_attr) {
				$count++;
				continue;
			}

			// Stop processing if the max number of items is reached
			if ($max_attr && $count >= $max_attr) {
				break;
			}

			// Clone the original group item element including its child nodes
			$new_node = $group_item->cloneNode(true);

			// Core fields
			$menu_title = isset($data_item['menu-title']) ? $data_item['menu-title'] : $data_item['title'];
			$title = isset($data_item['title']) ? $data_item['title'] : '';
			$image = isset($data_item['image']) ? $data_item['image'] : '';
			$description = isset($data_item['description']) ? $data_item['description'] : '';
			$keywords = isset($data_item['keywords']) ? $data_item['keywords'] : '';
			$item_html = isset($data_item['html']) ? $data_item['html'] : '';
			$last_update = isset($data_item['last-update']) ? $data_item['last-update'] : '';
			$publish_date = isset($data_item['publish-date']) ? $data_item['publish-date'] : '';
			$categories_str = isset($data_item['categories']) ? $data_item['categories'] : '';
			$category = isset($data_item['category']) ? $data_item['category'] : '';
			$today_value = isset($data_item['today']) ? (string) $data_item['today'] : '';

			// Collect custom fields from item keys
			$custom_fields = [];
			foreach ($data_item as $entry_key => $entry_value) {
				$entry_key = (string) $entry_key;
				if (in_array($entry_key, ['title', 'menu-title', 'description', 'keywords', 'image', 'html', 'last-update', 'publish-date', 'categories', 'category', 'today', 'article-url', 'url', 'url-encoded', 'file', 'filename'], true)) {
					continue;
				}
				if (preg_match('/^(category|location|image)-/', $entry_key)) {
					continue;
				}

				$custom_fields[$entry_key] = $entry_value;
			}

			// Truncate output based on data-limit
			if ($limit_attr) {
				$description = limit_html_output($description, $limit_attr);
				$item_html = limit_html_output($item_html, $limit_attr);
			}

			// Reuse existing class-based updater on the repeated block HTML only
			$new_node_html = $dom->saveHTML($new_node);
			$new_node_html = str_replace('<?xml encoding="UTF-8">', '', $new_node_html);

			$new_node_html = update_elem_by_class($new_node_html, 'menu-title', (string) $menu_title);
			$new_node_html = update_elem_by_class($new_node_html, 'title', (string) $title);
			if ($image === '') {
				$new_node_html = preg_replace('/<img\b(?=[^>]*\bclass=["\'](?:[^"\']*\s)?image(?:\s[^"\']*)?["\'])[^>]*>/i', '', $new_node_html);
			} else {
				$new_node_html = update_main_image($new_node_html, (string) $image, (string) $data_item['image-alt']);
			}
			$new_node_html = update_elem_by_class($new_node_html, 'description', (string) $description);
			$new_node_html = update_elem_by_class($new_node_html, 'keywords', (string) $keywords);
			$new_node_html = update_elem_by_class($new_node_html, 'html', (string) $item_html);
			$new_node_html = update_elem_by_class($new_node_html, 'last-update', (string) $last_update);
			$new_node_html = update_elem_by_class($new_node_html, 'publish-date', (string) $publish_date);
			$new_node_html = update_elem_by_class($new_node_html, 'categories', is_array($categories_str) ? json_encode($categories_str, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $categories_str);
			$new_node_html = update_elem_by_class($new_node_html, 'category', is_array($category) ? json_encode($category, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $category);
			$new_node_html = update_elem_by_class($new_node_html, 'today', $today_value);
			$new_node_html = update_elem_by_class($new_node_html, 'url', rtrim(site_url_des(), '/') . $data_item['article-url']);
			$new_node_html = update_elem_by_class($new_node_html, 'url-encoded', rawurlencode(rtrim(site_url_des(), '/') . $data_item['article-url']));

			// Bind custom fields by class (html-* will keep HTML through update_elem_by_class)
			foreach ($custom_fields as $field_key => $field_value) {
				if ($field_key === 'html') {
					continue;
				}

				$field_value = is_array($field_value)
					? json_encode($field_value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
					: (string) $field_value;

				$new_node_html = update_elem_by_class($new_node_html, $field_key, $field_value);
			}


			$parsed_dom = new DOMDocument();
			libxml_use_internal_errors(true);
			$parsed_dom->loadHTML('<?xml encoding="UTF-8">' . $new_node_html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
			libxml_clear_errors();

			$parsed_xpath = new DOMXPath($parsed_dom);
			foreach ($parsed_xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " today ")]') as $article_link) {
				$article_link->setAttribute('href', $data_item['article-url']);
			}

			if ($parsed_dom->documentElement instanceof DOMElement) {
				$new_node = $dom->importNode($parsed_dom->documentElement, true);
			}

			// Collect new node
			$new_nodes[] = $new_node;
			$count++;
		}

		// Replace the original group_item with the new nodes
		$parent_node = $group_item->parentNode;
		$parent_node->removeChild($group_item);

		foreach ($new_nodes as $new_node) {
			$parent_node->appendChild($new_node);
		}
	}

	// Get the modified HTML
	$modified_html = $dom->saveHTML();

	// Convert back to UTF-8
	$modified_html = html_entity_decode($modified_html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

	// Remove the added XML declaration
	$modified_html = str_replace('<?xml encoding="UTF-8">', '', $modified_html);

	return $modified_html;
}

function setup_cms2_buffer($html)
{
	// Read the article files.
	$db = read_news_entries();

	// Add a link to return to the previous page
	if (str_contains(get_current_url(), "/" . basename(__FILE__)) && !is_iframe()) {

		// Pull back
		$url = $db[$_GET['today'] ?? '']['article-url'] ?? urldecode($_GET['t'] ?? '/');
		$html = attach_admin_link($url, "Return", "Blue", $html, "&#8617;");
	}

	// Not to do anything inside admin
	if (is_inside_any_admin() or is_iframe()) {
		return $html;
	}

	// If there is no HTML document in input, do not continue processing
	if (!str_contains($html, '<html')) {
		return $html;
	}

	// basics variables
	$html = render_group_items_in_html($html);

	// Read today directly from GET
	$today = $_GET['today'] ?? null;

	// Attach links
	if (is_user_admin_back_end()) {

		// Link to edit
		$target = path2url(__FILE__);

		// Update url - pass full url to maintain query parameters (like ?today=...)
		$current_url = get_current_url();
		$url = update_url_params(array('t' => urlencode($current_url), 'today' => $today), $target);

		// Attach the link
		$html = attach_admin_link($url, 'Edit page', 'green', $html, '&#9997;');
	}

	// Giati na kanei update an den yparxei today?
	if ($today == null) {
		return $html;
	}

	// Consolidated data access
	$newsData = $db[$today] ?? [];
	$current_request_url = isset($newsData['article-url']) ? rtrim(site_url_des(), '/') . $newsData['article-url'] : get_current_url();
	$current_request_url_encoded = rawurlencode($current_request_url);

	// Title updates with fallback
	$title = $newsData['title'] ?? '';
	$html = update_title($html, $title);
	$html = update_h1($html, $title);

	// Meta updates with empty fallbacks (update both meta tags and class elements)
	$html = update_meta_description($html, $newsData['description'] ?? '');
	$html = update_elem_by_class($html, "description", $newsData['description'] ?? '');
	$keywords = trim($newsData['keywords'] ?? '');
	if ($keywords === '') {
		$html = preg_replace('/<meta\b(?=[^>]*\bname\s*=\s*["\']keywords["\'])[^>]*>/i', '', $html);
	} else {
		$html = update_meta_keywords($html, htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8'));
	}
	$html = update_elem_by_class($html, "keywords", $newsData['keywords'] ?? '');

	// Date handling with defaults
	$date_format = trim($newsData['date-format'] ?? '') ?: 'd/m/Y H:i';
	$timestamp = $newsData['timestamp'] ?? time();
	$html = update_elem_by_class($html, "last-update", date($date_format, $timestamp));
	$publish_date = DateTime::createFromFormat('YmdHis', (string) $today);
	$publish_date_value = $publish_date ? $publish_date->format($date_format) : '';
	$publish_date_datetime = $publish_date ? $publish_date->format(DateTime::ATOM) : '';
	$html = update_elem_by_class($html, "publish-date", $publish_date_value);
	if ($publish_date_datetime !== '') {
		$html = preg_replace_callback(
			'/<time\b([^>]*\bclass=["\'][^"\']*\bpublish-date\b[^"\']*["\'][^>]*)>/i',
			function ($matches) use ($publish_date_datetime) {
				$tag_attributes = preg_replace(
					'/\sdatetime=["\'][^"\']*["\']/i',
					'',
					$matches[1]
				);
				return '<time' . $tag_attributes . ' datetime="' . htmlspecialchars($publish_date_datetime, ENT_QUOTES, 'UTF-8') . '">';
			},
			$html,
			1
		);
	}

	// HTML content with fallback
	$html = update_main_html($html, $newsData['html'] ?? '');

	// Update the main image
	$image = $newsData['image'] ?? '';
	if ($image === '') {
		$html = preg_replace('/<img\b(?=[^>]*\bclass=["\'](?:[^"\']*\s)?image(?:\s[^"\']*)?["\'])[^>]*>/i', '', $html);
	} else {
		$html = update_main_image($html, $image, $newsData['image-alt']);
	}

	// OG tags with image validation
	$og_tags = [
		'og:title' => $title,
		'og:description' => $newsData['description'] ?? '',
		'og:url' => $current_request_url
	];

	if ($image !== '') {
		$og_tags['og:image'] = $image;
	} else {
		$html = preg_replace('/<meta\b(?=[^>]*\bproperty=["\']og:image["\'])[^>]*>/i', '', $html);
	}
	$html = update_og_meta_tags($html, $og_tags);

	// Variant processing with key filtering
	$variants = array_filter(
		array_keys($newsData),
		fn($v) => !in_array($v, ['title', 'description', 'image', 'date-format', 'timestamp', 'html', 'categories', 'article-url', 'url', 'url-encoded', 'file', 'filename'])
	);

	foreach ($variants as $variant) {
		$value = $newsData[$variant] ?? '';
		$html = update_elem_by_class($html, $variant, $value);
	}

	// URL classes should always come from current request URL, not DB
	$html = update_elem_by_class($html, "url", $current_request_url);
	$html = update_elem_by_class($html, "url-encoded", $current_request_url_encoded);

	// Patch to fix concatenated URLs
	$html = preg_replace_callback('/(https?:\\/\\/[^\\s"\'<>]+?)(https?:\\/\\/[^\\s"\'<>]+)/i', function ($matches) {
		// Keep only the last URL
		return $matches[2];
	}, $html);

	// Patch localhost:8000 url on production website
	$floatingToolsDiv = '';
	if (preg_match('/(<div id="floating-tools-div".*?>.*?<\/div>)/is', $html, $matches)) {
		$floatingToolsDiv = $matches[1];
	}

	// Remove the floating-tools-div content temporarily
	$html = preg_replace('/<div id="floating-tools-div".*?>.*?<\/div>/is', '', $html);

	// Determine the protocol based on the server environment
	$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

	// Replace localhost URLs using the determined protocol
	$html = str_replace("http://localhost:8000/", "$protocol://" . $_SERVER['HTTP_HOST'] . "/", $html);

	// Reinsert the floating-tools-div content if it was found
	if ($floatingToolsDiv) {
		$html = str_replace('</body>', $floatingToolsDiv . '</body>', $html);
	}

	return $html;
}

// load functions
include_once __DIR__ . '/functions.php';
if (file_exists(__DIR__ . '/polyfills.php')) {
	include_once __DIR__ . '/polyfills.php';
}

// Data-only callers do not run the buffer or editor actions.
if (!empty($content_2_functions_only)) {
	return;
}

// Resolve article slugs for templates that use the CMS buffer.
if (is_file(dirname(__DIR__) . '/posts/index.php') && isset($_GET['slug']) && is_string($_GET['slug'])) {
	foreach (read_news_entries() as $entry_today => $entry) {
		if ($entry['article-url'] === '/posts/' . strtolower(trim($_GET['slug'], '/'))) {
			$_GET['today'] = (string) $entry_today;
			break;
		}
	}
}

// Buffering the output
ob_start("setup_cms2_buffer");

// upload image (former upload.php)
if (isset($_GET['upload-image']) && isset($_GET['variant']) && is_user_admin_back_end()) {
	?>
	<html>

	<head>
		<link rel="stylesheet" href="content-2.css">
		<script defer src="content-2.js"></script>
		<script defer src="server-chunks.js"></script>
	</head>

	<body class="uploader-body"><?php

	// load static file
	$username = 'global';
	$field = 'news';
	$today = $_GET['today'];
	$variant = $_GET['variant'];

	// Construct file paths
	$baseDir = news_article_directory($today) . '/';
	if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $variant)) {
		throw new InvalidArgumentException('Invalid media variant.');
	}
	$pattern = "$baseDir$variant.*";

	// Handle image removal
	if (isset($_GET['remove'])) {
		foreach (glob($pattern) as $file) {
			if (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['txt', 'md'], true)) {
				unlink($file);
			}
		}
		save_news_article($today, [$variant => '']);
		reload_with_params(array('remove' => false), false, false, false, true);
	}

	// Handle file upload
	if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['fileToUpload']) && $_FILES['fileToUpload']['error'] == 0) {
		// Validate image type
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $_FILES['fileToUpload']['tmp_name']);
		finfo_close($finfo);

		echo '<code>';

		if (strpos($mime, 'image/') === 0 && in_array(strtolower(pathinfo($_FILES['fileToUpload']['name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg', 'bmp'], true)) {
			// Create directory if needed
			if (!is_dir($baseDir)) {
				@mkdir($baseDir, 0755, true);
			}

			// Get file extension and save
			$ext = strtolower(pathinfo($_FILES['fileToUpload']['name'], PATHINFO_EXTENSION));
			$targetFile = "$baseDir$variant.$ext";

			if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $targetFile)) {
				foreach (glob($pattern) as $previousFile) {
					if ($previousFile !== $targetFile && !in_array(strtolower(pathinfo($previousFile, PATHINFO_EXTENSION)), ['txt', 'md'], true)) {
						unlink($previousFile);
					}
				}
				@chmod($targetFile, 0644);
				save_news_article($today, [$variant => "/users/global/news/$today/$variant.$ext"]);
			}
		}

		echo '</code>';
	}

	// Read the article image.
	$file = null;
	$db_data = read_news_entries();
	$article_image = $db_data[$today][$variant] ?? '';
	if ($article_image !== '') {
		$file = preg_match('/^https?:\/\//i', $article_image)
			? $article_image
			: site_url_des() . '/' . ltrim($article_image, '/');
	}

	// Keep as-is; no dependency on f URL parameter

	// Delete URL
	$delete_url = update_url_params(array('remove' => 'yes', 'variant' => $variant));

	// Display image if exists
	if (!isset($_GET['remove']) && $file != null) {
		$file = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
		$image_alt = htmlspecialchars($db_data[$today]['image-alt'] ?? '', ENT_QUOTES, 'UTF-8');
		echo "<a target='_custom' class=\"uploader-preview-link\" href=\"$file\"><img class=\"uploader-preview-media\" src=\"$file\" alt=\"$image_alt\"></a>";
		echo "<a href=\"$delete_url\" class=\"uploader-delete-btn\">&#10005;</a>";
	}

	// Upload form
	echo '<label class="uploader-label">Drag and drop or upload image here.</label>';
	echo "<form id=\"form_$variant\" class=\"uploader-form\" method=\"post\" enctype=\"multipart/form-data\">";
	echo "<input id=\"upload_$variant\" class=\"uploader-file-input\" type=\"file\" name=\"fileToUpload\" />";
	echo '</form>';
	?>
		<script>
			document.getElementById("upload_<?php echo $variant; ?>").onchange = function () {
				document.getElementById("form_<?php echo $variant; ?>").submit();
			}
		</script>
	</body>

	</html> <?php
	exit();
}

// upload video (new section)
if (isset($_GET['upload-video']) && isset($_GET['variant']) && is_user_admin_back_end()) {
	?>
	<html>

	<head>
		<link rel="stylesheet" href="content-2.css">
		<script defer src="content-2.js"></script>
		<script defer src="server-chunks.js"></script>
	</head>

	<body class="uploader-body"><?php

	// load static file
	$username = 'global';
	$field = 'news';
	$today = $_GET['today'];
	$variant = $_GET['variant'];

	// Construct file paths
	$baseDir = news_article_directory($today) . '/';
	if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $variant)) {
		throw new InvalidArgumentException('Invalid media variant.');
	}
	$pattern = "$baseDir$variant.*";

	// Handle video removal
	if (isset($_GET['remove'])) {
		save_news_article($today, [$variant => '']);
		$existingFiles = array_values(array_filter(glob($pattern), static fn($path) => !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['txt', 'md'], true)));
		if (!empty($existingFiles)) {
			foreach ($existingFiles as $file) {
				@unlink($file);
			}
		}
		reload_with_params(array('remove' => false), false, false, false, true);
	}

	// Handle file upload
	if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['fileToUpload']) && $_FILES['fileToUpload']['error'] == 0) {
		// Validate video type
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($finfo, $_FILES['fileToUpload']['tmp_name']);
		finfo_close($finfo);

		echo '<code>';

		if (strpos($mime, 'video/') === 0 && in_array(strtolower(pathinfo($_FILES['fileToUpload']['name'], PATHINFO_EXTENSION)), ['mp4', 'webm', 'mov', 'ogv'], true)) {
			// Create directory if needed
			if (!is_dir($baseDir)) {
				@mkdir($baseDir, 0755, true);
			}

			// Remove existing files with same name pattern
			$existingFiles = array_values(array_filter(glob($pattern), static fn($path) => !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['txt', 'md'], true)));
			if (!empty($existingFiles)) {
				foreach ($existingFiles as $file) {
					@unlink($file);
				}
			}

			// Get file extension and save
			$ext = strtolower(pathinfo($_FILES['fileToUpload']['name'], PATHINFO_EXTENSION));
			$targetFile = "$baseDir$variant.$ext";

			if (move_uploaded_file($_FILES['fileToUpload']['tmp_name'], $targetFile)) {
				@chmod($targetFile, 0644);
				save_news_article($today, [$variant => "/users/global/news/$today/$variant.$ext"]);
			}
		}

		echo '</code>';
	}

	// Read the article video.
	$file = null;
	$db_data = read_news_entries();
	$article_video = $db_data[$today][$variant] ?? '';
	$url_file = "$baseDir$variant.url";
	$has_url_file = is_file($url_file);
	if ($has_url_file) {
		$article_video = trim(file_get_contents($url_file));
	}

	if ($article_video !== '') {
		$file = preg_match('/^https?:\/\//i', $article_video)
			? $article_video
			: site_url_des() . '/' . ltrim($article_video, '/');
	} elseif (!$has_url_file) {
		$existingFiles = array_values(array_filter(glob($pattern), static fn($path) => !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['txt', 'md', 'url'], true)));
		if (!empty($existingFiles)) {
			$file = site_url_des() . str_replace($_SERVER["DOCUMENT_ROOT"], '', $existingFiles[0]);
		}
	}

	// Keep as-is; no dependency on f URL parameter

	// Delete URL
	$delete_url = update_url_params(array('remove' => 'yes', 'variant' => $variant));

	// Display video if exists
	if (!isset($_GET['remove']) && $file != null && ($has_url_file || is_video($file))) {
		$file = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
		echo "<video controls class=\"uploader-preview-media\" src=\"$file\"></video>";
		echo "<a href=\"$delete_url\" class=\"uploader-delete-btn\">&#10005;</a>";
	}

	// Upload form
	echo '<label class="uploader-label">Drag and drop or upload video here.</label>';
	echo "<form id=\"form_$variant\" class=\"uploader-form\" method=\"post\" enctype=\"multipart/form-data\">";
	echo "<input id=\"upload_$variant\" class=\"uploader-file-input\" type=\"file\" name=\"fileToUpload\" />";
	echo '</form>';
	?>
	</body>

	</html>
	<?php
	exit();
}

// Change category
if (isset($_POST['push-category']) && is_user_admin_back_end()) {
	// Read the article files.
	$db_data = read_news_entries();

	// Get today from GET
	$today = $_GET['today'] ?? null;

	// Convert category to lowercase
	$_POST['category'] = mb_strtolower($_POST['category']);

	if ($_POST['category'] == 'reset') {
		$db_data[$today]['category'] = [];
	} else {
		// Check if category already exists
		$existing_categories = isset($db_data[$today]['category']) ?
			array_values($db_data[$today]['category']) : [];

		if (in_array($_POST['category'], $existing_categories)) {
			trigger_error('Category already exists');
		} else {
			// Add new category
			$next_index = count($existing_categories) + 1;
			$db_data[$today]['category'][$next_index] = $_POST['category'];
		}
	}

	// Save the article's categories.
	$fields = ['category' => $db_data[$today]['category']];
	if (!isset($db_data[$today]['html'])) {
		$fields['html'] = '';
	}
	save_news_article($today, $fields);
}

// Save to database
if (isset($_POST['save-dynamic-page']) && isset($_POST['today']) && is_user_admin_back_end()) {

	// Load the database once
	$db = read_news_entries();

	// Update all fields in memory
	$db[$_POST['today']]['title'] = $_POST['title'];
	$db[$_POST['today']]['description'] = $_POST['description'];
	$db[$_POST['today']]['keywords'] = $_POST['keywords'];
	if (isset($_POST['menu-title'])) {
		$db[$_POST['today']]['menu-title'] = $_POST['menu-title'];
	}
	$db[$_POST['today']]['date-format'] = $_POST['date-format'];

	// Main content is converted to Markdown when saved.
	if (trim($_POST['html']) == '') {
		$_POST['html'] = '';
	}
	$db[$_POST['today']]['html'] = $_POST['html'];

	// Process custom variants
	foreach ($_POST as $key => $value) {
		if (strpos($key, 'variant-') === 0) {
			$variant_name = str_replace('variant-', '', $key);
			$variant_name = mb_strtolower($variant_name);
			if (in_array($variant_name, ['article-url', 'url', 'url-encoded', 'file', 'filename', 'text'], true)) {
				continue;
			}
			if (strpos($variant_name, 'text-') === 0) {
				$value = prepare_cms2_text_for_save($value);
			}
			$db[$_POST['today']][$variant_name] = $value;
		}
	}

	// Store main.md and the additional fields.
	save_news_article($_POST['today'], $db[$_POST['today']]);

	// Prevent Form Resubmission via PRG Pattern
	reload_with_params();
}

if (isset($_POST['delete-dynamic-page']) && is_user_admin_back_end()) {
	// Read the article files.
	$db_data = read_news_entries();

	// Get today from GET
	$today = $_GET['today'] ?? null;

	// Delete an existing article.
	if ($today !== null && isset($db_data[$today])) {
		// Remove the article data; keep its uploaded media.
		$directory = news_article_directory($today);
		foreach (array_merge(glob($directory . '/*.txt'), [$directory . '/main.md']) as $file) {
			if (!unlink($file)) {
				throw new RuntimeException('Could not delete article data.');
			}
		}
		unset($db_data[$today]);
	}

	// Reload to the last (newest) remaining today from DB
	$last_today = null;
	if (isset($db_data) && is_array($db_data)) {
		$remaining_todays = array_keys($db_data);
		if (!empty($remaining_todays)) {
			rsort($remaining_todays, SORT_STRING);
			$last_today = $remaining_todays[0];
		}
	}

	if ($last_today !== null) {
		$params = array('today' => $last_today);
		if (isset($_GET['t'])) {
			$return_url = $db_data[$last_today]['article-url'];
			$params['t'] = urlencode($return_url);
		}
		reload_with_params($params, false, false, false, true);
	}

	// Fallback when no entries remain
	$fallback_today = date('YmdHis');
	$params = array('today' => $fallback_today);
	if (isset($_GET['t'])) {
		$return_url = is_file(dirname(__DIR__) . '/posts/index.php') ? '/posts/' : '/';
		$params['t'] = urlencode($return_url);
	}
	reload_with_params($params, false, false, false, true);
}

// Duplicate page
if (isset($_POST['duplicate-dynamic-page']) && is_user_admin_back_end()) {
	// Read the article files.
	$db_data = read_news_entries();

	$today = $_GET['today'] ?? null;
	$new_today = date('YmdHis');

	// Copy data from global user
	if (isset($db_data[$today])) {
		relocate_news_article($today, $new_today, true);
		save_news_article($new_today, ['title' => 'New page from ' . $db_data[$today]['title']]);
	}

	$params = array('today' => $new_today);
	if (isset($_GET['t'])) {
		$return_url = read_news_article($new_today)['article-url'];
		$params['t'] = urlencode($return_url);
	}
	$url = update_url_params($params);
	header('Location: ' . $url);
	exit();
}

// Move page up
if (isset($_POST['page-up']) && is_user_admin_back_end()) {
	$db_data = read_news_entries();

	$today = $_GET['today'] ?? null;

	// Get global user's news with categories
	$todays = [];
	if (isset($db_data)) {
		foreach ($db_data as $news_today => $news_data) {
			if (isset($news_data['category'])) {
				$todays[] = $news_today;
			}
		}
	}

	// Filter todays by matching categories
	$filtered_todays = [];
	$page_categories = call_categories_from_db($today);
	foreach ($todays as $scan_today) {
		foreach ($page_categories as $page_category) {
			if (in_array(mb_strtolower(trim($page_category)), call_categories_from_db($scan_today))) {
				$filtered_todays[] = $scan_today;
			}
		}
	}

	$filtered_todays = array_unique($filtered_todays);
	$new_today = oneStepSmaller($today, $filtered_todays) - rand(10, 99);

	if ($new_today == null || $new_today <= 0) {
		trigger_error('Cannot move data: there is no smaller date.');
	} else {
		if (isset($db_data[$today])) {
			relocate_news_article($today, $new_today);
			$params = ['today' => $new_today];
			if (isset($_GET['t'])) {
				$params['t'] = urlencode(read_news_article($new_today)['article-url']);
			}
			reload_with_params($params, false, false, false, true);
		}
	}
}

// Move page down
if (isset($_POST['page-down']) && is_user_admin_back_end()) {
	$db_data = read_news_entries();

	$today = $_GET['today'] ?? null;

	// Get global user's news with categories
	$todays = [];
	if (isset($db_data)) {
		foreach ($db_data as $news_today => $news_data) {
			if (isset($news_data['category'])) {
				$todays[] = $news_today;
			}
		}
	}

	// Filter todays by matching categories
	$filtered_todays = [];
	$page_categories = call_categories_from_db($today);
	foreach ($todays as $scan_today) {
		foreach ($page_categories as $page_category) {
			if (in_array(mb_strtolower(trim($page_category)), call_categories_from_db($scan_today))) {
				$filtered_todays[] = $scan_today;
			}
		}
	}

	$filtered_todays = array_unique($filtered_todays);
	$new_today = oneStepBigger($today, $filtered_todays) + rand(10, 99);

	if ($new_today == null || $new_today <= 0) {
		trigger_error('Cannot move data: there is no bigger date.');
	} else {
		if (isset($db_data[$today])) {
			relocate_news_article($today, $new_today);
			$params = ['today' => $new_today];
			if (isset($_GET['t'])) {
				$params['t'] = urlencode(read_news_article($new_today)['article-url']);
			}
			reload_with_params($params, false, false, false, true);
		}
	}
}

// If this file is included, stop before rendering the admin page
if (realpath(__FILE__) != realpath($_SERVER['SCRIPT_FILENAME'])) {

	return;
}

// Setup today for content 2
$today = $_GET['today'] ?? date('YmdHis');
?>
<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="robots" content="noindex,nofollow">
	<title>Galaxy Content Manager - Posts</title>
	<meta name="description" content="Create and manage posts in Galaxy Content Manager.">
	<link rel="stylesheet" href="content-2.css">

	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
	<link rel="shortcut icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>&#127756;</text></svg>">
	<script defer src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
	<!-- Quill Resources -->
	<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
	<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
	<script defer src="content-2.js"></script>
	<link rel="icon" href="icon.png" type="image/png">
</head>

<body class="admin-view" style="font-family: serif">
	<h2>🌌 Galaxy Content Manager</h2>
	<p><small>Create and manage your posts.</small></p>
	<?php if (is_user_admin_back_end()) { ?>
		<?php
		$editor_html_for_tags = null;
		if (isset($_GET['t'])) {
			$editor_url = urldecode($_GET['t']);
			$editor_file = url2path($editor_url);
			if (is_file(dirname(__DIR__) . '/posts/index.php') && str_starts_with(parse_url($editor_url, PHP_URL_PATH) ?? '', '/posts/')) {
				$editor_file = dirname(__DIR__) . '/posts/index.php';
			}
			if ($editor_file && file_exists($editor_file)) {
				$editor_html_for_tags = file_get_contents($editor_file);
			}
		}

		$has_image_tag = has_img_tag_with_class_image_2($editor_html_for_tags);
		$has_video_tag = has_video_tag_with_class_video_2($editor_html_for_tags);
		?>
		<form id="myForm" method="post">
			<input style="padding:5px" value="Save" name="save-dynamic-page" type="submit">
			<input style="padding:5px" value="Duplicate / New" name="duplicate-dynamic-page" type="submit">
			<input style="padding:5px" value="Delete" name="delete-dynamic-page" type="submit" onclick="return confirm('Press enter for delete');">
			<br class="mobile">
			<input style="padding:5px" value="&#8593 Move order up" name="page-up" type="submit">
			<input style="padding:5px" value="&#8595 Move order down" name="page-down" type="submit">

			<?php
			?><strong>Categories:</strong><?php
			$categories = call_categories_from_db($today);
			foreach ($categories as $category) {
				echo '[' . trim(mb_ucwords(mb_strtolower($category))), '] ';
			}
			?>
			<hr>
			<table style="border-color:dimgray;" cellpadding="0" cellspacing="0" width="100%">
				<tbody>
					<tr>
						<td class="mobile-block" style="width:70%;vertical-align: top">

							<p><u><strong>Title / ID:</strong></u></p>
							<p>
								<label>
									Title:
									<br class="mobile">
									<?php
									$db = read_news_entries();
									$title = '';
									if (isset($db[$today])) {
										$title = isset($db[$today]['title']) ?
											(is_array($db[$today]['title']) ?
												implode(',', $db[$today]['title']) :
												(string) $db[$today]['title']) :
											'';
									}
									?>
									<input class="mobile-input" type="text" style="padding: 5px;width:100%" placeholder="Place here your title:" value="<?php echo $title; ?>" name="title">
								</label>
							</p>

							<p>
								<label>ID:<br class="mobile">
									<input class="mobile-input" type="number" style="padding: 5px;width:100%" readonly value="<?php echo $today; ?>" name="today">
								</label>
							</p>

							<hr>
							<p><u><strong>Category:</strong></u></p>
							<label>Attach a category: </label>
							<select id="category-select" style="padding:5px;">
								<option value="">Select</option>
								<?php
								foreach (call_categories_from_db(false) as $category) {
									echo "<option value=\"$category\">";
									echo mb_ucfirst($category);
									echo '</option>' . PHP_EOL;
								}
								?>
								<option value="reset">Reset</option>
							</select>

							<label>Or new category: </label>
							<input id="category" name="category" value="" style="padding:5px">
							<button id="push-category-button" style="padding:5px" type="button" name="push-category">Attach</button>

							<hr>
							<p style="margin-bottom:0"><u><strong>Meta Data:</strong></u></p>

							<p>
								<label>
									<?php
									$db = read_news_entries();
									$description_file = news_article_directory($today) . '/description.txt';
									$description = is_file($description_file) ? file_get_contents($description_file) : '';
									?>
									Meta description:
									<br>
									<input class="mobile-input" type="text" style="padding: 5px;width:100%" name="description" value="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
								</label>
							</p>
							<p>
								<label>
									<?php
									$db = read_news_entries();
									$keywords = '';
									if (isset($db[$today])) {
										$keywords = isset($db[$today]['keywords']) ?
											(is_array($db[$today]['keywords']) ?
												implode(',', $db[$today]['keywords']) :
												(string) $db[$today]['keywords']) :
											'';
									}
									?>
									Meta keywords:
									<br>
									<input class="mobile-input" type="text" style="padding: 5px;width:100%" name='keywords' value="<?php echo $keywords; ?>">
								</label>
							</p>

							<?php
							$db = read_news_entries();
							$menu_title = '';
							$has_menu_title = false;
							if (isset($db[$today])) {
								$has_menu_title = isset($db[$today])
									&& array_key_exists('menu-title', $db[$today]);
								if ($has_menu_title) {
									$menu_title = is_array($db[$today]['menu-title'])
										? implode(',', $db[$today]['menu-title'])
										: (string) $db[$today]['menu-title'];
								}
							}
							if ($has_menu_title):
								?>
								<p>
									<label>
										Menu title:
										<br>
										<input class="mobile-input" type="text" style="padding: 5px;width:100%" name='menu-title' value="<?php echo $menu_title; ?>">
										<br>
									</label>
								</p>
							<?php endif; ?>

							<p>
								<label>
									<?php
									$db = read_news_entries();
									$date_format = '';
									if (isset($db[$today])) {
										$date_format = isset($db[$today]['date-format']) ?
											(is_array($db[$today]['date-format']) ?
												implode(',', $db[$today]['date-format']) :
												(string) $db[$today]['date-format']) :
											'';
									}
									?>
									Date format:
									<br>
									<input class="mobile-input" type="text" style="padding: 5px;width:100%" name='date-format' value="<?php echo $date_format; ?>">
									<br>
								</label>
							</p>

							<!--Show the custom variants forms-->
							<?php
							// Direct implementation of call_custom_variants for current entry only
							$db = read_news_entries();
							$variants = [];

							if (isset($db[$today])) {
								if (isset($db[$today])) {
									$entry_variants = array_keys($db[$today]);

									// Filter out default variants using the centralized function
									$variants = array_filter($entry_variants, function ($variant) {
										return !shouldContinueVariantForms($variant);
									});

									// Format variants
									$variants = array_map(function ($variant) {
										return str_replace(['-', '_'], ' ', mb_ucfirst($variant));
									}, array_values($variants));
								}
							}

							if ($variants != null) {
								foreach ($variants as $variant) {
									?>
									<p>
										<label>
											<?php
											$db = read_news_entries();
											$value = '';
											if (isset($db[$today])) {
												$value = isset($db[$today][$variant]) ?
													(is_array($db[$today][$variant]) ?
														implode(',', $db[$today][$variant]) :
														(string) $db[$today][$variant]) :
													'';
											}
											?>
											<?php echo $variant; ?>:
											<br>
											<?php
											$variant_name = mb_strtolower(str_replace(" ", "-", $variant));
											?>
											<textarea class="mobile-input" style="padding: 5px;width:100%" name="<?php echo 'variant-' . $variant_name; ?>"><?php echo $value; ?></textarea>
										</label>
									</p>
									<?php
								}
							}

							// Ordered fields: html, text-*, html-*

							$main_html_value = isset($db[$today]['html'])
								? (is_array($db[$today]['html'])
									? implode(',', $db[$today]['html'])
									: (string) $db[$today]['html'])
								: '';
							$main_html_value = clean_cms2_editor_html($main_html_value);

							$custom_keys = scan_custom_fields_from_html_2($editor_html_for_tags);
							$text_custom_keys = [];
							$html_custom_keys = [];
							foreach ($custom_keys as $k) {
								if (strpos($k, 'text-') === 0) {
									$text_custom_keys[] = $k;
								} elseif (strpos($k, 'html-') === 0) {
									$html_custom_keys[] = $k;
								}
							}
							?>

							<p>
								<label>
									Main Content (Html):
									<br>
									<div id="quill-editor-container" class="quill-editor-container" data-target-id="quill-html-content"></div>
									<textarea id="quill-html-content" class="mobile-input" style="display:none" name="html"><?php echo $main_html_value; ?></textarea>
								</label>
							</p>

							<?php foreach ($text_custom_keys as $db_key): ?>
								<?php
								$value = '';
								if (isset($db) && isset($db[$today][$db_key])) {
									$value = is_array($db[$today][$db_key])
										? implode(',', $db[$today][$db_key])
										: (string) $db[$today][$db_key];
								} else {
									$value = read_elem_by_class($html, $db_key);
								}
								$value = clean_cms2_text_for_textarea($value);
								?>
								<p>
									<label>
										<?php echo ucwords(str_replace(['-', '_'], ' ', $db_key)); ?>:
										<br>
										<textarea class="mobile-input" style="padding: 5px;width:100%" name="<?php echo 'variant-' . $db_key; ?>"><?php echo $value; ?></textarea>
									</label>
								</p>
							<?php endforeach ?>

							<?php foreach ($html_custom_keys as $db_key): ?>
								<?php
								$value = '';
								if (isset($db) && isset($db[$today][$db_key])) {
									$value = is_array($db[$today][$db_key])
										? implode(',', $db[$today][$db_key])
										: (string) $db[$today][$db_key];
								} else {
									$value = read_elem_by_class($html, $db_key);
								}
								$value = clean_cms2_editor_html($value);
								$editor_id = 'quill-' . preg_replace('/[^a-z0-9_-]/i', '-', $db_key);
								?>
								<p>
									<label>
										<?php echo ucwords(str_replace(['-', '_'], ' ', $db_key)); ?>:
										<br>
										<div class="quill-editor-container" data-target-id="<?php echo $editor_id; ?>"></div>
										<textarea id="<?php echo $editor_id; ?>" class="mobile-input" style="display:none" name="<?php echo 'variant-' . $db_key; ?>"><?php echo $value; ?></textarea>
									</label>
								</p>
							<?php endforeach ?>

						</td>
						<td class="desktop" style="width:2%">&nbsp;</td>
						<td class="mobile-block" style="width:28%;position:relative;vertical-align: top">
							<hr class="mobile">
							<p style="margin-bottom:0"><u><strong>Featured image:</strong></u></p>
							<br>
							<iframe class="uploader-iframe" scrolling="no" src="<?php echo update_url_params(array('upload-image' => true, 'variant' => 'image', 'today' => $today)); ?>"></iframe>

							<hr class="mobile" style="margin-top:16px">
							<p style="margin-bottom:0"><u><strong>Featured video:</strong></u></p>
							<br>
							<iframe class="uploader-iframe" scrolling="no" src="<?php echo update_url_params(array('upload-video' => true, 'variant' => 'video', 'today' => $today)); ?>"></iframe>
						</td>
					</tr>
				</tbody>
			</table>

		</form>
	<?php } else { ?>
		<p>You are not logged in as admin</p>
	<?php } ?>

	<?php if (!is_iframe()) { ?>
		<div id="copyright"><small>&copy; Andreas Express &bull; Program Ver:
				<?php echo date('ymd', filemtime(__FILE__)); ?> &bull; All Rights Reserved
			</small></div>
		<?php
	}
	?>

</body>

</html>

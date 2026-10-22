<?php

/**
 * This is a PHP 8 replacement function for the deprecated create_function function that allows developers to create anonymous functions at runtime using a string of PHP code as input.
 *
 * @param string The argument list for the anonymous function.
 * @param string The body of the anonymous function.
 * @return callable Returns a new anonymous function.
 */
if (!function_exists("create_function")) {
	function create_function($arg, $body)
	{
		static $cache = []; // A static array used to store previously created functions.
		static $max_cache_size = 64; // The maximum size of the cache.
		static $sorter; // A callback function used to sort the cache by hit count.

		if ($sorter === null) {

			//define the sorter callback function.
			$sorter = function ($a, $b) {
				if ($a->hits == $b->hits) {
					return 0;
				}
				return $a->hits < $b->hits ? 1 : -1;
			};
		}

		//generate a unique key for the current function.
		$crc = crc32($arg . "\\x00" . $body);
		if (isset($cache[$crc])) {

			//if the function has already been created and cached, increment the hit count and return the cached function.
			++$cache[$crc][1];
			return $cache[$crc][0];
		}

		if (sizeof($cache) >= $max_cache_size) {

			//if the cache size limit is reached, sort the cache by hit count and remove the least-used function.
			uasort($cache, $sorter);
			array_pop($cache);
		}

		//create a new anonymous function using `eval` and store it in the cache along with a hit count of 0.
		$cache[$crc] = [
			($cb = eval ("return function(" . $arg . "){" . $body . "};")),
			0,
		];
		return $cb;
	}
}
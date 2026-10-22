<?php

function clean_error_logs_in_server($dir)
{

	//clean all error logs from server
	$dh = new DirectoryIterator($dir);

	//directory object
	foreach ($dh as $item) {
		if (!$item->isDot()) {
			if ($item->isDir()) {

				clean_error_logs_in_server("$dir/$item");
			} else {

				if ($item->getFilename() == 'error_log') {

					unlink($dir . "/" . $item->getFilename());
					echo $dir . "/" . $item->getFilename() . "<br>";
					echo PHP_EOL . "<br>";

					//show in browser now
					flush_all();
				}
			}
		}
	}
}

//clean error logs
clean_error_logs_in_server($_SERVER['DOCUMENT_ROOT']);

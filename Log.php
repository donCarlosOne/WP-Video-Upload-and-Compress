<?php

//defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * This class provides the functionality for logging
 *
 * @author doncarlos
 */

class Log {

	protected static $instance;

	public function __construct() {

	}

	public function __destruct() {

	}

	public static function show($title, $event = "") {

		error_log("$title: " . ((is_array($event) || is_object($event)) ? stripslashes(json_encode($event)) : $event));
		usleep(25000);
	}

	/**
	 * This method logs the logged entry
	 * @param string $title log event title
	 * @param string $event event to log
	 * @return void
	 */
	public static function out($title, $event = "") {

		error_log("$title: " . ((is_array($event) || is_object($event)) ? stripslashes(json_encode($event)) : $event), 0);
		usleep(25000);
		return $event;
	}
}

?>
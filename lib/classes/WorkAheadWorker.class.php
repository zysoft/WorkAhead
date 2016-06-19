<?php
/**
 * WorkAhead library worker to be used in thread. Just helper to wrap library parameters pass
 *
 * @package WorkAhead.core
 * @author Yuriy Zisin <devel@yzisin.com>
 * @copyright 2008 Yuriy Zisin
 * @license GNU General Public License 
 */

/**
 * Worker class
 *
* @package WorkAhead.core
*/
class WorkAheadWorker {

	/**
	 * Parsed command line arguments
	 *
	 * @var array
	 */
	private $arguments = array();

	/**
	 * Creates worker instance and loads all command line parameters
	 *
	 */
	public function __construct() {
		foreach ($_ENV as $key=>$val) {
			$this->arguments["{$key}"] = $val;
		}
	}


	/**
	 * Magic method to cover easy way parameters requests
	 *
	 * Really slow, but easy to use
	 *
	 * @param string $argName Requested argument name
	 *
	 * @return mixed Parameter value or null if the parameter does't present
	 */
	private function __get($argName) {
		if (isset($this->arguments["{$argName}"])) {
			return $this->arguments["{$argName}"];
		}
		return $null;
	}


	/**
	 * Returns requested argument value or null if there is no one
	 * A way FASTER than magic method
	 *
	 * @param string $argName Argument name
	 *
	 * @return mixed Parameter value or null if the parameter does't present
	 */
	public function getArgument($argName) {
		return $this->__get($argName);
	}

}



//Running worker script in background
declare(ticks=1);

$pid = pcntl_fork();

if ($pid == -1) {
	die();
}
if ($pid) {
	exit(0);
} else {

}





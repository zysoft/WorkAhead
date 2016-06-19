<?php
/**
 * WorkAhead library manager
 *
 * @package WorkAhead.core
 * @author Yuriy Zisin <devel@yzisin.com>
 * @copyright 2008 Yuriy Zisin
 * @license GNU General Public License 
 */


/**
 * WorkAhead Manager class
 *
 * @package WorkAhead.core
 */
class WorkAheadManager {

	/**
	 * Constants to describe config type
	 *
	 */
	const CONFIG_TYPE_FILE = 0;
	const CONFIG_TYPE_STRING = 1;
	const CONFIG_TYPE_SIMPLEXML = 2;

	/**
	 * Constants to describe stats storage type
	 *
	 */
	const STORAGE_FILE = 0;
	const STORAGE_JSON = 1;
	const STORAGE_MYSQL = 2;

	/**
	 * Constants to determine operations to log
	 *
	 */
	const LOG_MAX_LEVEL = 5;
	const LOG_CONF_LOAD = 'Config loaded';
	const LOG_CONF_NOT_LOADED = 'Config is not loaded';
	const LOG_STATS_AUTOLOAD = 'Stats autoload found';
	const LOG_STATS_AUTOLOAD_START = 'Stats autoload started';
	const LOG_STATS_AUTOLOAD_END = 'Stats autoload finished';
	const LOG_STATS_SAVE = 'Stats save';
	const LOG_STATS_SAVE_START = 'Stats save started';
	const LOG_STATS_SAVE_END = 'Stats save finished';
	const LOG_VISITED_PAGE = 'Visited page';
	const LOG_GUESSED_PAGE = 'Guessed next page';
	const LOG_PAGE_NOT_GUESSED = 'Page was not guessed';
	const LOG_THREAD_FORK = 'Initiating worker thread';
	const LOG_THREAD_FORK_START = 'Initiating worker thread start';
	const LOG_THREAD_FORK_END = 'Worker thread initiated. Main thread is now continue';
	const LOG_THREAD_FORK_DATA = "Thread initial data:\n";
	const LOG_PROBABILITY = 'Next page detected with probability';
	/**
	 * Instance
	 *
	 * @var WorkAheadManager
	 */
	private static $instance = null;

	/**
	 * Current config
	 *
	 * @var SimpleXMLElement
	 */
	private $config = null;

	/**
	 * Current statistical data
	 *
	 * @var array Array with statical data
	 */
	private $stats = null;


	/**
	 * Database operations queue. Used for MySQL stats storage 
	 *
	 * @var array
	 */
	private $dbQueue = array();
	
	
	/**
	 * Database connection used to query/update data
	 *
	 * @var resource
	 */
	private $dbConnection = null;
	
	/**
	 * Log level from config file
	 *
	 * @var int
	 */
	private $logLevel = 0;

	/**
	 * Returns self instance if has one, or creates new
	 *
	 * @return WorkAheadManager
	 */
	public static function getInstance() {
		if (self::$instance == null) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Construts the object and prevents misuse due to Singleton pattern
	 *
	 */
	private function __construct() {

	}
	
	/**
	 * Prevents object cloning due to Singleton pattern
	 *
	 */
	private function __clone() {

	}


	/**
	 * Loads a config file
	 *
	 * @param mixed $config Can be XML string, SimpleXMLElement or XML config filename (with path)
	 * @param bool  $noAutoload Specifies not to perform autoloading even if it is specified in config. Used for system purposes
	 */
	public function loadConfig($config, $noAutoload = false) {
		$configType = self::CONFIG_TYPE_FILE;
		if ($config instanceof SimpleXMLElement) {
			$configType = self::CONFIG_TYPE_SIMPLEXML;
		} elseif (strpos($config, '<?xml') !== false) {
			$configType = self::CONFIG_TYPE_STRING;
		}

		try {
			switch ($configType) {
				case self::CONFIG_TYPE_SIMPLEXML:
					$this->config = $config;
					break;
				case self::CONFIG_TYPE_STRING:
					$this->config = new SimpleXMLElement($config);
					break;
				case self::CONFIG_TYPE_FILE:
					$this->config = new SimpleXMLElement(file_get_contents($config));
					break;
			}
			
			$this->logLevel = $this->config->Stats->Log->LogLevel;

			$this->logOperation(self::LOG_CONF_LOAD);
			
			
			
			if ($noAutoload) {
				return;
			}
			// Use autoload if needed
			$autoloadStorage = $this->config->Stats->Autoload->Storage;
			if ($autoloadStorage != 'NONE') {
				$this->logOperation(self::LOG_STATS_AUTOLOAD);
				switch ($autoloadStorage) {
					case 'FILE':
						$this->loadStats(self::STORAGE_FILE, $this->config->Stats->Autoload->Filename);
						break;
					case 'DATABASE':
						$this->loadStats(self::STORAGE_MYSQL);
						break;
				}
			}
			
		} catch (Exception $e) {

		}

	}
	
	
	/**
	 * Returns self config
	 *
	 * @return SimpleXMLElement
	 */
	public function getConfig() {
		return $this->config;
	}
	

	/**
	 * Loads statistical data from a storage
	 *
	 * @param int $storageType Storage type
	 * @param string $storageData Data for STORAGE_JSON (json string) or STORAGE_FILE (file name and path) storage type
	 */
	public function loadStats($storageType, $storageData = null) {
		try {
			switch ($storageType) {
				case self::STORAGE_FILE:
					$this->logOperation(self::LOG_STATS_AUTOLOAD_START,'Storage:FILE ('.$storageData.')');
					$this->stats = unserialize(file_get_contents($storageData));
					$this->logOperation(self::LOG_STATS_AUTOLOAD_END,'Storage:FILE ('.$storageData.')');
					break;
				case self::STORAGE_JSON:
					$this->logOperation(self::LOG_STATS_AUTOLOAD_START,'Storage:JSON ('.$storageData.')');
					$this->stats = json_decode($storageData);
					$this->logOperation(self::LOG_STATS_AUTOLOAD_END,'Storage:JSON ('.$storageData.')');
					break;
				case self::STORAGE_MYSQL:
					$this->logOperation(self::LOG_STATS_AUTOLOAD_START,'Storage:MYSQL');
					$this->loadMySqlStats();
					$this->logOperation(self::LOG_STATS_AUTOLOAD_END,'Storage:MYSQL');
					break;
			}
		} catch (Exception $e) {

		}
	}


	/**
	 * Saves statistical data to a storage
	 *
	 * @param int $storageType Storage type
	 * @param string $fileName Filename to save stats for STORAGE_FILE type
	 *
	 * @return mixed JSON string or true/false
	 */
	public function saveStats($storageType, $fileName = null) {
		try {
			switch ($storageType) {
				case self::STORAGE_FILE:
					$this->logOperation(self::LOG_STATS_SAVE_START,'Storage:FILE ('.$fileName.')');
					file_put_contents($fileName,serialize($this->stats));
					$this->logOperation(self::LOG_STATS_SAVE_END,'Storage:FILE ('.$fileName.')');
					break;
				case self::STORAGE_JSON:
					$this->logOperation(self::LOG_STATS_SAVE_START,'Storage:JSON');
					return json_encode($this->stats);
					$this->logOperation(self::LOG_STATS_SAVE_END,'Storage:JSON');
					break;
				case self::STORAGE_MYSQL:
					$this->logOperation(self::LOG_STATS_SAVE_START,'Storage:MYSQL');
					$this->saveMySqlStats();
					$this->logOperation(self::LOG_STATS_SAVE_END,'Storage:MYSQL');
					break;
			}
		} catch (Exception $e) {
			return false;
		}
		return true;
	}



	/**
	 * Loads stats from MySQL database
	 */
	private function loadMySqlStats(){
		$this->checkDbConnect();
		$query = "SELECT * FROM ".$this->config->MySql->table;
		$res = mysql_query($query);
		$this->stats = array();
		$stats =& $this->stats;
		
		//Creating stats data array
		while ($row = mysql_fetch_assoc($res)) {
			$element =& $stats[$row['sourcePageId']];
			if (!is_array($element)) {
				$element = array();
			}
			$element[$row['destinationPageId']] = $row['references'];
		}
		
		//Building totals
		foreach ($stats as $key =>& $stat) {
			$count = 0;
			foreach ($stat as $referal) {
				$count += $referal;
			}
			$stat['PAGE_STATS_TOTAL'] = $count;
		}
		
	}

	/**
	 * Saves stats to MySQL database
	 */
	private function saveMySqlStats(){
		$this->checkDbConnect();
		foreach ($this->dbQueue as $query) {
			mysql_query($query, $this->dbConnection);
		}
	}


	/**
	 * Ckecks MySQL connection. Establish one if not connected
	 *
	 */
	private function checkDbConnect() {
		if ($this->dbConnection == null) {
			$this->dbConnection = mysql_connect($this->config->MySql->host, $this->config->MySql->username, $this->config->MySql->password);
			mysql_select_db($this->config->MySql->database, $this->dbConnection);
		}
	}
	

	/**
	 * Runs detection process
	 *
	 * @param string $visitedPageId The current page ID user is now on (according to config site map)
	 * @param string $referrerPageId The page ID user came from (according to config site map)
	 *
	 * @return bool Returns true on success or false on fail
	 */
	public function pageVisit($visitedPageId, $refererPageId) {
		if (!$this->config) {
			$this->logOperation(self::LOG_CONF_NOT_LOADED);
			return false;
		}
		$this->logOperation(self::LOG_VISITED_PAGE, 'page id: '.$visitedPageId);
		//Checking for pages Ids to be real
		$visited = false;
		$referer = false;
		$pages = $this->config->LookupMap->Page;
		$needStats = ($this->stats === null);
		if ($needStats) {
			$this->stats = array();
		}
		foreach ($pages as $page) {
			$pageId = $page['id'];
			if ($pageId == $visitedPageId) {
				$visited = $page;
			}
			if ($pageId == $refererPageId) {
				$referer = $page;
			}
				
			if ($needStats) {
				$this->stats["{$pageId}"] = array('PAGE_STATS_TOTAL' => 0);
				$statPage =& $this->stats["{$pageId}"];
				foreach ($page->Reference as $reference) {
					$refId = $reference['targetId'];
					$statPage["{$refId}"] = 0;
				}
			}
		}

		if (!($visited && $referer)) {
			return false;
		}

		//Beginning run
		$refId = $referer['id'];
		$visId = $visited['id'];
		$this->stats["{$visId}"]['PAGE_STATS_TOTAL']++;
		$this->stats["{$refId}"]["{$visId}"]++;
		$this->dbQueue[] = "UPDATE `".$this->config->MySql->table."` SET `references` = `references`+1 WHERE sourcePageId='".$refId."' AND destinationPageId='".$visId."'";


		$nextPage = $this->getProbability($visId);

		//Saving stats if they were autoloaded
		$autoloadStorage = $this->config->Stats->Autoload->Storage;
		if ($autoloadStorage != 'NONE') {
			$this->logOperation(self::LOG_STATS_SAVE);
			switch ($autoloadStorage) {
				case 'FILE':
					$this->saveStats(self::STORAGE_FILE, $this->config->Stats->Autoload->Filename);
					break;
				case 'DATABASE':
					$this->saveStats(self::STORAGE_MYSQL);
					break;
			}
		}
		
		
		if ($nextPage === false) {
			$this->logOperation(self::LOG_PAGE_NOT_GUESSED);
			return true;
		}

		$this->logOperation(self::LOG_GUESSED_PAGE, 'page id: '.$nextPage);
		
		$this->logOperation(self::LOG_THREAD_FORK);
		
		//Call string
		$callStr = $this->config->Php->CallName . ' '.
					$this->config->Stats->Call['script'];
		//Env params			
		$env = array();
		$pgIdName = $this->config->Stats->Call['interpretPageIdAs'];
		$env["{$pgIdName}"] = $nextPage;

		$params =& $this->config->Stats->Call->Parameter;
		
		//Creaing parameters for call string
		foreach ($params as $param) {
			$name = ''.$param['name'];
			$scope = $param['scope'];
			$interpret = $param['interpretAs'];
			if ($scope == 'internal') {
				$env["{$interpret}"] = $name();
			} else {
				
				switch ($scope) {
					case 'cookie':
						$scope = $_COOKIE;
						break;
					case 'session': 
						$scope = $_SESSION;
						break;
					default:
						$scope = array();
						break;
				}
				$env["{$interpret}"] = $scope["{$name}"];
			}
		}

		$cwd = $this->config->Stats->Call['callDir'];

		if ($this->logLevel) {
			ob_start();
			ob_implicit_flush(0);
			echo 'RUN COMMAND='.$callStr."\n";
			echo 'INITIAL DIR='.$cwd."\n";
			echo "DATA:\n";
			print_r($env);
			$this->logOperation(self::LOG_THREAD_FORK_DATA, ob_get_clean());
		}

		//Running background worker
		$descriptspec = array(
								0 => array('pty'), 
								1 => array('pty'), 
								2 => array('pty')
							);

		$this->logOperation(self::LOG_THREAD_FORK_START);
		$process = proc_open($callStr, $descriptspec , $pipes, $cwd, $env);
		if (!is_resource($process)) {
			return false;
		}
		foreach($pipes as $pipe) {
			fclose($pipe);
		}
		proc_close($process);
		$this->logOperation(self::LOG_THREAD_FORK_END);
		
		return true;
	}


	/**
	 * Returns the most probable next user move
	 *
	 * @param string $visitedPageId Id of the page user is currenly requested
	 *
	 * @return mixed Page Id user will most probably go to. False if cannot guess
	 */
	private function getProbability($visitedPageId) {

		$followers =& $this->stats["{$visitedPageId}"];
		$totals =  $followers['PAGE_STATS_TOTAL'];
	
		//Determining if we need to decrease current page ststs
		$needDecrease = ($totals >= $this->config->Stats->Lower->DecreaseOn);
		$decreaseValue =  $this->config->Stats->Lower->DivideBy;

		if ($needDecrease) {
			$this->dbQueue[] = "UPDATE `".$this->config->MySql->table."` SET `references` = `references`/".$decreaseValue." WHERE sourcePageId='".$visitedPageId."'";
		}
		
		$nextPage = 0;
		$nextProbab = 0;
		$minReact = $this->config->Stats->MinProbability;
		foreach ($followers as $key => &$referer) {
			if ($key == 'PAGE_STATS_TOTAL') {
				continue;
			}
			$probability = ($referer/$totals)*100;
			if ($probability > $minReact) {
				if ($probability > $nextProbab ) {
					$nextPage = $key;
					$nextProbab = $probability;
				}
			}
			//Decrease stats if needed
			if ($needDecrease) {
				$referer = round ($referer / $decreaseValue);
			}
			
		}
		
		//Decrease stats if needed
		if ($needDecrease) {
			$followers['PAGE_STATS_TOTAL'] = round($totals / $decreaseValue);
		}
		
		if ($nextPage === 0) {
			return false;
		}
		$this->logOperation(self::LOG_PROBABILITY, $nextProbab);
		return $nextPage;
	}


	/**
	 * Logs operations via error_log function depending on loglevel
	 *
	 * @param string $opString Operation string
	 * @param string $params Additional string
	 */
	private function logOperation($opString, $params = null) {
		if (!$this->logLevel) {
			return;
		}
		
		//We will log iterationaly from lower loglevel to higher
		$logLevel = $this->logLevel;
		for ($i = 1; $i <= $logLevel; $i++) {
			$funcName = 'logLevel'.$i;
			$this->$funcName($opString, $params);
		}
	}

	/**
	 * Logs operations for level 1
	 *
	 * @param string $opString Operation string
	 * @param string $params Additional string
	 */
	private function logLevel1($opString, $params = null) {
		switch ($opString) {
			case self::LOG_CONF_LOAD: 
			case self::LOG_STATS_AUTOLOAD: 
			case self::LOG_STATS_SAVE: 
			case self::LOG_VISITED_PAGE:
			case self::LOG_GUESSED_PAGE:
			case self::LOG_PAGE_NOT_GUESSED:
			case self::LOG_THREAD_FORK:
			case self::LOG_CONF_NOT_LOADED:	
				error_log($opString.' '.$params);
				break;
			default:
				break;
		}
	}

	
	/**
	 * Logs operations for level 2
	 *
	 * @param string $opString Operation string
	 * @param string $params Additional string
	 */
	private function logLevel2($opString, $params = null) {
		switch ($opString) {
			case self::LOG_STATS_AUTOLOAD_START: 
			case self::LOG_STATS_AUTOLOAD_END: 
			case self::LOG_STATS_SAVE_START:
			case self::LOG_STATS_SAVE_END:
			case self::LOG_THREAD_FORK_START:
			case self::LOG_THREAD_FORK_END:
			case self::LOG_PROBABILITY:
				error_log($opString.' '.$params);
				break;
			default:
				break;
		}
		
	}
	

	/**
	 * Logs operations for level 3
	 *
	 * @param string $opString Operation string
	 * @param string $params Additional string
	 */
	private function logLevel3($opString, $params = null) {
		switch ($opString) {
			case self::LOG_THREAD_FORK_DATA:
				error_log($opString.' '.$params);
				break;
			default:
				break;
		}
		
	}
	
	
	
}
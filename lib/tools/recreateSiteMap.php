<?php
/**
 * Tool to create/recreate stats table from config
 * @author Yuriy Zisin <devel@yzisin.com>
 * @copyright 2008 Yuriy Zisin
 * @license GNU General Public License 
 * @package WorkAhead.tools
 */

echo <<<EOF
WorkAhead Copyright (C) 2008 Yuriy Zisin <devel@yzisin.com>
This program comes with ABSOLUTELY NO WARRANTY; See COPYING for more details.
This is free software, and you are welcome to redistribute it
under certain conditions; See COPYING for more details.
                
EOF;
if ($_SERVER['argc'] < 3) {
	echo "\n\nUsage: php ".$_SERVER['PHP_SELF']." CONFIG_PATH_AND_NAME PATH_TO_LIBRARY_CLASSES [--force]\n\n";
	die;
}

$argv = $_SERVER['argv'];

if ('--force' != $argv[3]) {
	echo "\n\n!!!WARNING!!!\nYou are about to recreate stats table. It will zero all stats.\nAre you sure? [y/N]:";
	$fp=fopen("/dev/stdin", "r");
	$input = fgets($fp, 255);
	fclose($fp);
	if (trim($input) != 'y') {
		die("Canceled\n\n");
	}
}
ini_set('error_log','/dev/null');


require $argv[2].'/WorkAheadManager.class.php';

$manager = WorkAheadManager::getInstance();
$manager->loadConfig($argv[1], true);
$manager->pageVisit(null, null);
$jsonInitialStats = $manager->saveStats(WorkAheadManager::STORAGE_JSON);
$statsMap = json_decode($jsonInitialStats);

$conf = $manager->getConfig();
$table = $conf->MySql->table;

mysql_connect($conf->MySql->host, $conf->MySql->username, $conf->MySql->password) or die('Cannot connect to MySQL datbase. Check your config');
mysql_select_db($conf->MySql->database) or die('Cannot select database. Check your config file');


$sql = file_get_contents('createTable.sql');
$sql = explode(';',str_replace('%TABLE_NAME%', $table, $sql));
foreach ($sql as $query) {
	mysql_query($query);
}

foreach ($statsMap as $key => $page) {
	$page =(array) $page;
	$refCount = count($page);
	$referals = array_keys($page);
	for ($i=1;$i<$refCount;$i++) {
		mysql_query('INSERT INTO `'.$table."` VALUES ('".$key."','".$referals[$i]."',0)");
	}
}

echo "Done\n\n";

<?php
/**
* Example cron script.
* @author Hugh Nougher <hughnougher@gmail.com>
*/

if (!defined('HNWC_CRON')) {
	die('Direct access to this script is forbidden.'); // Must be used from cron.php
}

out('I was last run on ' . date('r', $lastRun));

// On the next minute
$minute = 60;
return floor((time() + $minute) / $minute) * $minute;

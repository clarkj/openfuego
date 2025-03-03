<?php namespace OpenFuego;

use OpenFuego\lib\Logger as Logger;
use OpenFuego\lib\TwitterHandle as TwitterHandle;

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300) {
	die(__NAMESPACE__ . ' requires PHP 5.3.0 or higher.');
}

if (php_sapi_name() != 'cli') {
	die('This script must be invoked from the command line.');
}

require_once(__DIR__ . '/init.php');

// use Abraham\TwitterOAuth\TwitterOAuth;
// $twitter = new TwitterHandle();

$twitter = new TwitterHandle(\OpenFuego\TWITTER_CONSUMER_KEY, \OpenFuego\TWITTER_CONSUMER_SECRET, \OpenFuego\TWITTER_OAUTH_TOKEN, \OpenFuego\TWITTER_OAUTH_SECRET);
$twitter->get("account/verify_credentials", array("include_entities" => 0, "skip_status" => 1));
$twitter_http_code = $twitter->getLastHttpCode();

if ($twitter_http_code == 200) {
	Logger::info("Twitter credentials verified.");
} else {
	$error_message = "Cannot continue. Twitter credentials not valid. Error code: {$twitter_http_code}.\n";
	Logger::info($error_message);
	die($error_message);
}

if (!function_exists('pcntl_fork')) {
	$error_message = "\n"
		. 'To start OpenFuego, run these commands:'
		. "\n\n"
		. "\tnohup " . \PHP_BINDIR . '/php ' . BASE_DIR . '/collect.php > /dev/null 2> /dev/null & echo $!'
		. "\n"
		. "\tnohup " . \PHP_BINDIR . '/php ' . BASE_DIR . '/consume.php > /dev/null 2> /dev/null & echo $!'
		. "\n\n";

	die($error_message);
}

// Ignore hangup signal (when user exits shell)
pcntl_signal(SIGHUP, SIG_IGN);

// Handle shutdown tasks
pcntl_signal(SIGTERM, function() {

	global $_should_stop;
	$_should_stop = TRUE;

	Logger::info("Received shutdown request, finishing up.");

	return;
});

$pids = array();

$pids[0] = pcntl_fork();

if (!$pids[0]) {
	include_once(__DIR__ . '/collect.php');
}

$pids[1] = pcntl_fork();

if (!$pids[1]) {
	include_once(__DIR__ . '/consume.php');
}

echo __NAMESPACE__ . ' collector running as PID ' . $pids[0] . "\n";
echo __NAMESPACE__ . ' consumer running as PID ' . $pids[1] . "\n";

@file_put_contents(\OpenFuego\TMP_DIR . '/OpenFuego-collect.pid', $pids[0]);
@file_put_contents(\OpenFuego\TMP_DIR . '/OpenFuego-consume.pid', $pids[1]);

exit;
?>

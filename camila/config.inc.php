<?php
if (file_exists('var/config.php')) {
    require('var/config.php');
} else {
	$path = dirname($_SERVER['SCRIPT_FILENAME'], 3);
	require($path.'/'.'var/config.php');
}
?>

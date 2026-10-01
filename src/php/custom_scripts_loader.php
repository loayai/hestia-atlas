<?php
/*
 * Hestia Atlas theme loader (installed by hestia-atlas/install.sh).
 * Hestia includes every PHP file in web/js/custom_scripts/ inside <head>.
 * The theme library lives outside the web root; if it is missing this file does nothing.
 */
if (is_file("/usr/local/hestia-atlas/lib/head.php")) {
	require_once "/usr/local/hestia-atlas/lib/head.php";
}

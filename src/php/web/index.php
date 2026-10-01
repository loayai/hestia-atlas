<?php
/**
 * Hestia Atlas theme — Tools / dashboard page (/atlas/).
 * Uses Hestia's own bootstrap, authentication and page renderer.
 */
$TAB = "TOOLS";

include $_SERVER["DOCUMENT_ROOT"] . "/inc/main.php";

$ha_lib = "/usr/local/hestia-atlas/lib";
if (!is_file($ha_lib . "/dashboard.php")) {
	header("Location: /");
	exit();
}
require_once $ha_lib . "/bootstrap.php";
require_once $ha_lib . "/dashboard.php";

// The Tools page belongs to the Atlas theme; other themes keep Hestia's own home.
if (!ha_is_active()) {
	header("Location: /");
	exit();
}

$ha_dashboard = ha_dashboard_data();

// render_page() includes "templates/pages/<page>.php"; point it at our view.
render_page($user, $TAB, ha_view_path("dashboard"));

$_SESSION["back"] = $_SERVER["REQUEST_URI"];

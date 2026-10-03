<?php
/**
 * Hestia Atlas theme — shared bootstrap.
 *
 * Loaded from Hestia's own request context (after web/inc/main.php), so Hestia's
 * session, helpers (tohtml, get_user_data, humanize_usage_*) and gettext are available.
 * Nothing here executes Hestia commands with user input; data comes from the
 * same v-list-* calls Hestia's own pages use.
 */

if (defined("HA_VERSION")) {
	return;
}

define("HA_VERSION", "1.0.0");
define("HA_THEME", "atlas");
// Selectable Hestia theme ids that use the Atlas shell (one per colour scheme).
define("HA_THEMES", ["atlas", "atlas-evergreen"]);
define("HA_LIB", __DIR__);
define("HA_ASSETS", "/atlas/assets");
// Applies the remembered (or system) light/dark mode before first paint.
define("HA_MODE_BOOT", '(function(){var h=document.documentElement,m;try{m=localStorage.getItem("ha-color-mode")}catch(e){}if(m!=="light"&&m!=="dark"){m=window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"}h.setAttribute("data-ha-mode",m)})();');

/** Escape for HTML (falls back if Hestia's helper is unavailable). */
function ha_e($value): string {
	if (function_exists("tohtml")) {
		return tohtml(is_array($value) ? "" : $value);
	}
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

/** Translate through Hestia's gettext catalogue; untranslated strings stay English. */
function ha_t(string $text): string {
	return function_exists("_") ? _($text) : $text;
}

/** Asset URL with cache-busting version. */
function ha_asset(string $path): string {
	return HA_ASSETS . "/" . ltrim($path, "/") . "?v=" . HA_VERSION;
}

/** Inline SVG icon referencing the bundled Lucide sprite. */
function ha_icon(string $name, string $class = ""): string {
	$href = HA_ASSETS . "/icons.svg?v=" . HA_VERSION . "#" . $name;
	return '<svg class="ha-i ' .
		ha_e($class) .
		'" aria-hidden="true" focusable="false"><use href="' .
		ha_e($href) .
		'"></use></svg>';
}

/** Tool icon: line icon on a tinted tile. */
function ha_tool_icon(string $icon): string {
	return '<span class="ha-tool-icon">' . ha_icon($icon, "ha-tool-icon-main") . "</span>";
}

/** Read a value from /usr/local/hestia-atlas/atlas.conf (KEY='value' lines). */
function ha_config(string $key, string $default = ""): string {
	static $conf = null;
	if ($conf === null) {
		$conf = [];
		$file = dirname(HA_LIB) . "/atlas.conf";
		if (is_readable($file)) {
			foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
				if (preg_match("/^([A-Z_]+)=(.*)$/", trim($line), $m)) {
					$conf[$m[1]] = trim($m[2], "'\" ");
				}
			}
		}
	}
	return $conf[$key] ?? $default;
}

/** The theme currently selected for this session (user preference or system default). */
function ha_selected_theme(): string {
	$theme = !empty($_SESSION["userTheme"]) ? $_SESSION["userTheme"] : $_SESSION["THEME"] ?? "";
	return (string) $theme;
}

function ha_is_active(): bool {
	return in_array(ha_selected_theme(), HA_THEMES, true);
}

/**
 * Build the request context used by navigation, header and dashboard.
 * Mirrors the permission logic of Hestia's templates/includes/panel.php.
 */
function ha_context(): array {
	static $ctx = null;
	if ($ctx !== null) {
		return $ctx;
	}
	$s = $_SESSION;
	$look = $s["look"] ?? "";
	$user = $look !== "" ? $look : $s["user"] ?? "";
	$data = [];
	if ($user !== "" && function_exists("get_user_data")) {
		$all = get_user_data($user);
		$data = $all[$user] ?? [];
	}
	$isAdmin = ($s["userContext"] ?? "") === "admin";
	$root = $s["ROOT_USER"] ?? "admin";
	$protectedAdmin = ($s["POLICY_SYSTEM_PROTECTED_ADMIN"] ?? "") === "yes";
	$impersonatingRoot = $isAdmin && $look !== "" && $user === $root && $protectedAdmin;
	$has = fn($key) => !empty($s[$key]);
	$limit = fn($key) => ($data[$key] ?? "0") !== "0";
	$api = is_numeric($s["API_SYSTEM"] ?? "") ? (int) $s["API_SYSTEM"] : 0;

	$ctx = [
		"user" => $user,
		"login_user" => $s["user"] ?? "",
		"data" => $data,
		"token" => $s["token"] ?? "",
		"is_admin" => $isAdmin,
		"looking" => $look !== "",
		"root_user" => $root,
		"impersonating_root" => $impersonatingRoot,
		"app_name" => $s["APP_NAME"] ?? "Hestia Control Panel",
		"can" => [
			"users" => $isAdmin && $look === "",
			"web" => $has("WEB_SYSTEM") && $limit("WEB_DOMAINS"),
			"dns" => $has("DNS_SYSTEM") && $limit("DNS_DOMAINS"),
			"mail" => $has("MAIL_SYSTEM") && $limit("MAIL_DOMAINS"),
			"db" => $has("DB_SYSTEM") && $limit("DATABASES"),
			"cron" => $has("CRON_SYSTEM") && $limit("CRON_JOBS"),
			"backup" =>
				$has("BACKUP_SYSTEM") &&
				($limit("BACKUPS") ||
					($data["U_BACKUPS"] ?? "0") !== "0" ||
					($data["BACKUPS_INCREMENTAL"] ?? "") === "yes"),
			"backup_incremental" => ($data["BACKUPS_INCREMENTAL"] ?? "") === "yes",
			"file_manager" => ($s["FILE_MANAGER"] ?? "") === "true" && !$impersonatingRoot,
			"terminal" =>
				($s["WEB_TERMINAL"] ?? "") === "true" &&
				!$impersonatingRoot &&
				($s["login_shell"] ?? "nologin") !== "nologin",
			"server" =>
				(($isAdmin && ($s["POLICY_SYSTEM_HIDE_SERVICES"] ?? "") !== "yes") ||
					($s["user"] ?? "") === $root) &&
				!($isAdmin && $look !== ""),
			"firewall" => $has("FIREWALL_SYSTEM"),
			"firewall_extended" => ($s["FIREWALL_EXTENSION"] ?? "") !== "",
			"profile" => !$impersonatingRoot && ($data["SUSPENDED"] ?? "no") === "no",
			"logs" => $isAdmin || ($s["POLICY_USER_VIEW_LOGS"] ?? "") !== "no",
			"access_keys" => ($user === $root && $api > 0) || ($user !== $root && $api > 1),
			"docs" => ($s["HIDE_DOCS"] ?? "") !== "yes",
			"notifications" => !$impersonatingRoot,
			"theme_change" => ($s["POLICY_USER_CHANGE_THEME"] ?? "") !== "no",
		],
	];
	return $ctx;
}

/** Edit-profile URL for the effective user. */
function ha_profile_url(array $ctx): string {
	return "/edit/user/?" . http_build_query(["user" => $ctx["user"], "token" => $ctx["token"]]);
}

/** Percentage helper for resource meters; null when unlimited. */
function ha_percent($used, $limit): ?float {
	if ($limit === "unlimited" || $limit === "" || $limit === null) {
		return null;
	}
	$limit = (float) $limit;
	if ($limit <= 0) {
		return 100.0;
	}
	return max(0.0, min(100.0, ((float) $used / $limit) * 100));
}

/** Human readable size from Hestia's MB based counters. */
function ha_size($mb): string {
	if ($mb === "unlimited") {
		return "∞";
	}
	if (function_exists("humanize_usage_size")) {
		return trim(humanize_usage_size($mb) . " " . strtoupper((string) humanize_usage_measure($mb)));
	}
	return $mb . " MB";
}

require_once HA_LIB . "/nav.php";

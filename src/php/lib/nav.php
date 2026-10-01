<?php
/**
 * Navigation registry: maps Hestia features onto a task-oriented section hierarchy.
 *
 * Every entry points at an existing Hestia page; nothing here adds behaviour.
 * Visibility mirrors Hestia's own panel permissions (see ha_context()).
 */

/**
 * Groups of tools. Each item:
 *   id, label, href, icon, desc, show (bool), nav (sidebar), tool (dashboard)
 */
function ha_nav_groups(array $ctx): array {
	$can = $ctx["can"];
	$profile = ha_profile_url($ctx);
	$pma = !empty($_SESSION["DB_PMA_ALIAS"]) ? "/" . $_SESSION["DB_PMA_ALIAS"] . "/" : "/phpmyadmin/";
	$pga = !empty($_SESSION["DB_PGA_ALIAS"]) ? "/" . $_SESSION["DB_PGA_ALIAS"] . "/" : "/phppgadmin/";
	$dbSystem = (string) ($_SESSION["DB_SYSTEM"] ?? "");
	$systemLog = "/list/log/?" . http_build_query(["user" => "system", "token" => $ctx["token"]]);
	$t = "ha_t";

	$groups = [
		[
			"id" => "domains",
			"label" => $t("Domains"),
			"icon" => "globe",
			"items" => [
				["id" => "web", "label" => $t("Web Domains"), "href" => "/list/web/", "icon" => "app-window", "show" => $can["web"], "desc" => $t("Websites, aliases, SSL and web server settings")],
				["id" => "web-add", "label" => $t("Add Web Domain"), "href" => "/add/web/", "icon" => "globe", "show" => $can["web"], "nav" => false],
				["id" => "dns", "label" => $t("DNS Zones"), "href" => "/list/dns/", "icon" => "network", "show" => $can["dns"], "desc" => $t("Zones and records for your domains")],
				["id" => "dns-add", "label" => $t("Add DNS Domain"), "href" => "/add/dns/", "icon" => "waypoints", "show" => $can["dns"], "nav" => false],
			],
		],
		[
			"id" => "email",
			"label" => $t("Email"),
			"icon" => "mail",
			"items" => [
				["id" => "mail", "label" => $t("Mail Domains"), "href" => "/list/mail/", "icon" => "mails", "show" => $can["mail"], "desc" => $t("Mail domains, accounts, forwarders and DKIM")],
				["id" => "mail-accounts", "label" => $t("Email Accounts"), "href" => "/list/mail/", "icon" => "user-round", "show" => $can["mail"], "nav" => false],
				["id" => "mail-add", "label" => $t("Add Mail Domain"), "href" => "/add/mail/", "icon" => "inbox", "show" => $can["mail"], "nav" => false],
			],
		],
		[
			"id" => "databases",
			"label" => $t("Databases"),
			"icon" => "database",
			"items" => [
				["id" => "db", "label" => $t("Databases"), "href" => "/list/db/", "icon" => "database", "show" => $can["db"], "desc" => $t("MySQL / MariaDB and PostgreSQL databases")],
				["id" => "db-add", "label" => $t("Add Database"), "href" => "/add/db/", "icon" => "database-zap", "show" => $can["db"], "nav" => false],
				["id" => "pma", "label" => "phpMyAdmin", "href" => $pma, "icon" => "table-2", "external" => true, "show" => $can["db"] && str_contains($dbSystem, "mysql"), "nav" => false],
				["id" => "pga", "label" => "phpPgAdmin", "href" => $pga, "icon" => "table-2", "external" => true, "show" => $can["db"] && str_contains($dbSystem, "pgsql"), "nav" => false],
			],
		],
		[
			"id" => "files",
			"label" => $t("Files"),
			"icon" => "folder",
			"items" => [
				["id" => "fm", "label" => $t("File Manager"), "href" => "/fm/", "icon" => "folder-open", "show" => $can["file_manager"]],
				["id" => "backups", "label" => $t("Backups"), "href" => "/list/backup/", "icon" => "archive", "show" => $can["backup"]],
				["id" => "backup-exclusions", "label" => $t("Backup Exclusions"), "href" => "/list/backup/exclusions/", "icon" => "folder-minus", "show" => $can["backup"], "nav" => false],
				["id" => "backup-incremental", "label" => $t("Incremental Backups"), "href" => "/list/backup/incremental/", "icon" => "archive-restore", "show" => $can["backup"] && $can["backup_incremental"], "nav" => false],
			],
		],
		[
			"id" => "metrics",
			"label" => $t("Metrics"),
			"icon" => "chart-column",
			"items" => [
				["id" => "stats", "label" => $t("Statistics"), "href" => "/list/stats/", "icon" => "chart-line", "show" => true],
				["id" => "logs", "label" => $t("Activity Log"), "href" => "/list/log/", "icon" => "scroll-text", "show" => $can["logs"]],
				["id" => "auth-log", "label" => $t("Login History"), "href" => "/list/log/auth/", "icon" => "history", "show" => $can["logs"], "nav" => false],
				["id" => "rrd", "label" => $t("Resource Graphs"), "href" => "/list/rrd/", "icon" => "chart-area", "show" => $can["server"], "nav" => false],
			],
		],
		[
			"id" => "security",
			"label" => $t("Security"),
			"icon" => "shield-check",
			"items" => [
				["id" => "ssh-keys", "label" => $t("SSH Keys"), "href" => "/list/key/", "icon" => "key-round", "show" => !$ctx["impersonating_root"]],
				["id" => "access-keys", "label" => $t("Access Keys"), "href" => "/list/access-key/", "icon" => "key", "show" => $can["access_keys"]],
				["id" => "twofa", "label" => $t("Two-Factor Authentication"), "href" => $profile . "#v_twofa", "icon" => "fingerprint", "show" => $can["profile"], "nav" => false],
				["id" => "firewall", "label" => $t("Firewall"), "href" => "/list/firewall/", "icon" => "brick-wall-fire", "show" => $can["server"] && $can["firewall"]],
				["id" => "banlist", "label" => $t("Banned IPs"), "href" => "/list/firewall/banlist/", "icon" => "shield-ban", "show" => $can["server"] && $can["firewall"] && $can["firewall_extended"], "nav" => false],
				["id" => "ipset", "label" => $t("IP Lists"), "href" => "/list/firewall/ipset/", "icon" => "list-checks", "show" => $can["server"] && $can["firewall"], "nav" => false],
			],
		],
		[
			"id" => "advanced",
			"label" => $t("Advanced"),
			"icon" => "settings-2",
			"items" => [
				["id" => "cron", "label" => $t("Cron Jobs"), "href" => "/list/cron/", "icon" => "calendar-clock", "show" => $can["cron"]],
				["id" => "terminal", "label" => $t("Web Terminal"), "href" => "/list/terminal/", "icon" => "square-terminal", "show" => $can["terminal"]],
				["id" => "notifications", "label" => $t("Notifications"), "href" => "/list/notifications/", "icon" => "bell", "show" => $can["notifications"], "nav" => false],
			],
		],
		[
			"id" => "admin",
			"label" => $t("Administration"),
			"icon" => "users",
			"items" => [
				["id" => "users", "label" => $t("Users"), "href" => "/list/user/", "icon" => "users", "show" => $can["users"]],
				["id" => "user-add", "label" => $t("Add User"), "href" => "/add/user/", "icon" => "user-plus", "show" => $can["users"], "nav" => false],
				["id" => "packages", "label" => $t("Packages"), "href" => "/list/package/", "icon" => "package", "show" => $can["users"]],
				["id" => "ips", "label" => $t("IP Addresses"), "href" => "/list/ip/", "icon" => "ethernet-port", "show" => $can["users"] && $can["server"]],
			],
		],
		[
			"id" => "server",
			"label" => $t("Server"),
			"icon" => "server",
			"items" => [
				["id" => "services", "label" => $t("Services"), "href" => "/list/server/", "icon" => "server-cog", "show" => $can["server"]],
				["id" => "server-settings", "label" => $t("Server Settings"), "href" => "/edit/server/", "icon" => "sliders-horizontal", "show" => $can["server"]],
				["id" => "updates", "label" => $t("Updates"), "href" => "/list/updates/", "icon" => "refresh-cw", "show" => $can["server"]],
				["id" => "server-info", "label" => $t("Server Information"), "href" => "/list/server/?cpu", "icon" => "cpu", "show" => $can["server"], "nav" => false],
				["id" => "rrd-nav", "label" => $t("Resource Graphs"), "href" => "/list/rrd/", "icon" => "chart-area", "show" => $can["server"], "tool" => false],
				["id" => "system-log", "label" => $t("System Log"), "href" => $systemLog, "icon" => "file-text", "show" => $can["server"], "nav" => false],
			],
		],
		[
			"id" => "preferences",
			"label" => $t("Preferences"),
			"icon" => "user-cog",
			"items" => [
				["id" => "profile", "label" => $t("Account Settings"), "href" => $profile, "icon" => "user-pen", "show" => $can["profile"]],
				["id" => "password", "label" => $t("Password & 2FA"), "href" => $profile . "#v_password", "icon" => "lock-keyhole", "show" => $can["profile"], "nav" => false],
				["id" => "language", "label" => $t("Language"), "href" => $profile . "#v_language", "icon" => "languages", "show" => $can["profile"], "nav" => false],
				["id" => "contact", "label" => $t("Contact Details"), "href" => $profile . "#v_email", "icon" => "contact-round", "show" => $can["profile"], "nav" => false],
			],
		],
	];

	// Section order: Hestia's administrator areas come first (Hestia lands
	// administrators on user management), then the hosting sections.
	$order = ["admin", "server", "email", "files", "databases", "domains", "metrics", "security", "advanced", "preferences"];
	usort($groups, fn($a, $b) => array_search($a["id"], $order, true) <=> array_search($b["id"], $order, true));

	// Drop hidden items and empty groups.
	$out = [];
	foreach ($groups as $group) {
		$group["items"] = array_values(array_filter($group["items"], fn($i) => !empty($i["show"])));
		if ($group["items"]) {
			$out[] = $group;
		}
	}
	return $out;
}

/**
 * Route metadata used for breadcrumbs, page titles and the active nav item.
 * Keys are path prefixes; longest match wins. Values: [item id, title, description].
 * Titles may vary with query parameters (see ha_page_meta()).
 */
function ha_routes(): array {
	$t = "ha_t";
	return [
		"/atlas/" => ["home", $t("Tools"), ""],
		"/list/web/" => ["web", $t("Web Domains"), $t("Create and manage websites, domain aliases, SSL certificates and web server settings.")],
		"/add/web/" => ["web", $t("Add Web Domain"), $t("Create a new website on this account.")],
		"/edit/web/" => ["web", $t("Edit Web Domain"), ""],
		"/add/webapp/" => ["web", $t("Quick Install App"), $t("Install a web application on this domain.")],
		"/list/web-log/" => ["web", $t("Web Logs"), ""],
		"/generate/ssl/" => ["web", $t("Generate SSL Certificate"), ""],
		"/list/dns/" => ["dns", $t("DNS Zones"), $t("Manage DNS zones and records for the domains on this account.")],
		"/add/dns/" => ["dns", $t("Add DNS Domain"), $t("Create a new DNS zone.")],
		"/edit/dns/" => ["dns", $t("Edit DNS Domain"), ""],
		"/list/mail/" => ["mail", $t("Mail Domains"), $t("Manage mail domains, email accounts, forwarding, DKIM and anti-spam settings.")],
		"/add/mail/" => ["mail", $t("Add Mail Domain"), $t("Enable email for a domain on this account.")],
		"/edit/mail/" => ["mail", $t("Edit Mail Domain"), ""],
		"/list/db/" => ["db", $t("Databases"), $t("Create and manage databases and database users.")],
		"/add/db/" => ["db", $t("Add Database"), $t("Create a database and its user.")],
		"/edit/db/" => ["db", $t("Edit Database"), ""],
		"/list/cron/" => ["cron", $t("Cron Jobs"), $t("Schedule commands to run automatically at specific times.")],
		"/add/cron/" => ["cron", $t("Add Cron Job"), $t("Schedule a new command.")],
		"/edit/cron/" => ["cron", $t("Edit Cron Job"), ""],
		"/list/backup/" => ["backups", $t("Backups"), $t("Create, download and restore backups of this account.")],
		"/list/backup/exclusions/" => ["backups", $t("Backup Exclusions"), $t("Choose what is excluded from backups.")],
		"/edit/backup/exclusions/" => ["backups", $t("Edit Backup Exclusions"), ""],
		"/list/backup/incremental/" => ["backups", $t("Incremental Backups"), $t("Browse and restore incremental snapshots.")],
		"/list/user/" => ["users", $t("Users"), $t("Manage hosting accounts, their packages and access.")],
		"/add/user/" => ["users", $t("Add User"), $t("Create a new hosting account.")],
		"/edit/user/" => ["users", $t("Edit User"), ""],
		"/list/package/" => ["packages", $t("Packages"), $t("Define resource limits and templates assigned to hosting accounts.")],
		"/add/package/" => ["packages", $t("Add Package"), ""],
		"/edit/package/" => ["packages", $t("Edit Package"), ""],
		"/copy/package/" => ["packages", $t("Copy Package"), ""],
		"/list/ip/" => ["ips", $t("IP Addresses"), $t("Manage the IP addresses available on this server.")],
		"/add/ip/" => ["ips", $t("Add IP Address"), ""],
		"/edit/ip/" => ["ips", $t("Edit IP Address"), ""],
		"/list/firewall/" => ["firewall", $t("Firewall"), $t("Control which connections are allowed to reach this server.")],
		"/add/firewall/" => ["firewall", $t("Add Firewall Rule"), ""],
		"/edit/firewall/" => ["firewall", $t("Edit Firewall Rule"), ""],
		"/list/firewall/banlist/" => ["banlist", $t("Banned IP Addresses"), $t("Addresses blocked by Fail2Ban or added manually.")],
		"/add/firewall/banlist/" => ["banlist", $t("Ban IP Address"), ""],
		"/list/firewall/ipset/" => ["ipset", $t("IP Lists"), $t("Reusable IP address lists for firewall rules.")],
		"/add/firewall/ipset/" => ["ipset", $t("Add IP List"), ""],
		"/list/server/" => ["services", $t("Services"), $t("Monitor, restart and configure the services running on this server.")],
		"/edit/server/" => ["server-settings", $t("Server Settings"), $t("Configure system-wide settings for this server.")],
		"/list/rrd/" => ["rrd", $t("Resource Graphs"), $t("Historical CPU, memory, network and service usage.")],
		"/list/updates/" => ["updates", $t("Updates"), $t("Installed Hestia packages and automatic update settings.")],
		"/list/log/" => ["logs", $t("Activity Log"), $t("Recent actions performed on this account.")],
		"/list/log/auth/" => ["auth-log", $t("Login History"), $t("Recent sign-in attempts to this account.")],
		"/list/stats/" => ["stats", $t("Statistics"), $t("Monthly resource usage.")],
		"/list/key/" => ["ssh-keys", $t("SSH Keys"), $t("Public keys allowed to sign in over SSH and SFTP.")],
		"/add/key/" => ["ssh-keys", $t("Add SSH Key"), ""],
		"/list/access-key/" => ["access-keys", $t("Access Keys"), $t("API access keys for automation and integrations.")],
		"/add/access-key/" => ["access-keys", $t("Add Access Key"), ""],
		"/list/terminal/" => ["terminal", $t("Web Terminal"), ""],
		"/list/notifications/" => ["notifications", $t("Notifications"), ""],
		"/search/" => ["search", $t("Search Results"), ""],
	];
}

/** Resolve page metadata (active item, title, subtitle, breadcrumbs) for the current request. */
function ha_page_meta(array $ctx, array $groups, string $TAB = ""): array {
	$path = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/";
	$q = $_GET;
	$routes = ha_routes();
	$match = null;
	foreach ($routes as $prefix => $meta) {
		if (str_starts_with($path, $prefix) && ($match === null || strlen($prefix) > strlen($match))) {
			$match = $prefix;
		}
	}
	[$id, $title, $desc] = $match ? $routes[$match] : ["", "", ""];
	$sub = "";

	// Query-dependent views of the same controller.
	$domain = isset($q["domain"]) ? (string) $q["domain"] : "";
	switch ($match) {
		case "/list/dns/":
			if ($domain !== "") {
				[$title, $sub, $desc] = [ha_t("DNS Records"), $domain, ""];
			}
			break;
		case "/add/dns/":
			if ($domain !== "") {
				[$title, $sub, $desc] = [ha_t("Add DNS Record"), $domain, ""];
			}
			break;
		case "/edit/dns/":
			if (isset($q["record_id"])) {
				$title = ha_t("Edit DNS Record");
			}
			$sub = $domain;
			break;
		case "/list/mail/":
			if ($domain !== "" && isset($q["dns"])) {
				[$title, $sub, $desc, $id] = [ha_t("DNS Records"), $domain, "", "mail"];
			} elseif ($domain !== "") {
				[$title, $sub, $desc, $id] = [ha_t("Email Accounts"), $domain, ha_t("Mailboxes, quotas, forwarding and autoreplies for this domain."), "mail"];
			}
			break;
		case "/add/mail/":
			if ($domain !== "") {
				[$title, $sub, $desc] = [ha_t("Add Email Account"), $domain, ""];
			}
			break;
		case "/edit/mail/":
			if (isset($q["account"])) {
				[$title, $sub] = [ha_t("Edit Email Account"), $q["account"] . "@" . $domain];
			} else {
				$sub = $domain;
			}
			break;
		case "/edit/web/":
		case "/list/web-log/":
		case "/add/webapp/":
		case "/generate/ssl/":
			$sub = $domain;
			break;
		case "/edit/db/":
			$sub = (string) ($q["database"] ?? "");
			break;
		case "/edit/user/":
			$who = (string) ($q["user"] ?? $ctx["user"]);
			if ($who === $ctx["user"]) {
				[$id, $title, $desc] = ["profile", ha_t("Account Settings"), ha_t("Contact details, password, language and preferences.")];
			} else {
				$sub = $who;
			}
			break;
		case "/edit/package/":
			$sub = (string) ($q["package"] ?? "");
			break;
		case "/list/backup/":
			if (isset($q["backup"])) {
				[$title, $sub, $desc] = [ha_t("Restore Backup"), (string) $q["backup"], ""];
			}
			break;
		case "/list/server/":
			foreach (["cpu", "mem", "disk", "net", "web", "dns", "mail", "db"] as $k) {
				if (isset($q[$k])) {
					[$id, $title, $desc] = ["server-info", ha_t("Server Information"), ""];
				}
			}
			break;
		case "/edit/server/":
			$svc = trim(substr($path, strlen("/edit/server/")), "/");
			if ($svc !== "") {
				[$title, $sub, $desc] = [ha_t("Configure Service"), $svc, ""];
			}
			break;
		case "/list/log/":
			if (($q["user"] ?? "") === "system") {
				[$id, $title, $desc] = ["system-log", ha_t("System Log"), ha_t("Actions performed by the system and administrators.")];
			} elseif (!empty($q["user"])) {
				$sub = (string) $q["user"];
			}
			break;
		case "/search/":
			$sub = (string) ($q["q"] ?? "");
			break;
	}

	// Locate the item in the registry for breadcrumbs.
	$crumbs = [["label" => ha_t("Home"), "href" => "/atlas/"]];
	$group = null;
	$item = null;
	foreach ($groups as $g) {
		foreach ($g["items"] as $i) {
			if ($i["id"] === $id || ($id === "rrd" && $i["id"] === "rrd-nav")) {
				$group = $g;
				$item = $i;
				break 2;
			}
		}
	}
	if ($id !== "home") {
		if ($group) {
			$crumbs[] = ["label" => $group["label"], "href" => "/atlas/#" . $group["id"]];
		}
		if ($item && $item["label"] !== $title) {
			$crumbs[] = ["label" => $item["label"], "href" => $item["href"]];
		}
		if ($title !== "") {
			$crumbs[] = ["label" => $title, "href" => ""];
		}
	}

	return [
		"id" => $id,
		"group" => $group["id"] ?? "",
		"title" => $title,
		"subtitle" => $sub,
		"desc" => $desc,
		"crumbs" => $crumbs,
		"path" => $path,
	];
}

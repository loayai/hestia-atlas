<?php
/**
 * Data collection for the Atlas "Tools" page.
 * Only read-only v-list-* commands are executed, exactly as Hestia's own pages do.
 */

require_once __DIR__ . "/bootstrap.php";

use function Hestiacp\quoteshellarg\quoteshellarg;

/** Run a read-only Hestia list command and decode its JSON output. */
function ha_list(string $cmd, string ...$args): array {
	if (!preg_match('/^v-list-[a-z0-9-]+$/', $cmd)) {
		return [];
	}
	$line = HESTIA_CMD . $cmd;
	foreach ($args as $a) {
		$line .= " " . quoteshellarg($a);
	}
	$output = [];
	exec($line . " json 2>/dev/null", $output, $rc);
	if ($rc !== 0) {
		return [];
	}
	$data = json_decode(implode("", $output), true);
	return is_array($data) ? $data : [];
}

/** Relative template path from Hestia's templates/pages/ to one of our views. */
function ha_view_path(string $view): string {
	$pages = realpath($_SERVER["DOCUMENT_ROOT"] . "/templates/pages");
	$depth = count(array_filter(explode("/", (string) $pages), "strlen"));
	return str_repeat("../", $depth) . ltrim(HA_LIB, "/") . "/views/" . $view;
}

/** A statistics row: label, used, limit, display strings, percentage. */
function ha_stat(string $label, $used, $limit, bool $size = false): array {
	$used = $used === "" || $used === null ? "0" : (string) $used;
	$limit = $limit === "" || $limit === null ? "0" : (string) $limit;
	return [
		"label" => $label,
		"used" => $size ? ha_size($used) : $used,
		"limit" => $limit === "unlimited" ? "∞" : ($size ? ha_size($limit) : $limit),
		"pct" => ha_percent($used, $limit),
	];
}

/** Multiply a per-domain limit by the domain limit, as Hestia's panel does. */
function ha_scaled_limit($per, $domains): string {
	if ($per === "unlimited" || $domains === "unlimited") {
		return "unlimited";
	}
	return (string) ((int) $per * (int) $domains);
}

function ha_dashboard_data(): array {
	$ctx = ha_context();
	$u = $ctx["user"];
	$d = $ctx["data"];
	$can = $ctx["can"];

	// Primary domain: first web domain on the account (Hestia keeps creation order).
	$primary = null;
	if ($can["web"] && (int) ($d["U_WEB_DOMAINS"] ?? 0) > 0) {
		$web = ha_list("v-list-web-domains", $u);
		foreach ($web as $name => $w) {
			$primary = ["name" => $name, "ssl" => ($w["SSL"] ?? "no") === "yes", "ip" => $w["IP"] ?? "", "suspended" => ($w["SUSPENDED"] ?? "no") === "yes"];
			break;
		}
	}

	// Last successful login other than the current one, if known.
	$lastLogin = null;
	$auth = ha_list("v-list-user-auth-log", $u);
	$ok = array_values(array_filter($auth, fn($r) => ($r["STATUS"] ?? "") === "success" && ($r["ACTION"] ?? "") === "login"));
	usort($ok, fn($a, $b) => strcmp(($b["DATE"] ?? "") . ($b["TIME"] ?? ""), ($a["DATE"] ?? "") . ($a["TIME"] ?? "")));
	if ($ok) {
		$row = $ok[1] ?? $ok[0];
		$lastLogin = ["ip" => $row["IP"] ?? "", "when" => trim(($row["DATE"] ?? "") . " " . ($row["TIME"] ?? ""))];
	}

	$stats = [ha_stat(ha_t("Disk Usage"), $d["U_DISK"] ?? 0, $d["DISK_QUOTA"] ?? "unlimited", true)];
	if ($can["db"]) {
		$stats[] = ha_stat(ha_t("Database Disk Usage"), $d["U_DISK_DB"] ?? 0, $d["DISK_QUOTA"] ?? "unlimited", true);
	}
	if ($can["mail"]) {
		$stats[] = ha_stat(ha_t("Mail Disk Usage"), $d["U_DISK_MAIL"] ?? 0, $d["DISK_QUOTA"] ?? "unlimited", true);
	}
	$stats[] = ha_stat(ha_t("Bandwidth"), $d["U_BANDWIDTH"] ?? 0, $d["BANDWIDTH"] ?? "unlimited", true);
	if ($can["web"]) {
		$stats[] = ha_stat(ha_t("Web Domains"), $d["U_WEB_DOMAINS"] ?? 0, $d["WEB_DOMAINS"] ?? 0);
		$stats[] = ha_stat(ha_t("Aliases"), $d["U_WEB_ALIASES"] ?? 0, ha_scaled_limit($d["WEB_ALIASES"] ?? 0, $d["WEB_DOMAINS"] ?? 0));
	}
	if ($can["dns"]) {
		$stats[] = ha_stat(ha_t("DNS Zones"), $d["U_DNS_DOMAINS"] ?? 0, $d["DNS_DOMAINS"] ?? 0);
		$stats[] = ha_stat(ha_t("DNS Records"), $d["U_DNS_RECORDS"] ?? 0, ha_scaled_limit($d["DNS_RECORDS"] ?? 0, $d["DNS_DOMAINS"] ?? 0));
	}
	if ($can["mail"]) {
		$stats[] = ha_stat(ha_t("Mail Domains"), $d["U_MAIL_DOMAINS"] ?? 0, $d["MAIL_DOMAINS"] ?? 0);
		$stats[] = ha_stat(ha_t("Email Accounts"), $d["U_MAIL_ACCOUNTS"] ?? 0, ha_scaled_limit($d["MAIL_ACCOUNTS"] ?? 0, $d["MAIL_DOMAINS"] ?? 0));
	}
	if ($can["db"]) {
		$stats[] = ha_stat(ha_t("Databases"), $d["U_DATABASES"] ?? 0, $d["DATABASES"] ?? 0);
	}
	if ($can["cron"]) {
		$stats[] = ha_stat(ha_t("Cron Jobs"), $d["U_CRON_JOBS"] ?? 0, $d["CRON_JOBS"] ?? 0);
	}
	if ($can["backup"]) {
		$stats[] = ha_stat(ha_t("Backups"), $d["U_BACKUPS"] ?? 0, $d["BACKUPS"] ?? 0);
	}

	$server = null;
	if ($can["server"]) {
		$info = ha_list("v-list-sys-info")["sysinfo"] ?? [];
		$services = ha_list("v-list-sys-services");
		$running = 0;
		$list = [];
		foreach ($services as $name => $svc) {
			$up = ($svc["STATE"] ?? "") === "running";
			$running += $up ? 1 : 0;
			$list[] = ["name" => $name, "system" => $svc["SYSTEM"] ?? "", "up" => $up];
		}
		$server = [
			"hostname" => $info["HOSTNAME"] ?? (function_exists("get_hostname") ? get_hostname() : ""),
			"os" => trim(($info["OS"] ?? "") . " " . ($info["VERSION"] ?? "")),
			"arch" => $info["ARCH"] ?? "",
			"hestia" => $info["HESTIA"] ?? ($_SESSION["VERSION"] ?? ""),
			"uptime" => (int) ($info["UPTIME"] ?? 0),
			"load" => $info["LOADAVERAGE"] ?? "",
			"services" => $list,
			"running" => $running,
		];
	}

	$admin = null;
	if ($can["users"]) {
		$admin = [
			"users" => (int) ($d["U_USERS"] ?? 0),
			"suspended" => (int) ($d["SUSPENDED_USERS"] ?? 0),
		];
	}

	return [
		"ctx" => $ctx,
		"groups" => ha_nav_groups($ctx),
		"primary" => $primary,
		"last_login" => $lastLogin,
		"stats" => $stats,
		"server" => $server,
		"admin" => $admin,
	];
}

/** Uptime in minutes → "3 days, 4 hours". */
function ha_uptime(int $minutes): string {
	$days = intdiv($minutes, 1440);
	$hours = intdiv($minutes % 1440, 60);
	$mins = $minutes % 60;
	$parts = [];
	if ($days) {
		$parts[] = sprintf(ngettext("%d day", "%d days", $days), $days);
	}
	if ($hours) {
		$parts[] = sprintf(ngettext("%d hour", "%d hours", $hours), $hours);
	}
	if (!$days && $mins) {
		$parts[] = sprintf(ngettext("%d minute", "%d minutes", $mins), $mins);
	}
	return $parts ? implode(", ", $parts) : ha_t("just now");
}

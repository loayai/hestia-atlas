<?php
/**
 * Hestia Atlas theme — <head> hook.
 *
 * Included by Hestia's templates/includes/js.php via web/js/custom_scripts/atlas.php
 * on every page. When the "atlas" theme is selected it emits:
 *   1. a tiny boot script that mounts the shell while the page is parsed (no flash),
 *   2. <template> elements with the server-rendered sidebar, header and page heading,
 *   3. the deferred theme script.
 * When another theme is selected it outputs nothing.
 */

require_once __DIR__ . "/bootstrap.php";

if (!ha_is_active()) {
	return;
}

// Login / password reset pages: only branding helpers, no application shell.
if (empty($_SESSION["user"])) {
	echo '<script>document.documentElement.classList.add("ha","ha-guest");' . HA_MODE_BOOT . "</script>";
	echo '<script defer src="' . ha_e(ha_asset("atlas.js")) . '"></script>';
	return;
}

// After signing in Hestia lands on its own list pages; send users to the Tools page instead.
$ha_path = (string) parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH);
$ha_ref = (string) parse_url($_SERVER["HTTP_REFERER"] ?? "", PHP_URL_PATH);
$ha_landing = ["/list/user/", "/list/web/", "/list/dns/", "/list/mail/", "/list/db/", "/list/cron/", "/list/backup/"];
if (ha_config("LANDING", "tools") === "tools" && str_starts_with($ha_ref, "/login") && in_array($ha_path, $ha_landing, true) && ($_SERVER["QUERY_STRING"] ?? "") === "") {
	echo '<script>location.replace("/atlas/");</script>';
}

$ha_ctx = ha_context();
$ha_groups = ha_nav_groups($ha_ctx);
$ha_meta = ha_page_meta($ha_ctx, $ha_groups, $TAB ?? "");
$ha_data = $ha_ctx["data"];

/** Render the sidebar navigation. */
function ha_render_sidebar(array $ctx, array $groups, array $meta): string {
	ob_start(); ?>
	<aside class="ha-sidebar" id="ha-sidebar" aria-label="<?= ha_e(ha_t("Main navigation")) ?>">
		<div class="ha-sidebar-brand">
			<a href="/atlas/" class="ha-logo" title="<?= ha_e($ctx["app_name"]) ?>">
				<img class="ha-logo-full" src="/images/logo-header.svg" alt="<?= ha_e($ctx["app_name"]) ?>" width="76" height="40">
				<img class="ha-logo-mark" src="/images/logo.svg" alt="" width="26" height="32">
			</a>
			<button type="button" class="ha-sidebar-close" data-ha-action="close-nav" aria-label="<?= ha_e(ha_t("Close menu")) ?>">
				<?= ha_icon("x") ?>
			</button>
		</div>
		<nav class="ha-nav">
			<ul class="ha-nav-list">
				<li class="ha-nav-item<?= $meta["id"] === "home" ? " is-active" : "" ?>">
					<a href="/atlas/" class="ha-nav-link" data-ha-tip="<?= ha_e(ha_t("Tools")) ?>"<?= $meta["id"] === "home" ? ' aria-current="page"' : "" ?>>
						<?= ha_icon("wrench", "ha-nav-icon") ?><span class="ha-nav-label"><?= ha_e(ha_t("Tools")) ?></span>
					</a>
				</li>
				<?php foreach ($groups as $group) {
					$items = array_values(array_filter($group["items"], fn($i) => ($i["nav"] ?? true) !== false));
					if (!$items) {
						continue;
					}
					$active = $meta["group"] === $group["id"];
					$single = count($items) === 1 && $items[0]["label"] === $group["label"];
					if ($single) {
						$i = $items[0]; ?>
						<li class="ha-nav-item<?= $active ? " is-active" : "" ?>">
							<a href="<?= ha_e($i["href"]) ?>" class="ha-nav-link" data-ha-tip="<?= ha_e($i["label"]) ?>"<?= $active ? ' aria-current="page"' : "" ?>>
								<?= ha_icon($group["icon"], "ha-nav-icon") ?><span class="ha-nav-label"><?= ha_e($i["label"]) ?></span>
							</a>
						</li>
						<?php continue;
					} ?>
					<li class="ha-nav-item ha-nav-group<?= $active ? " is-active is-open" : "" ?>" data-ha-group="<?= ha_e($group["id"]) ?>">
						<button type="button" class="ha-nav-link ha-nav-toggle" aria-expanded="<?= $active ? "true" : "false" ?>" data-ha-tip="<?= ha_e($group["label"]) ?>">
							<?= ha_icon($group["icon"], "ha-nav-icon") ?><span class="ha-nav-label"><?= ha_e($group["label"]) ?></span><?= ha_icon("chevron-down", "ha-nav-caret") ?>
						</button>
						<ul class="ha-nav-sub">
							<li class="ha-nav-sub-title"><?= ha_e($group["label"]) ?></li>
							<?php foreach ($items as $i) {
								$on = $meta["id"] === $i["id"] || ($meta["id"] === "rrd" && $i["id"] === "rrd-nav"); ?>
								<li>
									<a href="<?= ha_e($i["href"]) ?>" class="ha-nav-sublink<?= $on ? " is-active" : "" ?>"<?= $on ? ' aria-current="page"' : "" ?><?= !empty($i["external"]) ? ' target="_blank" rel="noopener"' : "" ?>>
										<?= ha_e($i["label"]) ?>
									</a>
								</li>
							<?php } ?>
						</ul>
					</li>
				<?php } ?>
			</ul>
		</nav>
		<button type="button" class="ha-sidebar-collapse" data-ha-action="collapse-nav">
			<?= ha_icon("panel-left-close", "ha-nav-icon ha-collapse-icon") ?><span class="ha-nav-label"><?= ha_e(ha_t("Collapse")) ?></span>
		</button>
	</aside>
	<div class="ha-backdrop" data-ha-action="close-nav" hidden></div>
	<?php return ob_get_clean();
}

/** Render the top header (search, notifications, user menu). */
function ha_render_header(array $ctx, array $data): string {
	$user = $ctx["user"];
	$name = $data["NAME"] ?? $user;
	$logout = "/logout/?token=" . rawurlencode($ctx["token"]);
	$hasUnread = ($data["NOTIFICATIONS"] ?? "") === "yes";
	$role = $ctx["is_admin"] && !$ctx["looking"] ? ha_t("Administrator") : ha_t("User");
	ob_start(); ?>
	<header class="ha-header">
		<button type="button" class="ha-icon-btn ha-menu-toggle" data-ha-action="open-nav" aria-controls="ha-sidebar" aria-expanded="false" aria-label="<?= ha_e(ha_t("Open menu")) ?>">
			<?= ha_icon("menu") ?>
		</button>
		<a href="/atlas/" class="ha-header-brand" title="<?= ha_e($ctx["app_name"]) ?>">
			<img src="/images/logo.svg" alt="<?= ha_e($ctx["app_name"]) ?>" width="26" height="32"><span><?= ha_e($ctx["app_name"]) ?></span>
		</a>
		<?php if ($ctx["looking"]) { ?>
			<div class="ha-impersonation">
				<?= ha_icon("eye") ?>
				<span><?= ha_e(ha_t("Viewing as")) ?> <strong><?= ha_e($user) ?></strong></span>
				<a href="<?= ha_e($logout) ?>"><?= ha_e(ha_t("Exit")) ?></a>
			</div>
		<?php } ?>
		<div class="ha-header-spacer"></div>
		<form class="ha-search" role="search" action="/search/" method="get" data-ha-search>
			<input type="hidden" name="token" value="<?= ha_e($ctx["token"]) ?>">
			<label class="u-hidden-visually" for="ha-search-input"><?= ha_e(ha_t("Find a tool or page")) ?></label>
			<input id="ha-search-input" class="ha-search-input" type="search" name="q" autocomplete="off" spellcheck="false"
				placeholder="<?= ha_e(ha_t("Find a tool or page")) ?>" role="combobox" aria-expanded="false" aria-controls="ha-search-results" aria-autocomplete="list">
			<kbd class="ha-search-kbd" aria-hidden="true">/</kbd>
			<button type="submit" class="ha-search-submit" aria-label="<?= ha_e(ha_t("Search")) ?>"><?= ha_icon("search") ?></button>
			<div class="ha-search-results" id="ha-search-results" role="listbox" hidden></div>
		</form>
		<button type="button" class="ha-icon-btn ha-search-toggle" data-ha-action="open-search" aria-label="<?= ha_e(ha_t("Search")) ?>">
			<?= ha_icon("search") ?>
		</button>
		<button type="button" class="ha-icon-btn ha-icon-btn-circle ha-mode-toggle" data-ha-action="toggle-mode"
			data-label-dark="<?= ha_e(ha_t("Switch to dark mode")) ?>" data-label-light="<?= ha_e(ha_t("Switch to light mode")) ?>"
			aria-label="<?= ha_e(ha_t("Switch to dark mode")) ?>" title="<?= ha_e(ha_t("Switch to dark mode")) ?>">
			<?= ha_icon("moon", "ha-mode-icon-dark") ?><?= ha_icon("sun", "ha-mode-icon-light") ?>
		</button>
		<?php if ($ctx["can"]["notifications"]) { ?>
			<div class="ha-dropdown ha-notifications" x-data="notifications">
				<button type="button" class="ha-icon-btn ha-icon-btn-circle" x-on:click="toggle()" x-bind:aria-expanded="open" aria-haspopup="true" title="<?= ha_e(ha_t("Notifications")) ?>">
					<?= ha_icon("bell") ?>
					<span class="ha-dot" x-show="(!initialized && <?= $hasUnread ? "true" : "false" ?>) || notifications.length != 0"<?= $hasUnread ? "" : " x-cloak" ?>></span>
					<span class="u-hidden-visually"><?= ha_e(ha_t("Notifications")) ?></span>
				</button>
				<div class="ha-dropdown-panel ha-notifications-panel" x-cloak x-show="open" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
					<div class="ha-dropdown-head"><?= ha_e(ha_t("Notifications")) ?></div>
					<template x-if="!initialized">
						<div class="ha-empty-mini"><?= ha_icon("loader-circle", "ha-spin") ?><p><?= ha_e(ha_t("Loading...")) ?></p></div>
					</template>
					<template x-if="initialized && notifications.length == 0">
						<div class="ha-empty-mini"><?= ha_icon("bell-off") ?><p><?= ha_e(ha_t("No notifications")) ?></p></div>
					</template>
					<template x-if="initialized && notifications.length > 0">
						<ul class="ha-notification-list">
							<template x-for="notification in notifications" :key="notification.ID">
								<li class="ha-notification" x-bind:class="notification.ACK && 'is-unseen'" x-bind:id="`notification-${notification.ID}`" x-data="{ open: true }" x-show="open" x-collapse>
									<div class="ha-notification-head">
										<p class="ha-notification-title" x-text="notification.TOPIC"></p>
										<button type="button" class="ha-notification-delete" x-on:click="open = false; setTimeout(() => remove(notification.ID), 300);" title="<?= ha_e(ha_t("Delete notification")) ?>">
											<?= ha_icon("x") ?><span class="u-hidden-visually"><?= ha_e(ha_t("Delete notification")) ?></span>
										</button>
									</div>
									<div class="ha-notification-body" x-html="notification.NOTICE"></div>
									<time class="ha-notification-time" :datetime="`${notification.TIMESTAMP_ISO}`" x-bind:title="`${notification.TIMESTAMP_TITLE}`" x-text="`${notification.TIMESTAMP_TEXT}`"></time>
								</li>
							</template>
						</ul>
					</template>
					<template x-if="initialized && notifications.length > 2">
						<button type="button" class="ha-dropdown-foot" x-on:click="removeAll()"><?= ha_icon("check-check") ?><?= ha_e(ha_t("Delete all notifications")) ?></button>
					</template>
				</div>
			</div>
		<?php } ?>
		<div class="ha-dropdown ha-usermenu" data-ha-dropdown>
			<button type="button" class="ha-icon-btn ha-icon-btn-circle ha-user-btn" aria-haspopup="true" aria-expanded="false" data-ha-dropdown-toggle title="<?= ha_e($user) ?>">
				<?= ha_icon("user-round") ?><span class="ha-user-btn-label"><?= ha_e($user) ?></span><?= ha_icon("chevron-down", "ha-user-btn-caret") ?>
			</button>
			<div class="ha-dropdown-panel ha-usermenu-panel" hidden>
				<div class="ha-usermenu-head">
					<span class="ha-avatar" aria-hidden="true"><?= ha_e(strtoupper(mb_substr($name !== "" ? $name : $user, 0, 1))) ?></span>
					<div>
						<div class="ha-usermenu-name"><?= ha_e($name) ?></div>
						<div class="ha-usermenu-meta"><?= ha_e($user) ?> · <?= ha_e($role) ?></div>
					</div>
				</div>
				<ul class="ha-menu">
					<?php if ($ctx["can"]["profile"]) {
						$p = ha_profile_url($ctx); ?>
						<li><a href="<?= ha_e($p) ?>"><?= ha_icon("user-pen") ?><?= ha_e(ha_t("Account Settings")) ?></a></li>
						<li><a href="<?= ha_e($p . "#v_password") ?>"><?= ha_icon("lock-keyhole") ?><?= ha_e(ha_t("Password & 2FA")) ?></a></li>
						<li><a href="<?= ha_e($p . "#v_language") ?>"><?= ha_icon("languages") ?><?= ha_e(ha_t("Language")) ?></a></li>
					<?php } ?>
					<?php if (!$ctx["impersonating_root"]) { ?>
						<li><a href="/list/key/"><?= ha_icon("key-round") ?><?= ha_e(ha_t("SSH Keys")) ?></a></li>
					<?php } ?>
					<li><a href="/list/stats/"><?= ha_icon("chart-line") ?><?= ha_e(ha_t("Statistics")) ?></a></li>
					<?php if ($ctx["can"]["docs"]) { ?>
						<li><a href="https://hestiacp.com/docs/" target="_blank" rel="noopener"><?= ha_icon("circle-help") ?><?= ha_e(ha_t("Help")) ?><?= ha_icon("external-link", "ha-menu-ext") ?></a></li>
					<?php } ?>
					<li><button type="button" data-ha-action="reset-ui"><?= ha_icon("rotate-ccw") ?><?= ha_e(ha_t("Reset layout preferences")) ?></button></li>
				</ul>
				<a class="ha-usermenu-logout" href="<?= ha_e($logout) ?>">
					<?= ha_icon("log-out") ?><?= ha_e($ctx["looking"] ? sprintf(ha_t("Log out (%s)"), $user) : ha_t("Log out")) ?>
				</a>
			</div>
		</div>
	</header>
	<?php return ob_get_clean();
}

/** Render breadcrumbs + page heading. */
function ha_render_pagehead(array $meta): string {
	if ($meta["id"] === "home" || $meta["title"] === "") {
		return "";
	}
	ob_start(); ?>
	<div class="ha-page-head">
		<nav class="ha-breadcrumb" aria-label="<?= ha_e(ha_t("Breadcrumb")) ?>">
			<ol>
				<?php foreach ($meta["crumbs"] as $n => $c) {
					$last = $n === count($meta["crumbs"]) - 1; ?>
					<li><?php if ($c["href"] !== "" && !$last) { ?><a href="<?= ha_e($c["href"]) ?>"><?= ha_e($c["label"]) ?></a><?php } else { ?><span aria-current="page"><?= ha_e($c["label"]) ?></span><?php } ?></li>
				<?php } ?>
			</ol>
		</nav>
		<h1 class="ha-page-title"><?= ha_e($meta["title"]) ?><?php if ($meta["subtitle"] !== "") { ?> <span class="ha-page-subtitle"><?= ha_e($meta["subtitle"]) ?></span><?php } ?></h1>
		<?php if ($meta["desc"] !== "") { ?>
			<p class="ha-page-desc"><?= ha_e($meta["desc"]) ?></p>
		<?php } ?>
	</div>
	<?php return ob_get_clean();
}

/** Tool index for the header search (visible tools only). */
function ha_search_index(array $groups): array {
	$out = [["l" => ha_t("Tools"), "g" => ha_t("Home"), "h" => "/atlas/", "i" => "wrench"]];
	foreach ($groups as $g) {
		foreach ($g["items"] as $i) {
			$out[] = ["l" => $i["label"], "g" => $g["label"], "h" => $i["href"], "i" => $i["icon"], "x" => !empty($i["external"])];
		}
	}
	return $out;
}

$ha_json = json_encode(
	[
		"search" => ha_search_index($ha_groups),
		"page" => ["id" => $ha_meta["id"], "group" => $ha_meta["group"]],
		"i18n" => [
			"searchFor" => ha_t("Search accounts and objects for"),
			"noTools" => ha_t("No matching tools"),
			"save" => ha_t("Save"),
			"cancel" => ha_t("Cancel"),
			"actions" => ha_t("Actions"),
			"expand" => ha_t("Expand"),
			"collapse" => ha_t("Collapse"),
		],
		"icons" => HA_ASSETS . "/icons.svg?v=" . HA_VERSION,
	],
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE,
);
?>
<script>
/* Hestia Atlas boot: mount the server-rendered shell while the document is parsed. */
(function () {
	var d = document, h = d.documentElement, done = { shell: 0, head: 0, bar: 0 };
	h.classList.add("ha", "ha-app", "ha-loading");
	<?= HA_MODE_BOOT ?>
	try { if (localStorage.getItem("ha-nav-collapsed") === "1") h.classList.add("ha-nav-collapsed"); } catch (e) {}
	function tpl(id) { var t = d.getElementById(id); return t ? t.content.cloneNode(true) : null; }
	function mount() {
		if (!done.shell && d.body) { var s = tpl("ha-shell"); if (s) { d.body.insertBefore(s, d.body.firstChild); done.shell = 1; } }
		if (!done.bar) { var b = d.querySelector(".app-header .top-bar"); if (b) { b.remove(); done.bar = 1; } }
		if (!done.head) { var m = d.querySelector("main.app-content"); if (m) { var p = tpl("ha-pagehead"); if (p) m.insertBefore(p, m.firstChild); m.id = "ha-main"; done.head = 1; } }
		if (done.shell && done.head && done.bar) obs.disconnect();
	}
	var obs = new MutationObserver(mount);
	obs.observe(h, { childList: true, subtree: true });
	d.addEventListener("DOMContentLoaded", function () { mount(); obs.disconnect(); });
	setTimeout(function () { h.classList.remove("ha-loading"); }, 2500);
})();
</script>
<template id="ha-shell"><a class="ha-skip" href="#ha-main"><?= ha_e(ha_t("Skip to content")) ?></a><?= ha_render_sidebar($ha_ctx, $ha_groups, $ha_meta) ?><?= ha_render_header($ha_ctx, $ha_data) ?></template>
<template id="ha-pagehead"><?= ha_render_pagehead($ha_meta) ?></template>
<script type="application/json" id="ha-data"><?= $ha_json ?></script>
<script defer src="<?= ha_e(ha_asset("atlas.js")) ?>"></script>

<?php
/** @var array $ha_dashboard */
$hd = $ha_dashboard;
$ctx = $hd["ctx"];
$data = $ctx["data"];
$primary = $hd["primary"];
?>
<div class="ha-container ha-tools-page">
	<div class="ha-tools-head">
		<h1 class="ha-page-title ha-page-title-lg"><?= ha_e(ha_t("Tools")) ?></h1>
		<?php show_alert_message($_SESSION); ?>
	</div>

	<div class="ha-tools-layout">
		<div class="ha-tools-main">
			<?php foreach ($hd["groups"] as $group) {
				$tools = array_values(array_filter($group["items"], fn($i) => ($i["tool"] ?? true) !== false));
				if (!$tools) {
					continue;
				} ?>
				<section class="ha-card ha-tool-section" id="<?= ha_e($group["id"]) ?>" data-ha-section="<?= ha_e($group["id"]) ?>">
					<h2 class="ha-tool-section-head">
						<button type="button" class="ha-tool-section-toggle" aria-expanded="true" aria-controls="ha-tools-<?= ha_e($group["id"]) ?>">
							<span class="ha-tool-section-icon"><?= ha_tool_icon($group["icon"]) ?></span>
							<span class="ha-tool-section-title"><?= ha_e($group["label"]) ?></span>
							<?= ha_icon("chevron-up", "ha-tool-section-caret") ?>
						</button>
					</h2>
					<ul class="ha-tool-grid" id="ha-tools-<?= ha_e($group["id"]) ?>">
						<?php foreach ($tools as $tool) { ?>
							<li>
								<a class="ha-tool" href="<?= ha_e($tool["href"]) ?>"<?= !empty($tool["external"]) ? ' target="_blank" rel="noopener"' : "" ?>>
									<?= ha_tool_icon($tool["icon"]) ?>
									<span class="ha-tool-label"><?= ha_e($tool["label"]) ?></span>
								</a>
							</li>
						<?php } ?>
					</ul>
				</section>
			<?php } ?>
		</div>

		<aside class="ha-tools-side">
			<section class="ha-card ha-info-card">
				<h2 class="ha-info-title"><?= ha_e(ha_t("Account Overview")) ?></h2>
				<dl class="ha-info-list">
					<div class="ha-info-row">
						<dt><?= ha_e(ha_t("Account")) ?></dt>
						<dd><?= ha_e($ctx["user"]) ?><?php if (!empty($data["NAME"])) { ?> <span class="ha-muted">(<?= ha_e($data["NAME"]) ?>)</span><?php } ?></dd>
					</div>
					<div class="ha-info-row">
						<dt><?= ha_e(ha_t("Package")) ?></dt>
						<dd><?= ha_e($data["PACKAGE"] ?? "") ?></dd>
					</div>
					<?php if ($primary) {
						$edit = "/edit/web/?" . http_build_query(["domain" => $primary["name"], "token" => $ctx["token"]]); ?>
						<div class="ha-info-row">
							<dt>
								<?= ha_e(ha_t("Main Domain")) ?>
								<?php if (!$primary["ssl"]) { ?>
									<span class="ha-info-flag" title="<?= ha_e(ha_t("SSL is not enabled for this domain")) ?>"><?= ha_icon("circle-alert") ?></span>
								<?php } ?>
							</dt>
							<dd>
								<a class="ha-domain-link" href="<?= ha_e(($primary["ssl"] ? "https://" : "http://") . $primary["name"] . "/") ?>" target="_blank" rel="noopener">
									<?= ha_icon($primary["ssl"] ? "lock" : "lock-open") ?><?= ha_e($primary["name"]) ?><?= ha_icon("external-link", "ha-ext") ?>
								</a>
								<a class="ha-btn ha-btn-soft ha-btn-block" href="<?= ha_e($edit) ?>">
									<?= ha_icon("shield-check") ?><?= ha_e(ha_t("Manage SSL")) ?>
								</a>
							</dd>
						</div>
						<?php if ($primary["ip"] !== "") { ?>
							<div class="ha-info-row">
								<dt><?= ha_e(ha_t("IP Address")) ?></dt>
								<dd><?= ha_e($primary["ip"]) ?></dd>
							</div>
						<?php } ?>
					<?php } ?>
					<div class="ha-info-row">
						<dt><?= ha_e(ha_t("Home Directory")) ?></dt>
						<dd class="ha-mono"><?= ha_e($data["HOME"] ?? "/home/" . $ctx["user"]) ?></dd>
					</div>
					<?php if ($hd["last_login"]) { ?>
						<div class="ha-info-row">
							<dt><?= ha_e(ha_t("Last Sign-in")) ?></dt>
							<dd><?= ha_e($hd["last_login"]["ip"]) ?> <span class="ha-muted ha-small"><?= ha_e($hd["last_login"]["when"]) ?></span></dd>
						</div>
					<?php } ?>
					<div class="ha-info-row">
						<dt><?= ha_e(ha_t("Theme")) ?></dt>
						<dd class="ha-info-inline">
							<span><?= ha_e(ha_selected_theme()) ?></span>
							<?php if ($ctx["can"]["profile"] && $ctx["can"]["theme_change"]) { ?>
								<a href="<?= ha_e(ha_profile_url($ctx) . "#v_user_theme") ?>"><?= ha_e(ha_t("Change")) ?></a>
							<?php } ?>
						</dd>
					</div>
				</dl>
				<?php if ($hd["server"]) { ?>
					<a class="ha-info-link" href="/list/server/?cpu"><?= ha_e(ha_t("Server Information")) ?><?= ha_icon("chevron-right") ?></a>
				<?php } ?>
			</section>

			<?php if ($hd["server"]) {
				$srv = $hd["server"];
				$total = count($srv["services"]);
				$down = $total - $srv["running"]; ?>
				<section class="ha-card ha-info-card">
					<h2 class="ha-info-title"><?= ha_e(ha_t("Server Status")) ?></h2>
					<dl class="ha-info-list">
						<div class="ha-info-row"><dt><?= ha_e(ha_t("Hostname")) ?></dt><dd class="ha-break"><?= ha_e($srv["hostname"]) ?></dd></div>
						<div class="ha-info-row ha-info-row-split">
							<div><dt><?= ha_e(ha_t("Operating System")) ?></dt><dd><?= ha_e($srv["os"]) ?></dd></div>
							<div><dt><?= ha_e(ha_t("Hestia Version")) ?></dt><dd><?= ha_e($srv["hestia"]) ?></dd></div>
						</div>
						<div class="ha-info-row ha-info-row-split">
							<div><dt><?= ha_e(ha_t("Load Average")) ?></dt><dd><?= ha_e($srv["load"]) ?></dd></div>
							<div><dt><?= ha_e(ha_t("Uptime")) ?></dt><dd><?= ha_e(ha_uptime($srv["uptime"])) ?></dd></div>
						</div>
						<div class="ha-info-row">
							<dt><?= ha_e(ha_t("Services")) ?></dt>
							<dd>
								<span class="ha-status <?= $down ? "ha-status-danger" : "ha-status-success" ?>">
									<?= ha_icon($down ? "circle-alert" : "circle-check") ?>
									<?= ha_e(sprintf(ha_t("%d of %d running"), $srv["running"], $total)) ?>
								</span>
								<ul class="ha-service-list">
									<?php foreach ($srv["services"] as $svc) { ?>
										<li class="<?= $svc["up"] ? "is-up" : "is-down" ?>" title="<?= ha_e($svc["system"]) ?>"><span class="ha-led" aria-hidden="true"></span><?= ha_e($svc["name"]) ?><span class="u-hidden-visually"> — <?= ha_e($svc["up"] ? ha_t("running") : ha_t("stopped")) ?></span></li>
									<?php } ?>
								</ul>
							</dd>
						</div>
					</dl>
					<a class="ha-info-link" href="/list/server/"><?= ha_e(ha_t("Manage Services")) ?><?= ha_icon("chevron-right") ?></a>
				</section>
			<?php } ?>

			<section class="ha-card ha-info-card">
				<h2 class="ha-info-title"><?= ha_e(ha_t("Resource Usage")) ?></h2>
				<ul class="ha-stat-list">
					<?php if ($hd["admin"]) { ?>
						<li class="ha-stat">
							<div class="ha-stat-label"><?= ha_e(ha_t("Users")) ?></div>
							<div class="ha-stat-value"><?= ha_e($hd["admin"]["users"]) ?><?php if ($hd["admin"]["suspended"]) { ?> <span class="ha-muted">(<?= ha_e(sprintf(ha_t("%d suspended"), $hd["admin"]["suspended"])) ?>)</span><?php } ?></div>
						</li>
					<?php } ?>
					<?php foreach ($hd["stats"] as $s) {
						$level = $s["pct"] === null ? "" : ($s["pct"] >= 90 ? " is-danger" : ($s["pct"] >= 75 ? " is-warning" : "")); ?>
						<li class="ha-stat<?= $level ?>">
							<div class="ha-stat-label"><?= ha_e($s["label"]) ?></div>
							<div class="ha-stat-value"><?= ha_e($s["used"]) ?> / <?= ha_e($s["limit"]) ?><?php if ($s["pct"] !== null) { ?> <span class="ha-muted">(<?= ha_e(round($s["pct"])) ?>%)</span><?php } ?></div>
							<?php if ($s["pct"] !== null) { ?>
								<div class="ha-meter" role="progressbar" aria-label="<?= ha_e($s["label"]) ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= ha_e(round($s["pct"])) ?>"><span style="width: <?= ha_e(max(round($s["pct"], 1), $s["pct"] > 0 ? 1 : 0)) ?>%"></span></div>
							<?php } ?>
						</li>
					<?php } ?>
				</ul>
			</section>
		</aside>
	</div>
</div>

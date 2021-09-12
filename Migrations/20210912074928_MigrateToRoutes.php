<?php declare(strict_types=1);

namespace Nadybot\User\Modules\GAUNTLET_MODULE\Migrations;

use Exception;
use Nadybot\Core\DB;
use Nadybot\Core\DBSchema\Route;
use Nadybot\Core\DBSchema\RouteHopFormat;
use Nadybot\Core\DBSchema\Setting;
use Nadybot\Core\LoggerWrapper;
use Nadybot\Core\MessageHub;
use Nadybot\Core\Routing\Source;
use Nadybot\Core\SchemaMigration;
use Nadybot\Core\SettingManager;
use Nadybot\Core\Timer;

class MigrateToRoutes implements SchemaMigration {
	/** @Inject */
	public MessageHub $messageHub;

	/** @Inject */
	public Timer $timer;

	protected function getSetting(DB $db, string $name): ?Setting {
		return $db->table(SettingManager::DB_TABLE)
			->where("name", $name)
			->asObj(Setting::class)
			->first();
	}

	public function migrate(LoggerWrapper $logger, DB $db): void {
		$channels = $this->getSetting($db, "gauntlet_channels");
		$channels = isset($channels) ? $channels->value : "both";
		$this->timer->callLater(1, [$this, "installRoutes"], $db, $channels);

		// System messages aren't colored anyway
/*
		$color = $this->getSetting($db, "gauntlet_color");
		$color = isset($color) ? $color->value : "<font color=#999900>";
		if (preg_match("/#([a-f0-9]+)/i", $color, $matches)) {
			$routeColor = new RouteHopColor();
			$routeColor->hop = "spawn(gauntlet-*)";
			$routeColor->text_color = $matches[1];
			$db->insert(MessageHub::DB_TABLE_COLORS, $routeColor);
			$this->messageHub->loadTagColor();
		}
*/
	}

	public function installRoutes(DB $db, string $channels): void {
		if (!count($this->messageHub->getRoutes())) {
			$this->timer->callLater(1, [$this, "installRoutes"], $db, $channels);
			return;
		}
		if (!$this->messageHub->hasRouteFor("spawn(gauntlet-prespawn)")) {
			if ($channels === "guild" || $channels === "both") {
				$this->addRoute(
					$db,
					"spawn(gauntlet-*)",
					Source::ORG
				);
			}
			if ($channels === "priv" || $channels === "both") {
				$this->addRoute(
					$db,
					"spawn(gauntlet-*)",
					Source::PRIV . "(" . $db->getMyname() . ")",
				);
			}
			if (!$db->table(Source::DB_TABLE)->where("hop", "spawn")->exists()) {
				$routeFormat = new RouteHopFormat();
				$routeFormat->hop = "spawn";
				$routeFormat->render = false;
				$db->insert(Source::DB_TABLE, $routeFormat);
				$this->messageHub->loadTagFormat();
			}
		}
		if ($channels === "priv" || $channels === "both") {
			$this->addRoute(
				$db,
				Source::SYSTEM . "(gauntlet-buff)",
				Source::PRIV . "(" . $db->getMyname() . ")",
			);
		}
		if ($channels === "guild" || $channels === "both") {
			$this->addRoute(
				$db,
				Source::SYSTEM . "(gauntlet-buff)",
				Source::ORG
			);
		}
	}

	protected function addRoute(DB $db, string $source, string $destination): void {
		$route = new Route();
		$route->source = $source;
		$route->destination = $destination;
		$route->id = $db->insert(MessageHub::DB_TABLE_ROUTES, $route);
		try {
			$msgRoute = $this->messageHub->createMessageRoute($route);
			$this->messageHub->addRoute($msgRoute);
		} catch (Exception $e) {
			// Ain't nothing we can do, errors will be given on next restart
		}
	}
}

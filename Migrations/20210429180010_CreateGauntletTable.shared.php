<?php declare(strict_types=1);

namespace Nadybot\User\Modules\GAUNTLET_MODULE\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Nadybot\Core\DB;
use Nadybot\Core\LoggerWrapper;
use Nadybot\Core\SchemaMigration;

class CreateGauntletTable implements SchemaMigration {
	public function migrate(LoggerWrapper $logger, DB $db): void {
		$table = "gauntlet";
		if ($db->schema()->hasTable($table)) {
			return;
		}
		$db->schema()->create($table, function(Blueprint $table) {
			$table->string("player", 25)->nullable();
			$table->text("items")->nullable();
		});
	}
}

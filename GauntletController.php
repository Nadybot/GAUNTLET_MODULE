<?php

namespace Nadybot\User\Modules\GAUNTLET_MODULE;

use DateTime;
use DateTimeZone;
use Nadybot\Core\{
	CommandReply,
	DB,
	LoggerWrapper,
	MessageEmitter,
	MessageHub,
	Modules\ALTS\AltsController,
	Nadybot,
	Routing\RoutableMessage,
	Routing\Source,
	SettingManager,
	Text,
	Util,
};
use Nadybot\Modules\TIMERS_MODULE\Alert;
use Nadybot\Modules\TIMERS_MODULE\Timer;
use Nadybot\Modules\TIMERS_MODULE\TimerController;

/**
 * @author Equi
 * @author Nadyita (RK5) <nadyita@hodorraid.org>
 * @Instance
 *
 * Commands this controller contains:
 *	@DefineCommand(
 *		command     = 'gauntlet',
 *		accessLevel = 'all',
 *		description = 'shows timer of Gauntlet',
 *		help        = 'gautimer.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gauntlet sub .+',
 *		accessLevel = 'all',
 *		description = 'subscribe for a Gauntletraid',
 *		help        = 'gautimer.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gauntlet subalt .+',
 *		accessLevel = 'all',
 *		description = 'shows a list to sub an alt',
 *		help        = 'gautimer.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gauntlet rollqueue',
 *		accessLevel = 'all',
 *		description = 'rolls the subscriberlist for gauntlet',
 *		help        = 'gautimer.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gauupdate',
 *		accessLevel = 'all',
 *		description = 'manual Gaunlet update',
 *		help        = 'gautimer.txt',
 *		alias		= 'gauset'
 *	)
 *	@DefineCommand(
 *		command     = 'gaukill',
 *		accessLevel = 'all',
 *		description = 'Gauntlet killtime',
 *		help        = 'gautimer.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gautrade',
 *		accessLevel = 'all',
 *		description = 'Gauntlet tradeskills',
 *		help        = 'gautimer.txt'
 *	)
 *
 * Gauntlet inventory part
 *
 *	@DefineCommand(
 *		command     = 'gaulist register',
 *		accessLevel = 'all',
 *		description = 'Registers a Gauntlet inventory for the Char',
 *		help        = 'gaulist.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gaulist',
 *		accessLevel = 'all',
 *		description = 'Manage the stuff u got and need',
 *		help        = 'gaulist.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gaulist add .+',
 *		accessLevel = 'all',
 *		description = 'Adds a item',
 *		help        = 'gaulist.txt'
 *	)
 *	@DefineCommand(
 *		command     = 'gaulist del .+',
 *		accessLevel = 'all',
 *		description = 'Removes a item',
 *		help        = 'gaulist.txt'
 *	)
 *
 * Gaubuff
 *
 *	@DefineCommand(
 *		command     = 'gaubuff',
 *		accessLevel = 'all',
 *		description = 'Handles timer for gauntlet buff',
 *		help        = 'gaubuff.txt'
 *	)
 */
class GauntletController implements MessageEmitter {

	public const SIDE_NONE = 'none';
	public const TIMER = 'Gauntlet';

	/**
	 * Name of the module.
	 * Set automatically by module loader.
	 */
	public string $moduleName;

	/** @Inject */
	public Text $text;

	/** @Inject */
	public SettingManager $settingManager;

	/** @Inject */
	public MessageHub $messageHub;

	/** @Inject */
	public Nadybot $chatBot;

	/** @Inject */
	public DB $db;

	/** @Inject */
	public Util $util;

	/** @Inject */
	public AltsController $altsController;

	/** @Logger */
	public LoggerWrapper $logger;

	/** @Inject */
	public TimerController $timerController;

	//(ref , image, need) 16 items without basic armor
	private $gaulisttab =   [
		[292507, 292793, 3], [292509, 292775, 1], [292508, 292776, 1], [292510, 292774, 1],
		[292514, 292764, 1], [292515, 292780, 1], [292516, 292792, 1], [292532, 292760, 3],
		[292533, 292788, 3], [292529, 292779, 3], [292530, 292759, 3], [292524, 292784, 3],
		[292538, 292772, 3], [292525, 292763, 3], [292526, 292777, 3], [292528, 292778, 3],
		[292517,292762,3]
	];

	public function getChannelName(): string {
		return Source::SYSTEM . "(gauntlet-buff)";
	}

	/**
	 * @Setup
	 * This handler is called on bot startup.
	 */
	public function setup() {
		$this->db->loadMigrations($this->moduleName, __DIR__ . '/Migrations');
		$this->settingManager->add(
			$this->moduleName,
			"gauntlet_timezone",
			"Choose your timezone",
			"edit",
			"text",
			"Europe/Berlin",
			"Europe/Berlin;America/New_York;Europe/Amsterdam;Europe/London",
			'',
			"mod"
		);
		$this->settingManager->add(
			$this->moduleName,
			'gauntlet_times',
			'Times to display timer alerts',
			'edit',
			'text',
			'2h 1h 30m 10m',
			'2h 1h 30m 10m',
			'',
			'mod',
			'gau_times.txt'
		);
		$this->settingManager->add(
			$this->moduleName,
			"gauntlet_portaltime",
			"Select how long Gauntletportal is open",
			'edit',
			'text',
			'6m30s',
			'6m30s',
			'',
			'mod',
			'gau_times.txt'
		);
		$this->settingManager->add(
			$this->moduleName,
			"gauntlet_color",
			"Color for the gauntlet chat timer",
			"edit",
			"color",
			"<font color=#999900>"
		);
		$this->settingManager->add(
			$this->moduleName,
			'gauntlet_channels',
			'Channels to display timer alerts',
			'edit',
			'text',
			'both',
			'guild;priv;both',
			'',
			'mod',
			'gau_times.txt'
		);
		$this->settingManager->add(
			$this->moduleName,
			'gauntlet_autoset',
			'Automaticly reset timer on restart or reconnect',
			"edit",
			"options",
			"0",
			"true;false",
			"1;0"
		);
		$this->settingManager->add(
			$this->moduleName,
			'gaubuff_times',
			'Times to display gaubuff timer alerts',
			'edit',
			'text',
			'30m 10m',
			'30m 10m',
			'',
			'mod',
			'gau_times.txt'
		);
		$this->settingManager->add(
			$this->moduleName,
			"gaubuff_logon",
			"Show gaubuff timer on logon",
			"edit",
			"options",
			"1",
			"true;false",
			"1;0"
		);
		$this->settingManager->add(
			$this->moduleName,
			"gaubuff_default_side",
			"Implicit gauntlet buff side if none specified",
			"edit",
			"options",
			"none",
			"none;clan;omni"
		);
		foreach (["prespawn", "spawn", "vulnerable"] as $event) {
			$emitter = new GauntletChannel("gauntlet-{$event}");
			$this->messageHub->registerMessageEmitter($emitter);
		}
		$this->messageHub->registerMessageEmitter($this);
	}

	private function tmTime($zz) {
		//This wouldn't be necessary if you would add timezone option for the bot into the configs :P
		$gtime = new DateTime();
		$gtime->setTimestamp($zz);
		$gtime->setTimezone(new DateTimeZone($this->settingManager->get('gauntlet_timezone')));
		return $gtime->format("D, H:i T (Y-m-d)");
	}

	public function gauntgetTime($zz) {
		//This wouldn't be necessary if you would add timezone option for the bot into the configs :P
		$timer = $this->timerController->get(self::TIMER);
		if ($timer === null) {
			return 0;
		}
		return $this->tmTime($timer->endtime + 61620*$zz);
	}

	private function checkalt($name, $name2) {
		$altInfo = $this->altsController->getAltInfo($name2);
		return in_array($name, $altInfo->getAllAlts());
	}

	/**
	 * @HandlesCommand("gautrade")
	 * @Matches("/^gautrade$/i")
	 */
	public function gautradeCommand($message, $channel, $sender, $sendto, $args) {
		$info = file_get_contents(__DIR__.'/gautrade');
		$msg = $this->text->makeLegacyBlob("Gauntlet Tradeskills", $info);
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("gauntlet sub .+")
	 * @Matches("/^gauntlet sub ([0-9]) ([a-z0-9]+)$/i")
	 */
	public function gauntletSubCommand($message, $channel, $sender, $sendto, $args) {
		//*** subscribe/unsubscribe for raid ***
		if (!isset($args[2])) {
			$args[2] = $sender;
		}
		if (($args[1] > 9) || ($args[1] < 0)) {
			$sendto->reply("This raid doesn't exist!");
			return;
		}
		if (!$this->checkalt($sender, $args[2])) {
			$sendto->reply("This is none of your chars!");
			return;
		}
		if (isset($this->gaumem[$args[1]][$args[2]])) {
			$this->gaumem[$args[1]][$args[2]]=0;
			unset($this->gaumem[$args[1]][$args[2]]);
			$msg = "You removed <highlight>$args[2]<end> from the Gauntlet at ".$this->gauntgetTime($args[1]);
		} else {
			$this->gaumem[$args[1]][$args[2]]=1;
			$msg = "<highlight>$args[2]<end> is now subscribed for the Gauntlet at ".$this->gauntgetTime($args[1]);
		}
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("gauntlet subalt .+")
	 * @Matches("/^gauntlet subalt ([0-9]) ([a-z0-9]+)$/i")
	 */
	public function gauntletSubaltCommand($message, $channel, $sender, $sendto, $args) {
		//*** subscribe/unsubscribe for raid if u have too many alts! ***
		if (!isset($args[2])) {
			$args[2] = $sender;
		};
		if (($args[1] > 9) || ($args[1] < 0)) {
			$sendto->reply("This raid doesn't exist!");
			return;
		}
		if (!$this->checkalt($sender, $args[2])) {
			$sendto->reply("This is none of your chars!");
			return;
		}
		$msg = "Subscription for the Gauntlet at ".$this->gauntgetTime($args[1])." with the main $args[2]:\n\n";
		$altInfo = $this->altsController->getAltInfo($args[2]);
		$allAlts = $altInfo->getAllAlts();
		sort($allAlts);
		foreach ($allAlts as $alt) {
			$msg .= "     - <a href='chatcmd:///tell <myname> <symbol>gauntlet sub $args[1] $alt'>$alt</a>\n";
		}
		$msg = $this->text->makeBlob("Un/Subscribe", $msg);
		$sendto->reply($msg, $sendto);
	}

	private function gauRollQueue() {
		$gau = $this->gaumem;
		unset($this->gaumem);
		for ($z = 1; $z <= 9; $z++) {
			foreach ($gau[$z] as $key => $value) {
				$this->gaumem[$z-1][$key]=1;
			}
		}
		unset($gau);
	}

	/**
	 * @HandlesCommand("gauntlet rollqueue")
	 * @Matches("/^gauntlet rollqueue$/i")
	 */
	public function gauntletRollqueueCommand($message, $channel, $sender, $sendto, $args) {
		//*** roll manual subscribe queue raid; only raidleader! ***
		$this->gauRollQueue();
		$msg = "Manual queue rolled!";
		$sendto->reply($msg);
	}

	public function getGauCreator() {
		$timer = $this->timerController->get(self::TIMER);
		if ($timer === null) {
			return null;
		}
		return json_decode($timer->data, true);
	}

	/**
	 * This command handler shows gauntlet.
	 *
	 * @HandlesCommand("gauntlet")
	 * @Matches("/^gauntlet$/i")
	 */
	public function gauntletCommand($message, $channel, $sender, $sendto, $args) {
		$timer = $this->timerController->get(self::TIMER);
		if ($timer === null) {
			$sendto->reply("No Gauntlettimer set! Seems like someone deleted it.");
			return;
		}
		$gautimer = $timer->endtime;
		$dt = $gautimer-time();
		$list = "Tradeskill: [<a href='chatcmd:///tell <myname> <symbol>gautrade'>Click me</a>]\n";
		$creatorinfo = $this->getGauCreator();
		$list .= "Timer updated at <highlight>".$this->tmTime($creatorinfo['createtime'])."<end>\n\n";

		//alts handler more or less 8! Every blob has its max size, so we need such a thing
		$altInfo = $this->altsController->getAltInfo($sender);
		$aashort = count($altInfo->getAllAlts()) >= 9;

		//spawntimes
		for ($z = 0; $z <= 9; $z++) {
			$list .= "    - ".$this->gauntgetTime($z)."\n";
			//subscriber list
			if (count($this->gaumem[$z])>0) {
				$list .= "         <yellow>";
				$list .= join(', ', array_keys($this->gaumem[$z]));
				$list .= " <end>\n         Sub/Unsub with |";
			} else {
				$list .= "         Sub/Unsub with |";
			}
			//add altslist
			if ($aashort == false) {
				foreach ($altInfo->getAllAlts() as $alt) {
					$list .= "<a href='chatcmd:///tell <myname> <symbol>gauntlet sub $z $alt'>$alt</a>|";
				}
			} else {
				$list .= "<a href='chatcmd:///tell <myname> <symbol>gauntlet sub $z $altInfo->main'>$altInfo->main</a>|";
				$list .= "<a href='chatcmd:///tell <myname> <symbol>gauntlet subalt $z $altInfo->main'>Other chars</a>|";
			}
			$list .= "\n\n";
		}
		$link = $this->text->makeBlob("Spawntimes for Portals", $list);

		//if portal is open
		$gptime = time()+61620-$gautimer;
		if (($gptime > 0) && ($gptime <=($this->settingManager->get('gauntlet_portaltime')*60))) {
			$gptime = $this->settingManager->get('gauntlet_portaltime')*60-$gptime;
			$msg = "<highlight>Portal is open for <end><red>".$this->util->unixtimeToReadable($gptime)."<end><highlight>!<end> ".$link;
			//otherwise show normal style
		} else {
			$msg = "<highlight>".$this->util->unixtimeToReadable($dt)."<end> until Vizaresh is vulnerable. ".$link;
		}

		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("gaukill")
	 * @Matches("/^gaukill$/i")
	 */
	public function gaukillCommand($message, $channel, $sender, $sendto, $args) {
		//Gauntlet portal will be there again in 61620 secs!
		$this->setGauTime(time()+61620, $sender, time());
		//roll subscribe list
		$this->gauRollQueue();
		//send something
		$msg="Bot was updated manually! Vizaresh will be vulnerable at ".$this->gauntgetTime(0)."\n (respawn is every 17 hours and 7 minutes)";
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("gauupdate")
	 * @Matches("/^gauupdate ([a-z0-9 ]+)$/i")
	 */
	public function gauupdateCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$newSpawn = $this->util->parseTime($args[1]);
		if ($newSpawn < 1) {
			$msg = "You must enter a valid time parameter for the gauntlet update time.";
			$sendto->reply($msg);
			return;
		}
		$newSpawn += time();
		$this->setGauTime($newSpawn, $sender, time());
		$msg="Bot was updated manually! Vizaresh will be vulnerable at ".$this->gauntgetTime(0)."\n (Normal respawn is every 17h07m)";
		$sendto->reply($msg);
	}

	//**************************************
	//***   Gauntlet inventory from here on
	//**************************************

	private function checkZero($number) {
		return max(0, $number);
	}

	private function gaudbexists($name): bool {
		return $this->db->table('gauntlet')
			->where("player", $name)
			->exists();
	}

	private function bastioninventory($name, $ac) {
		//check is done earlier, get data here
		$row = $this->db->table('gauntlet')
			->where("player", $name)
			->limit(1)
			->asObj()->first();
		$tem = unserialize($row->items);
		if (($ac<0)&&($ac>3)) {
			$ac = 1;
		}
		//Do blob box
		$list = "Tradeskill: [<a href='chatcmd:///tell <myname> <symbol>gautrade'>Click me</a>]\n" .
			"Needed items for: [<a href='chatcmd:///tell <myname> <symbol>gaulist $name 1'>1 Armor</a>|" .
			"<a href='chatcmd:///tell <myname> <symbol>gaulist $name 2'>2 Armor</a>|" .
			"<a href='chatcmd:///tell <myname> <symbol>gaulist $name 3'>3 Armor</a>]\n";
		$list .= "Items needed for ".$ac." Bastionarmorparts.\n<green>[Amount you have]<end>|<red>[Amount you need]<end>\n[+]=increase Item      [-]=decrease Item\n\n";

		for ($i = 0; $i <= 16; $i++) {
			$list .= "    <a href='itemref://".$this->gaulisttab[$i][0]."/".$this->gaulisttab[$i][0]."/".$this->gaulisttab[$i][0]."'><img src='rdb://".$this->gaulisttab[$i][1]."'></a>    ";
			if ((($i+1) % 4)==0) {
				$list .= "\n";
				for ($ii = $i-3; $ii<=$i; $ii++) {
					$list .= "[".
						"<a href='chatcmd:///tell <myname> <symbol>gaulist add $name $ii'> + </a>".
						"|".
						"<green>".$tem[$ii]."<end>".
						"|".
						"<red>".$this->checkZero(($ac*$this->gaulisttab[$ii][2])-$tem[$ii])."<end>".
						"|".
						"<a href='chatcmd:///tell <myname> <symbol>gaulist del $name $ii'> - </a>".
						"] ";
				}
				$list .= "\n";
			} elseif ($i==16) {
				$list .= "\n[".
					"<a href='chatcmd:///tell <myname> <symbol>gaulist add $name $i'> + </a>".
					"|".
					"<green>".$tem[$i]."<end>".
					"|".
					"<red>".$this->checkZero(($ac*$this->gaulisttab[$i][2])-$tem[$i])."<end>".
					"|".
					"<a href='chatcmd:///tell <myname> <symbol>gaulist del $name $i'> - </a>".
					"]\n";
			}
		}
		$list .= "\n                         <a href='chatcmd:///tell <myname> <symbol>gaulist $name $ac'>Refresh</a>";
		$link = $this->text->makeBlob("Bastion inventory for $name", $list);
		$tem = "Bastion inventory: ".$link;
		return $tem;
	}

	/**
	 * @HandlesCommand("gaulist register")
	 * @Matches("/^gaulist register$/i")
	 */
	public function gaulistRegisterCommand($message, $channel, $sender, $sendto, $args) {
		//Creates a db for your char
		//1. Check if player is in db and create if not
		if ($this->gaudbexists($sender)==false) {
			$this->db->table("gauntlet")
				->insert([
					"player" => $sender,
					"items" => serialize([0,0,0,0,0, 0,0,0,0,0, 0,0,0,0,0, 0,0]),
				]);
			$msg = "Gauntlet inventory created for $sender.";
		} else {
			$msg = "You already have a Gauntlet inventory!";
		}
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("gaulist")
	 * @Matches("/^gaulist$/i")
	 * @Matches("/^gaulist ([a-z0-9]+)$/i")
	 * @Matches("/^gaulist ([a-z0-9]+) ([0-9])$/i")
	 */
	public function gaulistCommand($message, $channel, $sender, $sendto, $args) {
		if (count($args)==3) {
			$name = ucfirst(strtolower($args[1]));
			$ac = $args[2];
		} elseif (count($args)==2) {
			if (preg_match('/^\d+$/', $args[1])) {
				$name = $sender;
				$ac = $args[1];
			} else {
				$name = ucfirst(strtolower($args[1]));
				$ac = 1;
			}
		} else {
			$name = $sender;
			$ac = 1;
		}
		//check and get Bastioninventory
		if ($this->gaudbexists($name)) {
			$msg = $this->bastioninventory($name, $ac);
		} else {
			$msg = "No Bastion inventory found for $name, use <symbol>gaulist register.";
		}
		$sendto->reply($msg);
	}

	protected function altCheck($sendto, $sender, $name) {
		if ($this->gaudbexists($name) && ($this->checkalt($sender, $name))) {
			return true;
		}
		$sendto->reply("Player \"$name\" doesn't exist or is not your alt.");
		return false;
	}

	/**
	 * @HandlesCommand("gaulist add .+")
	 * @Matches("/^gaulist add ([a-z0-9]+) ([0-9]+)$/i")
	 */
	public function gaulistAddCommand($message, $channel, $sender, $sendto, $args) {
		$tt = [];
		$tt = array_fill(0, 16, 0);
		$name = ucfirst(strtolower($args[1]));
		// Check and increase item
		if ($this->altCheck($sendto, $sender, $name) === false) {
			return;
		}
		if (($args[2]>=0)&&($args[2]<17)) {
			$row = $this->db->table("gauntlet")
				->where("player", $name)
				->limit(1)
				->asObj()
				->first();
			$tt = unserialize($row->items);
			++$tt[$args[2]];
			$this->db->table("gauntlet")
				->where("player", $name)
				->update([
					"items" => serialize($tt),
				]);
			$msg = "Item increased!";
		} else {
			$msg = "No valid itemID.";
		}
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("gaulist del .+")
	 * @Matches("/^gaulist del ([a-z0-9]+) ([0-9]+)$/i")
	 */
	public function gaulistDelCommand($message, $channel, $sender, $sendto, $args) {
		$tt = [];
		$tt = array_fill(0, 16, 0);
		$name = ucfirst(strtolower($args[1]));
		// Check and increase item
		if ($this->altCheck($sendto, $sender, $name) === false) {
			return;
		}
		if (($args[2] >= 0) && ($args[2] < 17)) {
			$row = $this->db->table("gauntlet")
				->where("player", $name)
				->limit(1)
				->asObj()->first();
			$tt = unserialize($row->items);
			if ($tt[$args[2]]>0) {
				--$tt[$args[2]];
				$this->db->table("gauntlet")
					->where("player", $name)
					->update([
						"items" => serialize($tt),
					]);
				$msg = "Item decreased!";
			} else {
				$msg = "Item is already at zero.";
			}
		} else {
			$msg = "No valid itemID.";
		}
		$sendto->reply($msg);
	}

	//**************************************
	//***   Gaubuff timer
	//**************************************

	public function setGaubuff(string $side, string $time, string $creator, int $createtime): void {
		$alerts = [];
		foreach (explode(' ', $this->settingManager->get('gaubuff_times')) as $utime) {
			$alertTimes[] = $this->util->parseTime($utime);
		}
		$alertTimes[] = 0;                  //timer runs out
		foreach ($alertTimes as $alertTime) {
			if (($time - $alertTime) > time()) {
				$alert = new Alert();
				$alert->time = $time - $alertTime;
				$alert->message = "<{$side}>" . ucfirst($side) . "<end> Gauntlet buff ";
				if ($alertTime === 0) {
					$alert->message .= "<highlight>expired<end>!";
				} else {
					$alert->message .= "runs out in <highlight>".
						$this->util->unixtimeToReadable($alertTime)."<end>!";
				}
				$alerts []= $alert;
			}
		}
		$data = [];
		$data['createtime'] = $createtime;
		$data['creator'] = $creator;
		$data['repeat'] = 0;
		//*** Add Timers
		$this->timerController->remove("Gaubuff_{$side}");
		$this->timerController->add("Gaubuff_{$side}", $this->chatBot->vars['name'], $this->settingManager->get('gauntlet_channels'), $alerts, "GauntletController.gaubuffcallback", json_encode($data));
	}

	public function gaubuffcallback(Timer $timer, Alert $alert) {
		$rMsg = new RoutableMessage($alert->message);
		$rMsg->appendPath(new Source(
			Source::SYSTEM,
			"gauntlet-buff"
		));
		$this->messageHub->handle($rMsg);
	}

	protected function showGauntletBuff(string $sender): void {
		$sides = $this->getSidesToShowBuff($args['side']??null);
		$msgs = [];
		foreach ($sides as $side) {
			$timer = $this->timerController->get("Gaubuff_{$side}");
			if ($timer === null) {
				continue;
			}
			$msgs []= "<{$side}>" . ucfirst($side) . " Gauntlet buff<end> ".
					$this->settingManager->get('gauntlet_color').
					"runs out in <highlight>".
					$this->util->unixtimeToReadable($timer->endtime - time()).
					"<end><end>!";
		}
		if (empty($msgs)) {
			return;
		}
		$this->chatBot->sendMassTell(join("\n", $msgs), $sender);
	}

	/**
	 * @Event("logOn")
	 * @Description("Sends gaubuff message on logon")
	 */
	public function gaubufflogonEvent($eventObj) {
		$sender = $eventObj->sender;
		if (!$this->chatBot->isReady()
			|| (!isset($this->chatBot->guildmembers[$sender]))
			|| (!$this->settingManager->get('gaubuff_logon'))) {
			return;
		}
		$this->showGauntletBuff($sender);
	}

	/**
	 * @Event("joinPriv")
	 * @Description("Sends gaubuff message on join")
	 */
	public function privateChannelJoinEvent($eventObj) {
		$sender = $eventObj->sender;
		if ($this->settingManager->get('gaubuff_logon')) {
			$this->showGauntletBuff($sender);
		}
	}

	/**
	 * This command handler shows gauntlet.
	 *
	 * @HandlesCommand("gaubuff")
	 * @Matches("/^gaubuff$/i")
	 * @Matches("/^gaubuff (?<side>clan|omni)$/i")
	 * @Matches("/^gaubuff (?<side>clan|omni) (?<time>[a-z0-9 ]+)$/i")
	 * @Matches("/^gaubuff (?<time>[a-z0-9 ]+)$/i")
	 */
	public function gaubuffCommand($message, $channel, $sender, $sendto, $args) {
		//set time
		$defaultSide = $this->settingManager->getString('gaubuff_default_side');
		if (!isset($args['side'])
			&& isset($args['time'])
			&& $defaultSide === static::SIDE_NONE
		) {
			$msg = "You have to specify for which side the buff is: omni or clan";
			$sendto->reply($msg);
			return;
		}
		if (isset($args['time'])) {
			$side = $args['side'] ?? $defaultSide;
			$buffEnds = $this->util->parseTime($args['time']);
			if ($buffEnds < 1) {
				$msg = "You must enter a valid time parameter for the gauntlet buff time.";
				$sendto->reply($msg);
				return;
			}
			$buffEnds += time();
			$this->setGaubuff($side, $buffEnds, $sender, time());
			$msg = "Gauntletbuff timer for <{$side}>{$side}<end> has been set and expires at <highlight>".$this->tmTime($buffEnds)."<end>.";
			$sendto->reply($msg);
			return;
		}
		$sides = $this->getSidesToShowBuff($args['side']??null);
		$msgs = [];
		foreach ($sides as $side) {
			//get time
			$timer = $this->timerController->get("Gaubuff_{$side}");
			if ($timer !== null) {
				$gaubuff = $timer->endtime - time();
				$msgs []= "<{$side}>" . ucfirst($side) . " Gauntlet buff<end> runs out ".
					"in <highlight>".$this->util->unixtimeToReadable($gaubuff)."<end>.";
			}
		}
		if (empty($msgs)) {
			if (isset($args['side'])) {
				$sendto->reply("No <{$side}>{$side} Gauntlet buff<end> available!");
			} else {
				$sendto->reply("No Gauntlet buff available!");
			}
			return;
		}
		$sendto->reply(join("\n", $msgs));
	}

	/**
	 * Get a list of array for which to show the gauntlet buff(s)
	 * @return string[]
	 */
	protected function getSidesToShowBuff(?string $side): array {
		$defaultSide = $this->settingManager->getString('gaubuff_default_side');
		$side ??= $defaultSide;
		if ($side === static::SIDE_NONE) {
			return ['clan', 'omni'];
		}
		return [$side];
	}

	//**************************************
	//***   Gauntlet event things
	//**************************************

	/**
	 * @Event("connect")
	 * @Description("Initialize timers")
	 */
	public function intializeTimersEvent($eventObj) {
		if ($this->settingManager->get('gauntlet_autoset')) {
			$this->setGauTime(time()+480, $this->chatBot->vars['name'], time());
		}
	}

	private function gauAlert($tstr) {
		foreach ($this->gaumem[0] as $key => $value) {
			$altInfo = $this->altsController->getAltInfo($key);
			foreach ($altInfo->getOnlineAlts() as $name) {
				if ($name != $key) {
					$this->chatBot->sendMassTell("<red>Gauntlet is in $tstr (subscribed with $key).<end>", $name);
				} else {
					$this->chatBot->sendMassTell("<red>Gauntlet is in $tstr.<end>", $name);
				}
			}
		}
	}

	public function gauntletcallback(Timer $timer, Alert $alert) {
		if ($timer->endtime - $alert->time == 1800) {
			$this->gauAlert("30 min");
			//this could be upgraded by adding setting etc
		}
		$rMsg = new RoutableMessage($alert->message);
		$rMsg->appendPath(new Source(
			"spawn",
			"gauntlet-" . ($alert->event ?? "spawn")
		));
		$this->messageHub->handle($rMsg);
		if (count($timer->alerts) === 0) {
			$data= json_decode($timer->data, true);
			$this->setGauTime($timer->endtime + $data['repeat'], $data['creator'], $data['createtime']);
			//roll subscribe list, keeeeeppp on rollin rollin rollin...^^
			$this->gauRollQueue();
		}
	}

	public function setGauTime($time, $creator, $createtime) {
		/** @var Alert[] */
		$alerts = [];
		$portaltime = $this->util->parseTime($this->settingManager->get('gauntlet_portaltime'));
		foreach (preg_split('/\s+/', $this->settingManager->get('gauntlet_times')) as $utime) {
			$alertTimes[] = $this->util->parseTime($utime);
		}
		$alertTimes[] = 61620-$portaltime;  //portal closes
		$alertTimes[] = 0;                  //vulnerable
		$alertTimes[] = 420;                //spawn
		//make sure the order is correct...maybe this little thing could be included in the Timecontroller.class.php when adding
		rsort($alertTimes);
		foreach ($alertTimes as $alertTime) {
			if (($time - $alertTime)>time()) {
				$alert = new GauntletAlert();
				$alert->time = $time - $alertTime;
				if ($alertTime == 0) {
					$alert->event = "vulnerable";
					$alert->message = "Vizaresh <highlight>VULNERABLE/DOWN<end>!";
				} elseif ($alertTime == 420) {
					$alert->event = "spawn";
					$alert->message = "Vizaresh <highlight>SPAWNED (7 min left)<end>!";
				} elseif ($alertTime == (61620-$portaltime)) {
					$alert->event = "vulnerable";
					$alert->message = "Portal is <highlight>GONE<end>!";
				} elseif ($alertTime > (61620-$portaltime)) {
					$alert->event = "vulnerable";
					$alert->message = "Portal is open for <red>".$this->util->unixtimeToReadable($alertTime)."<end>!";
				} else {
					$alert->event = "prespawn";
					$alert->message = "Gauntlet is in <highlight>".$this->util->unixtimeToReadable($alertTime)."<end>!";
				}
				$alerts []= $alert;
			}
		}
		$data = [];
		$data['createtime'] = $createtime;
		$data['creator'] = $creator;
		$data['repeat'] = 61620;

		//*** Add Timers
		$this->timerController->remove(self::TIMER);
		$this->timerController->add('Gauntlet', $this->chatBot->vars['name'], $this->settingManager->get('gauntlet_channels'), $alerts, "GauntletController.gauntletcallback", json_encode($data));
	}

	/**
	 * @NewsTile("gauntlet")
	 * @Description("Show spawn status of Vizaresh spawns and the
	 * status of the currently popped Gauntlet buff")
	 */
	public function gauntletNewsTile(string $sender, callable $callback): void {
		$timerLine = $this->getGauntletTimerLine();
		$buffLine = $this->getGauntletBuffLine();
		if (!isset($timerLine) && !isset($buffLine)) {
			$callback(null);
			return;
		}
		$blob = "<header2>Gauntlet<end>\n".
			($buffLine??"").
			($timerLine??"");
		$callback($blob);
	}

	/**
	 * @NewsTile("gauntlet-timer")
	 * @Description("Show when Vizaresh spawns/is vulnerable")
	 */
	public function gauntletTimerNewsTile(string $sender, callable $callback): void {
		$timerLine = $this->getGauntletTimerLine();
		if (isset($timerLine)) {
			$timerLine = "<header2>Gauntlet<end>\n{$timerLine}";
		}
		$callback($timerLine);
	}

	/**
	 * @NewsTile("gauntlet-buff")
	 * @Description("If the Gauntlet buff has been popped, show how much is remaining")
	 */
	public function gauntletBuffNewsTile(string $sender, callable $callback): void {
		$buffLine = $this->getGauntletBuffLine();
		if (isset($buffLine)) {
			$buffLine = "<header2>Gauntlet buff<end>\n{$buffLine}";
		}
		$callback($buffLine);
	}

	protected function getGauntletTimerLine(): ?string {
		$timer = $this->timerController->get(self::TIMER);
		if ($timer === null) {
			return null;
		}
		$gautimer = $timer->endtime;
		//if portal is open
		$gptime = time()+61620-$gautimer;
		if (($gptime > 0) && ($gptime <=($this->settingManager->get('gauntlet_portaltime')*60))) {
			$gptime = $this->settingManager->get('gauntlet_portaltime')*60-$gptime;
			return "<tab>Portal is <green>open<end> for <highlight>".
				$this->util->unixtimeToReadable($gptime)."<end>.\n";
		}
		$dt = $gautimer-time();
		return "<tab>Vizaresh will be vulnerable in <highlight>".$this->util->unixtimeToReadable($dt)."<end>.\n";
	}

	public function getGauntletBuffLine(): ?string {
		$defaultSide = $this->settingManager->getString('gaubuff_default_side');
		$sides = $this->getSidesToShowBuff(($defaultSide === "none") ? null : $defaultSide);
		$msgs = [];
		foreach ($sides as $side) {
			$timer = $this->timerController->get("Gaubuff_{$side}");
			if ($timer !== null) {
				$gaubuff = $timer->endtime - time();
				$msgs []= "<tab><{$side}>" . ucfirst($side) . " Gauntlet buff<end> runs out ".
					"in <highlight>".$this->util->unixtimeToReadable($gaubuff)."<end>.\n";
			}
		}
		if (empty($msgs)) {
			return null;
		}
		return join("", $msgs);
	}
}

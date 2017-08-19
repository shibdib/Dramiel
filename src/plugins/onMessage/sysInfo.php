<?php

use Discord\Discord;

class sysInfo
{
	public $config;
	public $discord;
	public $logger;
	private $excludeChannel;
	private $message;
	private $triggers;

	public function init($config, $discord, $logger)
	{
		$this->config = $config;
		$this->discord = $discord;
		$this->logger = $logger;
		$this->excludeChannel = $this->config['bot']['restrictedChannels'];
		$this->triggers[] = $this->config['bot']['trigger'] . 'sys';
		$this->triggers[] = $this->config['bot']['trigger'] . 'Sys';
		$this->triggers[] = $this->config['bot']['trigger'] . 'sysinfo';
		$this->triggers[] = $this->config['bot']['trigger'] . 'system';
		$this->triggers[] = $this->config['bot']['trigger'] . 'System';
		$this->triggers[] = $this->config['bot']['trigger'] . 'Sysinfo';
	}

	public function onMessage($msgData, $message)
	{
		$channelID = (int) $msgData['message']['channelID'];

		if(in_array($channelID, $this->excludeChannel, true))
		{
			return null;
		}

		$this->message = $message;

		$message = $msgData['message']['message'];
		$user = $msgData['message']['from'];

		$data = command($message, $this->information()['trigger'], $this->config['bot']['trigger']);
		if(isset($data['trigger']))
		{
			$messageString = strstr($data['messageString'], '@') ? str_replace('<@', '', str_replace('>', '', $data['messageString'])) : $data['messageString'];
			if(is_numeric($messageString))
			{
				$messageString = dbQueryField('SELECT name FROM usersSeen WHERE id = :id', 'name', array(':id' => $messageString));
			}
			$cleanString = urlencode($messageString);
			$sysID = urlencode(getSystemID($cleanString));

			//Check if we get a system back, otherwise check for partials
			if(empty($sysID))
			{
                return $this->message->reply('**Error:** no data available');
			}

			$systemDetails = systemDetails($sysID);
			if (null === $systemDetails)
			{
				return $this->message->reply('**Error:** ESI is down. Try again later.');
			}

			$url = "https://zkillboard.com/api/stats/solarSystemID/{$sysID}/";
			$json = json_decode(file_get_contents($url));

			$regionID = $json->info->regionID;
			$regionName = getRegionName($regionID);

			$thisMonth = (string)date('Ym');
            $lastMonth = (string)date('Ym', strtotime('first day of previous month'));

            $thisMonthKill = $json->months->$thisMonth->shipsDestroyed;
            $lastMonthKill = $json->months->$lastMonth->shipsDestroyed;

            $activePVP = $json->activepvp;
            $activeKills = $activePVP->kills->count;
            $activeChars = $activePVP->characters->count;

			$sysName = $systemDetails['name'];
			$secStatus = round($systemDetails['security_status'],2);

			$npc = "https://api.eveonline.com/map/kills.xml.aspx";
			$npc = new SimpleXMLElement(file_get_contents($npc));
			foreach($npc->result->rowset->row as $row){
				if($row->attributes()->solarSystemID == $sysID){
					$npcKills = $row->attributes()->factionKills;
					$podKills = $row->attributes()->podKills;
				}
			}

			$url = "https://zkillboard.com/system/{$sysID}/";

			$msg = "```Systeminfo
Name: {$sysName} 
Region: {$regionName}
Security: {$secStatus}

Kills
This Month: {$thisMonthKill}
Last Month: {$lastMonthKill}

Activity
Characters: {$activeChars}
Kills: {$activeKills}
Pod Kills: {$podKills}
NPC Kills: {$npcKills}
```

More information: $url";
			$this->logger->addInfo("sysInfo: Sending system information to {$user}");
			$this->message->reply($msg);
		}
		return null;
	}

	public function information()
	{
		return array(
			'name' => 'sys',
			'trigger' => $this->triggers,
			'information' => 'Returns information for the given system'
			);
	}
}

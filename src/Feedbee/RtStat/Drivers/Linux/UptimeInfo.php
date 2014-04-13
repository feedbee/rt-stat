<?php

namespace Feedbee\RtStat\Drivers\Linux;

class UptimeInfo extends ShellCommand
{
	static protected $command = 'uptime';

	static protected function parse($data)
	{
		$matches = [];
		preg_match('/ (.*) up\s+(.*),\s+(\d+) users,\s+load average: (.*), (.*), (.*)/', $data, $matches);

		return [
			'time' => trim($matches[1]),
			'uptime' => trim($matches[2]),
			'users' => trim($matches[3]),
			'la1' => trim($matches[4]),
			'la5' => trim($matches[5]),
			'la15' => trim($matches[6])
		];
	}
}
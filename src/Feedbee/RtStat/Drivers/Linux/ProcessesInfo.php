<?php

namespace Feedbee\RtStat\Drivers\Linux;

class ProcessesInfo extends ShellCommand
{
	static protected $command = 'ps aux --no-headers | awk \'{ print $8 }\'';

	static protected function parse($data)
	{
		$allProcesses = explode("\n", $data);

		$running = count(array_filter($allProcesses, function ($v) {
				return substr($v, 0, 1) == 'R';
			})) - 1; // all running except self (ps) process
		$sleep = count(array_filter($allProcesses, function ($v) {
			return substr($v, 0, 1) == 'S' || substr($v, 0, 1) == 'D';
		}));
		$stopped = count(array_filter($allProcesses, function ($v) {
			return substr($v, 0, 1) == 'T';
		}));
		$zombie = count(array_filter($allProcesses, function ($v) {
			return substr($v, 0, 1) == 'Z';
		}));

		return [
			'all' => $running + $sleep + $stopped + $zombie,
			'running' => $running,
			'sleep' => $sleep,
			'stopped' => $stopped,
			'zombie' => $zombie
		];
	}
}
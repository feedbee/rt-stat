<?php

namespace Feedbee\RtStat\Drivers\Linux;

class ProcessesInfo extends ShellCommand
{
	static protected $command = 'ps aux --no-headers | awk \'{ print $8 }\'';

	static protected function parse($data) {
		$allProcesses = explode("\n", $data);

		$running = count(array_filter($allProcesses, function ($v) { return $v == 'R'; })) - 1; // all running except self (ps) process
		$sleep = count(array_filter($allProcesses, function ($v) { return $v == 'S' || $v == 'D'; }));
		$stopped = count(array_filter($allProcesses, function ($v) { return $v == 'T'; }));
		$zombie = count(array_filter($allProcesses, function ($v) { return $v == 'Z'; }));

		return [
			'all' => $running + $sleep + $stopped + $zombie,
			'running' => $running,
			'sleep' => $sleep,
			'stopped' => $stopped,
			'zombie' => $zombie
		];
	}
}
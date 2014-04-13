<?php

namespace Feedbee\RtStat\Drivers\Linux;

class MemoryInfo
{
	public static function get()
	{
		$memoryStatFile = "/proc/meminfo";
		$lines = file($memoryStatFile);

		$result = [];
		foreach ($lines as $line) {
			preg_match('/(.*):\s*(\d+)( (.*))?/u', $line, $matches);
			$units = isset($matches[4]) ? $matches[4] : '';
			$result[$matches[1]] = ['value' => (int)$matches[2], 'units' => $units];
		}

		return $result;
	}
}
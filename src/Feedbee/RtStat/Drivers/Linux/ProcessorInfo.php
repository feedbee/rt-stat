<?php

namespace Feedbee\RtStat\Drivers\Linux;

class ProcessorInfo
{
	static private $lastData = [];

	static public function get() {
		$newData = self::getInternal();

		$cpuUsage = [];
		foreach ($newData as $cpuIndex => $cpuData) {
			$deltas = [];
			foreach ($cpuData as $k => $v) {
				$deltas[$k] = $v - self::$lastData[$cpuIndex][$k];
			}

			$diffTotal = array_sum($deltas);
			$diffIdle = $deltas['idle'];
			$diffAllUser = $deltas['user'] + $deltas['nice'];
			$diffAllSystem = $deltas['system'] + $deltas['irc'] + $deltas['softirq'];
			$diffIoWait = $deltas['iowait'] + $deltas['irc'] + $deltas['softirq'];

			$usage = ($diffTotal - $diffIdle) / $diffTotal;
			$user = $diffAllUser / $diffTotal;
			$system = $diffAllSystem / $diffTotal;
			$iowait = $diffIoWait / $diffTotal;

			$lineData = ['usage' => $usage, 'user' => $user, 'system' => $system, 'iowait' => $iowait];
			$lineData = array_map(function($value) { return round($value, 2); }, $lineData);

			$cpuUsage[$cpuIndex] = $lineData;
		}

		self::$lastData = $newData;
		return $cpuUsage;
	}

	static public function getInternal() {
		$cpuStatFile = "/proc/stat"; // file description: http://www.linuxhowtos.org/System/procstat.htm
		$lines = file($cpuStatFile);

		$result = [];
		foreach ($lines as $i => $line) {
			$array = explode(' ', $line);
			$cpuName = $array[0];

			if (strpos($cpuName, 'cpu') !== 0) { // `cpu` entries always on top
				break;
			}

			// Some machines list a `cpu` and a `cpu0`. In this case only
			// return values for the numbered cpu entry.
			if ($cpuName == 'cpu' && isset($lines[$i + 1])
				&& strpos(explode(' ', $lines[$i + 1])[0], 'cpu') === 0
			) {
				continue;
			}

			$values = [];
			foreach (['user','nice','system','idle','iowait','irq','softirq'] as $k => $v) {
				$values[$v] = $array[$k + 1];
			}

			$result[$cpuName] = $values;
		}

		return $result;
	}
}
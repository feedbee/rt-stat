<?php

namespace Feedbee\RtStat\Plugins;

use Feedbee\RtStat\Drivers\Linux\ProcessorInfo;

class CpuUsage
{
	private $lastData = [];

	public function __construct()
	{
		$this->lastData = ProcessorInfo::get();
	}

	public function getName()
	{
		return "cpu_stat";
	}

	public function getData()
	{
		$newData = ProcessorInfo::get();
		$lastData =& $this->lastData;

		$cpuUsage = [];
		foreach ($newData as $cpuIndex => $cpuData) {
			$deltas = [];
			foreach ($cpuData as $k => $v) {
				$deltas[$k] = $v - $lastData[$cpuIndex][$k];
			}

			$diffTotal = array_sum($deltas);
			$diffIdle = $deltas['idle'];
			$diffAllUser = $deltas['user'] + $deltas['nice'];
			$diffAllSystem = $deltas['system'] + $deltas['irq'] + $deltas['softirq'];
			$diffIoWait = $deltas['iowait'] + $deltas['irq'] + $deltas['softirq'];

			$usage = ($diffTotal - $diffIdle) / $diffTotal;
			$user = $diffAllUser / $diffTotal;
			$system = $diffAllSystem / $diffTotal;
			$iowait = $diffIoWait / $diffTotal;

			$lineData = ['usage' => $usage, 'user' => $user, 'system' => $system, 'iowait' => $iowait];
			$lineData = array_map(function ($value) {
				return round($value, 2);
			}, $lineData);

			$cpuUsage[$cpuIndex] = $lineData;
		}

		$this->lastData = $newData;
		return $cpuUsage;
	}
}
<?php

namespace Feedbee\RtStat\Drivers\Linux;

class ProcessorInfo
{
	static public function get() {
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
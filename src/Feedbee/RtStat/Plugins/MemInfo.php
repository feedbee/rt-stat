<?php

namespace Feedbee\RtStat\Plugins;

use Feedbee\RtStat\Drivers\Linux\MemoryInfo;

class MemInfo
{
	public function getName()
	{
		return "meminfo";
	}

	public function getData()
	{
		$data = MemoryInfo::get();
		$values = [];
		foreach ($data as $k => $item) {
			$values[$k] = $item['value'];
		}

		return [
			'memory' => ['total' => $values['MemTotal'], 'used' => $values['MemTotal'] - $values['MemFree'], 'free' => $values['MemFree'],
				'apps' => $values['MemTotal'] - ($values['MemFree'] + $values['Buffers'] + $values['Cached'] + $values['SwapCached']),
				'buffers' => $values['Buffers'], 'cached' => $values['Cached'], 'swapCached' => $values['SwapCached']],
			'swap' => ['total' => $values['SwapTotal'], 'used' => $values['SwapTotal'] - $values['SwapFree'], 'free' => $values['SwapFree']],
		];
	}
}
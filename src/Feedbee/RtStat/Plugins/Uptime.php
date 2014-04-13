<?php

namespace Feedbee\RtStat\Plugins;

use Feedbee\RtStat\Drivers\Linux\UptimeInfo;

class Uptime
{
	public function getName()
	{
		return 'uptime';
	}

	protected function getData()
	{
		return UptimeInfo::get();
	}
}
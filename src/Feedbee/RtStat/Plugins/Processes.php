<?php

namespace Feedbee\RtStat\Plugins;

use Feedbee\RtStat\Drivers\Linux\ProcessesInfo;

class Processes
{
	public function getName()
	{
		return "processes";
	}

	protected function getData()
	{
		return ProcessesInfo::get();
	}
}
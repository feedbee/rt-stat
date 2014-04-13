<?php

namespace Feedbee\RtStat\Plugins;

use Feedbee\RtStat\Drivers\Linux\ProcessesInfo;

class Processes
{
	public function getName()
	{
		return "processes";
	}

	public function getData()
	{
		return ProcessesInfo::get();
	}
}
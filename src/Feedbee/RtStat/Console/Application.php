<?php

namespace Feedbee\RtStat\Console;

use Symfony\Component\Console\Application as SymfonyConsoleApplication;

class Application extends SymfonyConsoleApplication
{
	public function __construct()
	{
		parent::__construct("Rt-Stat", "0.0.2");
		$this->add(new ServerCommand);
	}
}
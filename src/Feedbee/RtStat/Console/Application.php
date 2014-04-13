<?php

namespace Feedbee\RtStat\Console;

use Feedbee\RtStat\Application as RtStatApplication;
use Symfony\Component\Console\Application as SymfonyConsoleApplication;

class Application extends SymfonyConsoleApplication
{
	public function __construct()
	{
		parent::__construct(RtStatApplication::NAME, RtStatApplication::VERSION);
		$this->add(new ServerCommand);
	}
}
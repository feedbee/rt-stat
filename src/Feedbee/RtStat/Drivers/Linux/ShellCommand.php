<?php

namespace Feedbee\RtStat\Drivers\Linux;

class ShellCommand
{
	static protected $command;

	static public function get() {
		return static::parse(static::output());
	}

	static protected function output() {
		$cmd = static::$command;
		return `{$cmd}`;
	}

	static protected function parse($data) {
		return $data;
	}
}
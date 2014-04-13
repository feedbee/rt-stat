<?php

namespace Feedbee\RtStat\Plugins;

interface PluginInterface
{
	public function getName();

	public function getData();
}
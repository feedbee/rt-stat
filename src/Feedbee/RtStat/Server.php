<?php

namespace Feedbee\RtStat;

use Ratchet\Server\IoServer;

class Server
{
	static public function run($port = 8000) {
		$server = IoServer::factory(new MessageComponent, $port);
		$server->run();
	}
}
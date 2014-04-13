<?php

namespace Feedbee\RtStat;

use Psr\Log\LoggerInterface;
use Ratchet\Server\IoServer;
use React\EventLoop\Factory as EventLoopFactory;
use React\Socket\Server as SocketServer;

class Server
{
	/**
	 * @var \Ratchet\Server\IoServer
	 */
	private $server;

	private $logger;

	public function __construct(LoggerInterface $logger = null, $port = 8000, $address = '0.0.0.0')
	{
		$this->logger = $logger;
		$logger && $this->logger->info("Server config: {$address}:{$port}");

		$loop = EventLoopFactory::create();

		$socket = new SocketServer($loop);
		$socket->listen($port, $address);

		$messageComponent = new MessageComponent($loop, $logger);

		$server = new IoServer(
		/*new WsServer*/
			($messageComponent),
			$socket,
			$loop
		);

		$server->run();
	}

	/**
	 * @param \Psr\Log\LoggerInterface $logger
	 */
	public function setLogger($logger)
	{
		$this->logger = $logger;
	}

	/**
	 * @return \Psr\Log\LoggerInterface
	 */
	public function getLogger()
	{
		return $this->logger;
	}

	public function run()
	{
		$this->logger->info('Start server');
		$this->server->run();
	}
}
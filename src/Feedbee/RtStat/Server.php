<?php

namespace Feedbee\RtStat;

use Psr\Log\LoggerInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;
use React\EventLoop\Factory as EventLoopFactory;
use React\Socket\Server as SocketServer;

class Server
{
	const TYPE_RAW = 'raw';
	const TYPE_WEB_SOCKET = 'web-socket';

	/**
	 * @var \Ratchet\Server\IoServer
	 */
	private $server;

	private $logger;

	public function __construct(LoggerInterface $logger = null, $port = 8000, $address = '0.0.0.0', $authToken,
								$maxClients = 1000, $type = self::TYPE_RAW)
	{
		$this->logger = $logger;
		$logger && $this->logger->info("Server config: {$address}:{$port} ({$type}), "
			. ($maxClients > 0 ? "clients limit $maxClients" : 'clients unlimited'));

		$loop = EventLoopFactory::create();

		$socket = new SocketServer($loop);
		$socket->listen($port, $address);

		$messagingApp = new MessagingApplication($loop, $authToken, $logger, $maxClients);

		if ($type == self::TYPE_WEB_SOCKET) {
			$messagingApp = new HttpServer(new WsServer($messagingApp));
		}

		$server = new IoServer(
			$messagingApp,
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
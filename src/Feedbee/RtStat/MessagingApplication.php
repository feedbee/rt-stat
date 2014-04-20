<?php

namespace Feedbee\RtStat;

use Feedbee\RtStat\Plugins\CpuUsage;
use Feedbee\RtStat\Plugins\MemInfo;
use Feedbee\RtStat\Plugins\Processes;
use Feedbee\RtStat\Plugins\Uptime;
use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use React\EventLoop\LoopInterface;

class MessagingApplication implements MessageComponentInterface
{
	/**
	 * @var Worker[]
	 */
	private $workers;

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	private $logger;

	/**
	 * @var \React\EventLoop\LoopInterface
	 */
	private $loop;

	/**
	 * @var null|string
	 */
	private $authToken;

	/**
	 * @var int
	 */
	private $maxClients;

	public function __construct(LoopInterface $loop, $authToken = null, LoggerInterface $logger = null, $maxClients = 1000)
	{
		$this->loop = $loop;
		$this->logger = $logger;
		$this->authToken = $authToken;
		$this->maxClients = $maxClients;
	}

	public function onOpen(ConnectionInterface $connection)
	{
		$this->logger && $this->logger->debug('MessageComponent::onOpen');

		if ($this->maxClients > 0 && count($this->workers) >= $this->maxClients) {
			$this->logger && $this->logger->notice(
				"MessageComponent: client maximum ({$this->maxClients}) reached, new connection terminated");
			$connection->close();
			return;
		}

		$worker = new Worker($connection, $this->loop, $this->getDefaultPlugins(), $this->authToken, $this->logger);
		$this->workers[spl_object_hash($connection)] = $worker;
		$worker->open();
	}

	public function onMessage(ConnectionInterface $connection, $message)
	{
		$this->logger && $this->logger->debug("MessageComponent::onMessage: {$message}");

		$this->workers[spl_object_hash($connection)]->processMessage($message);
	}

	public function onClose(ConnectionInterface $connection)
	{
		$this->logger && $this->logger->debug('MessageComponent::onClose');

		$hash = spl_object_hash($connection);
		if (isset($this->workers[$hash])) {
			$this->workers[$hash]->stop();
			unset($this->workers[spl_object_hash($connection)]);
		}
	}

	public function onError(ConnectionInterface $connection, \Exception $e)
	{
		$this->logger && $this->logger->debug("MessageComponent::onError: {$e->getMessage()}");

		$connection->close();
	}

	private function getDefaultPlugins()
	{
		return [
			new CpuUsage,
			new MemInfo,
			new Processes,
			new Uptime,
		];
	}
}
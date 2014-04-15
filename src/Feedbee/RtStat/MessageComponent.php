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

class MessageComponent implements MessageComponentInterface
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

	public function __construct(LoopInterface $loop, $authToken = null, LoggerInterface $logger = null)
	{
		$this->loop = $loop;
		$this->logger = $logger;
		$this->authToken = $authToken;
	}

	public function onOpen(ConnectionInterface $connection)
	{
		$this->logger && $this->logger->debug('MessageComponent::onOpen');

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

		$this->workers[spl_object_hash($connection)]->stop();
	}

	public function onError(ConnectionInterface $connection, \Exception $e)
	{
		$this->logger && $this->logger->debug("MessageComponent::onError: {$e->getMessage()}");

		$connection->close();
		unset($this->workers[spl_object_hash($connection)]);
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
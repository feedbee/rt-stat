<?php

namespace Feedbee\RtStat;

use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class MessageComponent implements MessageComponentInterface
{
	/**
	 * @var \SplObjectStorage
	 */
	private $clients;

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	private $logger;

	public function __construct(LoggerInterface $logger = null)
	{
		$this->clients = new \SplObjectStorage;
		$this->logger = $logger;
	}

	public function onOpen(ConnectionInterface $conn)
	{
		$this->logger && $this->logger->debug('MessageComponent::onOpen');
	}

	public function onMessage(ConnectionInterface $from, $msg)
	{
		$this->logger && $this->logger->debug('MessageComponent::onMessage');
	}

	public function onClose(ConnectionInterface $conn)
	{
		$this->logger && $this->logger->debug('MessageComponent::onClose');
	}

	public function onError(ConnectionInterface $conn, \Exception $e)
	{
		$this->logger && $this->logger->debug('MessageComponent::onError');
	}
}
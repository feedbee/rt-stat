<?php

namespace Feedbee\RtStat;

use Feedbee\RtStat\Plugins\PluginInterface;
use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;

class Worker
{
	/**
	 * @var ConnectionInterface
	 */
	private $connection;

	/**
	 * @var \React\EventLoop\LoopInterface
	 */
	private $loop;

	/**
	 * @var PluginInterface[]
	 */
	private $plugins;

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	private $logger;

	/**
	 * @var int
	 */
	private $interval;

	/**
	 * @var TimerInterface
	 */
	private $pushTimer;

	public function __construct(ConnectionInterface $connection, LoopInterface $loop, array $plugins, LoggerInterface $logger = null)
	{
		$this->connection = $connection;
		$this->loop = $loop;
		$this->plugins = $plugins;
		$this->logger = $logger;
	}

	public function open()
	{
		$this->logger->debug('Worker::opened');
		$this->send("Welcome::" . Application::NAME . " v." . Application::VERSION);
	}

	public function push()
	{
		$this->logger->debug('Worker::push');

		$data = [];

		foreach ($this->plugins as $plugin) {
			$data[$plugin->getName()] = $plugin->getData();
		}

		$this->send('Push::' . json_encode($data, JSON_UNESCAPED_UNICODE));
	}

	/**
	 * @param string $message
	 */
	public function processMessage($message)
	{
		$this->logger->debug("Worker::processMessage: $message");

		if (strlen($message) < 1) {
			$this->sendError("Unknown message");
			return;
		}

		$parts = explode('::', $message);
		$command = $parts[0];
		switch ($command) {
			case 'start':
				if ($this->pushTimer) {
					$this->sendError("Already started");
					return;
				}
				if (!$this->interval) {
					$this->sendError("Interval is not set");
					return;
				}
				$this->start();
				break;
			case 'stop':
				if (!$this->pushTimer) {
					$this->sendError("Not started");
					return;
				}
				$this->stop();
				break;
			case 'interval':
				if (count($parts) < 2) {
					$this->sendError("Interval message has 1 required parameter: int interval (not set)");
					return;
				}
				$arg = (float)$parts[1];
				if ($parts[1] != $arg || $arg == 0) {
					$this->sendError("Interval message has 1 required parameter: int interval (not positive float)");
					return;
				}
				$this->setInterval($arg);
				break;
			case 'quit':
			case 'exit':
				$this->quit();
				break;
			default:
				$this->sendError("Unknown message");
		}
	}

	public function setInterval($interval)
	{
		$this->logger->debug('Worker::setInterval');

		$this->interval = $interval;
		if ($this->pushTimer) {
			$this->stop();
			$this->start();
		}
	}

	public function start()
	{
		$this->logger->debug('Worker::start');

		$this->pushTimer = $this->loop->addPeriodicTimer($this->interval, [$this, 'push']);
	}

	public function stop()
	{
		$this->logger->debug('Worker::stop');

		if ($this->pushTimer) {
			$this->loop->cancelTimer($this->pushTimer);
			$this->pushTimer = null;
		}
	}

	public function quit()
	{
		$this->logger->debug('Worker::quit');

		$this->connection->close();
	}

	private function send($text)
	{
		$this->connection->send($text . "\n");
	}

	private function sendError($text)
	{
		$this->logger->debug("Worker::sendError {$text}");

		$this->send("Error::" . $text);
	}
}
<?php

namespace Feedbee\RtStat;

use Feedbee\RtStat\Plugins\PluginInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;

class Worker
{
	/**
	 * @var ConnectionInterface
	 */
	private $connection;

	private $loop;

	/**
	 * @var PluginInterface[]
	 */
	private $plugins;

	/**
	 * @var int
	 */
	private $interval;

	/**
	 * @var TimerInterface
	 */
	private $pushTimer;

	public function __construct(ConnectionInterface $connection, LoopInterface $loop, array $plugins)
	{
		$this->connection = $connection;
		$this->loop = $loop;
		$this->plugins = $plugins;
	}

	public function open()
	{
		$this->connection->send("Welcome::" . Application::NAME . " v." . Application::VERSION);
	}

	public function push()
	{
		$data = [];

		foreach ($this->plugins as $plugin) {
			$data[$plugin->getName()] = $plugin->getData();
		}

		$this->connection->send('Push::' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
	}

	/**
	 * @param string $message
	 */
	public function processMessage($message)
	{
		if (strlen($message) < 1) {
			$this->sendError("Unknown message");
			return;
		}

		$parts = explode('::', $message);
		$command = $parts[0];
		switch ($command) {
			case 'start':
				$this->start();
				break;
			case 'stop':
				$this->stop();
				break;
			case 'interval':
				if (count($parts) < 2) {
					$this->sendError("Interval message has 1 required parameter: int interval (not set)");
					return;
				}
				$arg = (int)$parts[1];
				if ($parts[1] != $arg) {
					$this->sendError("Interval message has 1 required parameter: int interval (not int)");
					return;
				}
				$this->setInterval($arg);
				break;
			default:
				$this->sendError("Unknown message");
		}
	}

	public function setInterval($interval)
	{
		$this->interval = $interval;
		if ($this->pushTimer) {
			$this->stop();
			$this->start();
		}
	}

	public function start()
	{
		if ($this->pushTimer) {
			$this->stop();
		}
		$this->pushTimer = $this->loop->addPeriodicTimer($this->interval, [$this, 'push']);
	}

	public function stop()
	{
		$this->loop->cancelTimer($this->pushTimer);
		$this->pushTimer = null;
	}

	private function sendError($text)
	{
		$this->connection->send("Error::" . $text);
	}
}
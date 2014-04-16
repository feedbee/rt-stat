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

	/**
	 * @var bool
	 */
	private $authorized = false;

	/**
	 * @var null|string
	 */
	private $authToken;

	public function __construct(ConnectionInterface $connection, LoopInterface $loop, array $plugins, $authToken = null,
								LoggerInterface $logger = null)
	{
		$this->connection = $connection;
		$this->loop = $loop;
		$this->plugins = $plugins;
		$this->authToken = $authToken;
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

		$parts = explode('::', trim($message));
		$command = strtolower($parts[0]);

		if (!is_null($this->authToken) && !$this->authorized
			&& in_array($command, array('start', 'stop', 'interval')))
		{
			$this->sendError("Not authorized");
			return;
		}

		$args = [];
		if (count($parts) > 1) {
			for ($i = 1; $i < count($parts); $i++) {
				$part = $parts[$i];
				$args[$part];
				while (count($part) > 0 && substr($part, strlen($part) - 1) == '\\'
					&& (substr($part, strlen($part) - 2, 1) != '\\' || count($part) < 2))
				{
					$args[] = '::' . ($i + 1 < count($parts) ? $parts[$i + 1] : '');
				}
			}
		}

		switch ($command) {
			case 'auth':
				if (count($args) < 1) {
					$this->sendError("Auth message has 1 required parameter: string token (not set)");
					return;
				}
				$this->authorized = (!is_null($this->authToken) && $this->authToken == $args[0]);
				if (!$this->authorized) {
					$this->sendError("Auth failed");
					return;
				}
				break;
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
				if (count($args) < 1) {
					$this->sendError("Interval message has 1 required parameter: int interval (not set)");
					return;
				}
				$arg = (float)$args[0];
				if ($args[0] != $arg || $arg == 0) {
					$this->sendError("Interval message has 1 required parameter: int interval (not positive float)");
					return;
				}
				if ($arg < 0.5) {
					$this->sendError("Interval message has must be equal or greater than 0.5");
					return;
				}
				$this->setInterval($arg);
				break;
			case 'version':
				$this->send("Version::" . Application::VERSION);
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

	private function sendCommand($command, array $args)
	{
		$argsEscaped = array_map(function ($value) {
			return str_replace('::', '\::', $value);
		}, $args);
		$text = implode('::', $argsEscaped);

		$this->logger->debug("Worker::sendCommand {$command}::{$text}");

		$this->send("{$command}::{$text}");
	}

	private function sendError($text)
	{
		$this->logger->debug("Worker::sendError {$text}");

		$this->sendCommand('Error', [$text]);
	}
}
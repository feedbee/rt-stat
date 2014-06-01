<?php

namespace Feedbee\RtStat;

use Feedbee\RtStat\Plugins\PluginInterface;
use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;

class Worker
{
	const PING_INTERVAL_SEC = 30;
	const PONG_TIMEOUT_SEC = 60;

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
	 * @var int
	 */
	private $lastPongTime;

	/**
	 * @var TimerInterface
	 */
	private $pongTimer;

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
		$this->sendCommand("Welcome", [Application::NAME . " v." . Application::VERSION, 'features:ping']);
		$this->pushTimer = $this->loop->addPeriodicTimer(self::PING_INTERVAL_SEC, [$this, 'ping']);
	}

	public function push()
	{
		$this->logger->debug('Worker::push');

		$data = [];

		foreach ($this->plugins as $plugin) {
			$data[$plugin->getName()] = $plugin->getData();
		}

		$this->sendCommand('Push', [json_encode($data, JSON_UNESCAPED_UNICODE)]);
	}

	public function ping()
	{
		if ($this->lastPongTime < round(microtime(true), 3) - self::PONG_TIMEOUT_SEC) {
			$this->logger->debug('Worker::pong timeout');
			$this->connection->close();
			return;
		}

		$this->logger->debug('Worker::ping');
		$this->sendCommand('Ping');
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

		$messageParsed = Protocol::parseMessage($message);

		$this->executeCommand($messageParsed['command'], $messageParsed['arguments']);
	}

	private function executeCommand($command, $arguments)
	{
		if (!is_null($this->authToken) && !$this->authorized
			&& in_array($command, array('start', 'stop', 'interval')))
		{
			$this->sendError("Not authorized");
			return;
		}

		switch ($command) {
			case "pong":
				$this->lastPongTime = round(microtime(true), 3);
				break;

			case 'auth':
				if (count($arguments) < 1) {
					$this->sendError("Auth command has 1 required parameter: string token (not set)");
					return;
				}
				$this->authorized = (!is_null($this->authToken) && $this->authToken == $arguments[0]);
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
				if (count($arguments) < 1) {
					$this->sendError("Interval command has 1 required parameter: int interval (not set)");
					return;
				}
				$arg = (float)$arguments[0];
				if ($arguments[0] != $arg || $arg == 0) {
					$this->sendError("Interval command has 1 required parameter: int interval (not positive float)");
					return;
				}
				if ($arg < 0.5) {
					$this->sendError("Interval command has must be equal or greater than 0.5");
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
				$this->sendError("Unknown command");
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

		if ($this->pongTimer) {
			$this->loop->cancelTimer($this->pongTimer);
			$this->pongTimer = null;
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

	private function sendCommand($command, array $arguments = array())
	{
		$message = Protocol::createMessage($command, $arguments);

		$this->logger->debug("Worker::sendCommand {$message}");

		$this->send($message);
	}

	private function sendError($text)
	{
		$this->logger->debug("Worker::sendError {$text}");

		$this->sendCommand('Error', [$text]);
	}
}
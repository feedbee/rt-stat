<?php

namespace Feedbee\RtStat\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Feedbee\RtStat\Server;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ServerCommand extends Command
{
	protected function configure()
	{
		$this
			->setName('server')
			->setDescription('Start server')

			->addOption(
				'interface',
				'i',
				InputOption::VALUE_REQUIRED,
				'Select network interface to bind. 0.0.0.0 will cover all available interfaces (this is the default).'
			)

			->addOption(
				'port',
				'p',
				InputOption::VALUE_REQUIRED,
				'Select TCP-port to bind. Default is 8000.'
			)

			->addOption(
				'web-sockets',
				'w',
				InputOption::VALUE_NONE,
				'Set to work as WebSocket server. Default is simple raw socket server'
			);;
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$interface = '0.0.0.0';
		$port = 8000;
		$type = Server::TYPE_RAW;
		if ($i = $input->getOption('interface')) {
			$interface = $i;
		}
		if ($p = $input->getOption('port')) {
			$port = $p;
		}
		if ($input->getOption('web-sockets')) {
			$type = Server::TYPE_WEB_SOCKET;
		}

		$logger = null;
		if ($output->getVerbosity() != OutputInterface::VERBOSITY_QUIET) {
			$logger = new Logger('Rt-Stat');
			$level = Logger::NOTICE;
			switch ($output->getVerbosity()) {
				case OutputInterface::VERBOSITY_DEBUG:
					$level = Logger::DEBUG;
					break;
				case OutputInterface::VERBOSITY_VERY_VERBOSE:
					$level = Logger::INFO;
					break;
			}
			$logger->pushHandler(new StreamHandler('php://stderr', $level));
		}

		$server = new Server($logger, $port, $interface, $type);
		$server->run();
	}
}
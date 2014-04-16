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
				'auth-token',
				'a',
				InputOption::VALUE_REQUIRED,
				'Enter auth token. If token entered, all command requires authorization. Default is no authorization.'
			)

			->addOption(
				'max-clients',
				'c',
				InputOption::VALUE_REQUIRED,
				'Clients count limit (integer >0 or 0 for unlimited count). If token entered, all command requires authorization. Default is 10.'
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
		$authToken = null;
		$maxClients = 10;
		if (null !== ($i = $input->getOption('interface'))) {
			$interface = $i;
		}
		if (null !== ($p = $input->getOption('port'))) {
			if (!ctype_digit($p))
			{
				$output->writeln('<error>Error: port number must be positive integer</error>');
				exit(1);
			}
			if ($p < 1 || $p > 65535) {
				$output->writeln('<error>Error: port number must be between 1 and 65535</error>');
				exit(1);
			}
			$port = (int)$p;
		}
		if (true === ($input->getOption('web-sockets'))) {
			$type = Server::TYPE_WEB_SOCKET;
		}
		if (null !== ($a = $input->getOption('auth-token'))) {
			$authToken = $a;
		}
		if (null !== ($c = $input->getOption('max-clients'))) {
			if (!ctype_digit($c))
			{
				$output->writeln('<error>Error: clients count must be integer equal or greater then 0</error>');
				exit(1);
			}
			$maxClients = (int)$c;
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

		$server = new Server($logger, $port, $interface, $authToken, $maxClients, $type);
		$server->run();
	}
}
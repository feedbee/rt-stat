<?php

namespace Feedbee\RtStat;

class Protocol
{
	static public function parseMessage($message)
	{
		$parts = explode('::', trim($message), 2);
		$command = strtolower($parts[0]);

		$arguments = [];
		if (count($parts) > 1) {
			$arguments = static::parseArgs($parts[1]);
		}

		return [
			'command' => $command,
			'arguments' => $arguments,
		];
	}

	static private function parseArgs($argsStr)
	{
		$length = strlen($argsStr);
		if ($length < 1) {
			return [];
		}

		$escapeMode = false;
		$argsStack = [''];
		$lastArg =& $argsStack[0];
		for ($i = 0; $i < $length; $i++) {
			$char = substr($argsStr, $i, 1);

			if ($escapeMode) {
				$escapeMode = false;
				if ($char == 'n') {
					$lastArg .= "\n";
				} else {
					$lastArg .= $char;
				}
			} else {
				if ($char == '\\') {
					$escapeMode = true;
				} else if ($char == ':' && $i < $length - 1 && substr($argsStr, $i + 1, 1) == ':') {
					$argsStack[] = '';
					$lastArg =& $argsStack[count($argsStack) - 1];
					$i++;
				} else {
					$lastArg .= $char;
				}
			}
		}

		return $argsStack;
	}

	static public function createMessage($command, array $arguments)
	{
		$argsEscaped = array_map(function ($value) {
			return str_replace(["\n", '::'], ['\\n', '\::'], str_replace('\\\\', '\\', $value));
		}, $arguments);
		$text = implode('::', $argsEscaped);

		return "{$command}::{$text}";
	}
}
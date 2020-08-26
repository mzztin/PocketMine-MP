<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine;

use function get_class;
use function is_string;
use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;
use pocketmine\plugin\Plugin;
use pocketmine\utils\Utils;

/**
 * Alows different modules to expose APIs using types.
 */
final class ApiMap{
	/**
	 * @var object[][]|Plugin[][]|null[][]
	 * @phpstan-var array<class-string, array{plugin: Plugin|null, impl: object, default: bool}>
	 */
	private $apiMap = [];

	/**
	 * @see Server#provideApi
	 *
	 * @template T of object
	 * @param string $interface
	 * @phpstan-param class-string<T> $interface
	 * @param Plugin|null $plugin
	 * @param object $impl
	 * @phpstan-param T $impl
	 * @param bool $default
	 *
	 * @throws InvalidArgumentException if $impl is not an instance of $interface
	 * @throws RuntimeException if two non-default APIs are provided for the same interface
	 */
	public function provideApi(string $interface, ?Plugin $plugin, object $impl, bool $default = false) : void{
		if(!($impl instanceof $interface)){
			$class = get_class($impl);
			throw new InvalidArgumentException("\$impl is an instance of $class, which does not extend/implement $interface");
		}

		if(isset($this->apiMap[$interface])){
			if(!$this->apiMap[$interface]["default"] && !$default) {
				// two non-default implementations
				$otherPlugin = $this->apiMap[$interface]["plugin"];
				$otherPluginName = $otherPlugin !== null ? $otherPlugin->getName() : "PocketMine";
				$pluginName = $plugin !== null ? $plugin->getName() : "PocketMine";

				$class = new ReflectionClass($interface);
				$doc = $class->getDocComment();
				$tags = is_string($doc) ? Utils::parseDocComment($doc) : [];
				$purpose = $tags["purpose"] ?? "an implementation of $interface";

				// TODO switch to user-friendly exceptions
				throw new RuntimeException("Multiple plugins ($otherPluginName, $pluginName) are providing $purpose. Please disable one of them or check configuration.");
			}

			if($default) {
				return;
			}
		}

		$this->apiMap[$interface] = [
			"plugin" => $plugin,
			"impl" => $impl,
			"default" => $default,
		];
	}

	/**
	 * @see Server#getApi
	 *
	 * @template T
	 * @param string $interface
	 * @phpstan-param class-string<T> $interface
	 * @param bool $default
	 * @return object|null
	 * @phpstan-return T|null
	 */
	public function getApi(string $interface, bool &$default = false) : ?object {
		if(!isset($this->apiMap[$interface])) {
			return null;
		}
		$default = $this->apiMap[$interface]["default"];
		return $this->apiMap[$interface]["impl"];
	}
}


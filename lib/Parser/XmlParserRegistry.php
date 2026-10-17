<?php
declare(strict_types=1);

namespace OCA\Xmlviewer\Parser;

final class XmlParserRegistry {
	private static ?array $registeredParsers = null;

	private function parsers(): array {
		if (self::$registeredParsers !== null) {
			return self::$registeredParsers;
		}

		$parsers = [];
		
		foreach (glob(__DIR__ . '/*/Parser.php') ?: [] as $file) {
			$parser = require_once $file;
			if ($parser instanceof XmlParserInterface) { $parsers[] = $parser; }
		}
		
		return self::$registeredParsers = $parsers;
	}
	
	public function findParser(\SimpleXMLElement $xml): ?XmlParserInterface {
		$rootNode = $xml->getName();
		
		foreach ($this->parsers() as $parser) {
			if ($parser->supportsRoot($rootNode)) { return $parser; }
		}
		
		return null;
	}
}

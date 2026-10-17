<?php
declare(strict_types=1);

namespace OCA\Xmlviewer\Parser;

interface XmlParserInterface {
	public function supportsRoot(string $rootNode): bool;
	public function parse(\SimpleXMLElement $xml, string $source): ?array;
	public function getTemplatePath(): string;
}

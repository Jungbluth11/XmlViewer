<?php
return [
	'routes' => [
		['name' => 'view#index', 'url' => '/view/{fileId}', 'verb' => 'GET'],
		['name' => 'view#download', 'url' => '/download/{fileId}', 'verb' => 'GET'],
		['name' => 'view#parserStyle', 'url' => '/parser-style/{parser}', 'verb' => 'GET'],
		['name' => 'view#parserScript', 'url' => '/parser-script/{parser}', 'verb' => 'GET'],
		['name' => 'publicview#root', 'url' => '/public/{token}', 'verb' => 'GET'],
		['name' => 'publicview#index', 'url' => '/public/{token}/{fileId}', 'verb' => 'GET'],
		['name' => 'publicview#download', 'url' => '/public-download/{token}/{fileId}', 'verb' => 'GET'],
	],
];

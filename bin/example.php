<?php

use alcamo\input_stream\StringInputStream;

include $_composer_autoload_path ?? __DIR__ . '/../vendor/autoload.php';

$text = <<<EOT
foo=bar ; example assignment

EOT;

$stream = new StringInputStream($text);

echo "Left: " . $stream->extractUntil('=') . PHP_EOL;

echo "Equal sign: " . $stream->extract() . PHP_EOL;

echo "Right: " . $stream->extractRegexp('/[a-z]+/') . PHP_EOL;

echo "Comment: " . $stream->extractWsAndComments() . PHP_EOL;

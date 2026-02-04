# Usage example

~~~
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
~~~

This example is contained in this package as a file in the `bin`
directory. It will output

~~~
Left: foo
Equal sign: =
Right: bar
Comment:  ; example assignment

~~~

# Overview

This package provides input streams vaguely inspired by C++
istream. In addition to C++-like methods such as `extract()`,
`isGood()`, `peek()`, `putback()`, they provide convenenience methods
such as `extractUntil()` or `extractRegexp()`.

The class `StringInputStream` is for strings where one byte
corresponds to one character, auch as ASCII or ISO-8859, while
`MbStringInputStream` supports multibyte character sets such as
UTF-8. The former should be preferred if applicable because it is
faster.

See the doxygen documentation for details.


Tenkawa PHP Language Server
===========================

Tenkawa is a [language server][lsp] for PHP, with powerful static analysis
and type inference engine borrowed without asking from [PHPStan][phpstan].

Still experimental, but should be usable. Any bug reports, feature requests,
suggestions, questions are welcome.

[lsp]: https://microsoft.github.io/language-server-protocol/
[phpstan]: https://github.com/phpstan/phpstan

Installation
------------

Requires PHP >= 7.0.

For Visual Studio Code extension, see [here][vsix].

[vsix]: https://github.com/tsufeki/vscode-tenkawa-php

Either with [`composer`][composer] `create-project` (`~` directory
is an example):
```
$ cd ~
$ composer create-project --no-dev --keep-vcs \
    tsufeki/tenkawa-php-language-server tenkawa/
```

[composer]: https://getcomposer.org/

Or by cloning the repo:
```
$ cd ~
$ git clone https://github.com/tsufeki/tenkawa-php-language-server.git tenkawa/
$ cd tenkawa/
$ composer install --no-dev
$ cd ..
```

Now configure your client to start the server with this command to use stdio:
```
php ~/tenkawa/bin/tenkawa.php
```

Or to connect to a TCP socket:
```
php ~/tenkawa/bin/tenkawa.php --socket=tcp://127.0.0.1:12345
```

Features
--------

* ✔ Autocompletion
    * ✔ Classes/functions (also with automatic import and within doc comments)
    * ✔ Class members
    * ✔ Local variables
* ✔ Diagnostics
    * ✔ Static analysis with [PHPStan][phpstan]
      (see [Known issues](#known-issues))
* ✔ Go to definition
* ✔ Hover info
* ✔ Document symbols
* ✔ Code actions
    * ✔ Import class/function
    * ✘ More to come...
* ✔ Multi-root workspace

Unimplemented (yet?):

* ✘ Go to implementation
* ✘ Go to type definition
* ✘ Signature help
* ✘ Workspace symbols
* ✘ References
* ✘ Document highlight
* ✘ Code lens
* ✘ Formatting
    * ✘ document
    * ✘ range
    * ✘ on type
* ✘ Rename
* ✘ Dynamic configuration

Known issues
------------

* Information about standard library and extensions is taken from
  [PhpStorm stubs][stubs], which aren't always perfect and sometimes don't
  work well with our static analysis. This is the main reason why the real,
  standalone PHPStan gives different results than our server. This may improve
  with future PHPStan versions.
* Many features don't work inside anonymous classes and traits. This is
  caused by PHPStan's design and it should be possible to fix this when next
  version(s) land.
* Refactors are not 100% bullet-proof. More comprehensive implementation needs
  PHP Parser 4 (and its support in PHPStan).
* Filtering of big lists (i.e. completions) is left entirely to the client,
  which must be able to withstand it performance-wise.
* Performance & long indexing times.

[stubs]: https://github.com/JetBrains/phpstorm-stubs

Command line options
--------------------

* `--socket=<socket>` - connect to a socket instead of communicating through
  STDIO. Allowed format: `tcp://127.0.0.1:12345` or `unix:///path/to/socket`.
* `--log-stderr` - log to stderr.
* `--log-file=<file>` - log to the given file.
* `--log-client` - log using `window/logMessage` protocol method.
* `--log-level=<level>` - log only messages of the given level and up.
  `<level>` can be one of `emergency`, `alert`, `critical`, `error`,
  `warning`, `notice`, `info`, `debug`. Defaults to `info`.

Configuration
-------------

Currently, the only way to pass configuration options to the server is through
`initializationOptions` parameter of `initialize` protocol method. Recognized
options:

```js
{
  "tenkawaphp": {
    "diagnostics": {
      "phpstan": {
        "enabled": true
      }
    },
    "completion": {
      "autoImport": true
    }
  }
}
```

Licence
-------

Copyright (c) 2017 tsufeki

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

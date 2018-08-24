
Tenkawa PHP Language Server
===========================

![Tenkawa](images/tenkawa-logo.png)

Tenkawa is a [language server][lsp] for PHP, with powerful static analysis
and type inference engine borrowed without asking from [PHPStan][phpstan].

Still experimental, but should be usable. Any bug reports, feature requests,
suggestions, questions are welcome.

[lsp]: https://microsoft.github.io/language-server-protocol/
[phpstan]: https://github.com/phpstan/phpstan

Installation
------------

Requires PHP >= 7.1 with pdo_sqlite extension.

For Visual Studio Code extension, see [here][vsix].

[vsix]: https://github.com/tsufeki/vscode-tenkawa-php

Either with [`composer`][composer] `create-project` (`~` directory
is an example):

```sh
$ cd ~
$ composer create-project --no-dev --keep-vcs \
    tsufeki/tenkawa-php-language-server tenkawa/
```

[composer]: https://getcomposer.org/

Or by cloning the repo:

```sh
$ cd ~
$ git clone https://github.com/tsufeki/tenkawa-php-language-server.git tenkawa/
$ cd tenkawa/
$ composer install --no-dev
$ cd ..
```

Build index of the standard library:

```sh
$ php ~/tenkawa/bin/tenkawa.php --build-index --log-stderr
```

Now configure your client to start the server with this command to use stdio:

```sh
php ~/tenkawa/bin/tenkawa.php
```

Or to connect to a TCP socket:

```sh
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
* ✔ References
* ✔ Document symbols
* ✔ Workspace symbols
  * ✔ Classes/functions/consts
  * ✘ Class members
* ✔ Code actions
  * ✔ Import class/function
  * ✘ More to come...
* ✔ Multi-root workspace

Unimplemented (yet?):

* ✘ Go to implementation
* ✘ Go to type definition
* ✘ Signature help
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

* Many features don't work inside traits. This is caused by PHPStan's design.
* Refactors are not 100% bullet-proof. More comprehensive implementation needs
  PHP Parser 4 (and its support in PHPStan).
* Filtering of big lists (i.e. completions) is left entirely to the client,
  which must be able to withstand it performance-wise.
* Performance & long indexing times.

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
* `--build-index` - build standard library index instead of starting the server.

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
        // Enable PHPStan diagnostics.
        "enabled": true
      }
    },
    "completion": {
      // Enable automatic import (use) of completed classes.
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

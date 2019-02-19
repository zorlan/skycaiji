Websocket Client for PHP
========================

[![Build Status](https://travis-ci.org/Textalk/websocket-php.png)](https://travis-ci.org/Textalk/websocket-php)
[![Coverage Status](https://coveralls.io/repos/Textalk/websocket-php/badge.png)](https://coveralls.io/r/Textalk/websocket-php)

This package mainly contains a WebSocket client for PHP.

I made it because the state of other WebSocket clients I could found was either very poor
(sometimes failing on large frames) or had huge dependencies (React…).

The Client should be good.  If it isn't, tell me!

The Server there because much of the code would be identical in writing a server, and because it is
used for the tests.  To be really useful though, there should be a Connection-class returned from a
new Connection, and the Server-class only handling the handshake.  Then you could hold a full array
of Connections and check them periodically for new data, send something to them all or fork off a
process handling one connection.  But, I have no use for that right now.  (Actually, I would
suggest a language with better asynchronous handling than PHP for that.)

Installing
----------

Preferred way to install is with [Composer](https://getcomposer.org/).

Just add

    "require": {
      "textalk/websocket": "1.0.*"
    }

in your projects composer.json.

Client usage:
-------------
```php
require('vendor/autoload.php');

use WebSocket\Client;

$client = new Client("ws://echo.websocket.org/");
$client->send("Hello WebSocket.org!");

echo $client->receive(); // Will output 'Hello WebSocket.org!'
```

Developer install
-----------------

Development depends on php, php-curl and php-xdebug.

```bash
# Will get composer, install dependencies and run tests
make test
```


License ([ISC](http://en.wikipedia.org/wiki/ISC_license))
---------------------------------------------------------

Copyright (C) 2014, 2015 Textalk
Copyright (C) 2015 Patrick McCarren - added payload fragmentation for huge payloads
Copyright (C) 2015 Ignas Bernotas - added stream context options

Websocket PHP is free software: Permission to use, copy, modify, and/or distribute this software
for any purpose with or without fee is hereby granted, provided that the above copyright notice and
this permission notice appear in all copies.

THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS
SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT,
NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF
THIS SOFTWARE.

See COPYING.


Changelog
---------

1.2.0

 * Adding stream context options (to set e.g. SSL `allow_self_signed`).

1.1.2

 * Fixed error message on broken frame.

1.1.1

 * Adding license information.

1.1.0

 * Supporting huge payloads.

1.0.3

 * Bugfix: Correcting address in error-message

1.0.2

 * Bugfix: Add port in request-header.

1.0.1

 * Fixing a bug from empty payloads.

1.0.0

 * Release as production ready.
 * Adding option to set/override headers.
 * Supporting basic authentication from user:pass in URL.

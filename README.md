# Composer class discovery

Provides composer discovery with class discovery.

## Install

The recommended way to install this library is [through composer](http://getcomposer.org).

```sh
composer require xylemical/composer-class-discovery
```

## Usage

Getting the sources from the class discovery can be done with the following:

```php

use Xylemical\Composer\ClassDiscovery\ClassDiscoveryStorage;

$storage = new ClassDiscoveryStorage();
$sources = $storage->get('Interface/or/Trait/or/Class/Name');
```

## License

MIT, see LICENSE.

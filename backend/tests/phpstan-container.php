<?php

// Included by phpstan.dist.neon. PHPStan's Symfony extension reads the debug container's XML dump, but the dev stack
// and the tests run without debug (docker-compose.yml, phpunit.dist.xml): (re)build the dump before the analysis.
$root = dirname(__DIR__);
passthru('APP_ENV=test APP_DEBUG=1 php '.escapeshellarg($root.'/bin/console').' cache:warmup --env=test -q', $code);
if (0 !== $code) {
    fwrite(\STDERR, "Could not warm the test container for PHPStan.\n");
}

return ['parameters' => ['symfony' => ['containerXmlPath' => $root.'/var/cache/test/App_KernelTestDebugContainer.xml']]];

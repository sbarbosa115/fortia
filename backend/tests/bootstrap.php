<?php

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

/*
 * Tests run without debug-mode freshness checks (phpunit.dist.xml sets APP_DEBUG=0): on Docker Desktop, checking
 * every source file through the bind mount on each kernel boot cost about a second per test. Instead, the test
 * cache is dropped here, once per run, when anything in src/, config/, templates/ or translations/ changed since it
 * was built.
 */
$root = dirname(__DIR__);
$cache = $root.'/var/cache/test';
$stamp = $cache.'/.built-at';
if (is_dir($cache)) {
    $builtAt = is_file($stamp) ? (int) filemtime($stamp) : 0;
    $stale = 0 === $builtAt;
    foreach (['src', 'config', 'templates', 'translations', 'tests/Support'] as $dir) {
        if ($stale || !is_dir($root.'/'.$dir)) {
            continue;
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getMTime() >= $builtAt) {
                $stale = true;
                break;
            }
        }
    }
    if ($stale) {
        (new Filesystem())->remove($cache);
    }
}
@mkdir($cache, 0777, true);
touch($stamp);

<?php
/**
 * Setup for unittests.
 * @package Tests
 * @author Sean Molenaar <sean@m2mobi.com>
 */

$base = __DIR__ . '/..';

if (file_exists($base . '/vendor/autoload.php') == TRUE)
{
    // Load composer autoloader.
    require_once $base . '/vendor/autoload.php';
}
else
{
    // Load decomposer autoloader.
    require_once $base . '/decomposer.autoload.inc.php';
    autoload_register_psr4_prefix('Resque\Logging', 'lib/');
}

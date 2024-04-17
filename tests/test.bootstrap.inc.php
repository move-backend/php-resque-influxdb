<?php

/**
 * Setup for unittests.
 *
 * SPDX-FileCopyrightText: Copyright 2017 M2mobi B.V., Amsterdam, The Netherlands
 * SPDX-FileCopyrightText: Copyright 2022 Move Agency Group B.V., Zwolle, The Netherlands
 * SPDX-License-Identifier: MIT
 */

$base = __DIR__ . '/..';

if (file_exists($base . '/vendor/autoload.php') == true) {
    // Load composer autoloader.
    require_once $base . '/vendor/autoload.php';
} else {
    // Load decomposer autoloader.
    require_once $base . '/decomposer.autoload.inc.php';
    autoload_register_psr4_prefix('Resque\Logging', 'lib/');
}

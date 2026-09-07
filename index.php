<?php

declare(strict_types=1);

/**
 * Songwunsch -- front controller.
 *
 * Every address below the base path arrives here (.htaccess); this file
 * loads the configuration, builds the container and hands the request to the
 * kernel. What happens then is in four places and nowhere else:
 *
 *   config/routes.php    every address, and the controller behind it
 *   config/access.php    who may open which address
 *   config/services.php  what the application is built from
 *   src/Kernel.php       the order the pieces run in
 *
 * A route's controller is in src/Controller, the page it renders in
 * templates/. Nothing outside src/Http/Response.php writes a header or ends
 * a request.
 */

use Songwunsch\Http\Request;
use Songwunsch\Kernel;

require __DIR__ . '/src/bootstrap.php';

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    render_fatal(
        'Configuration missing',
        'Please copy <code>config.example.php</code> to <code>config.php</code> and enter the database credentials.'
    );
}

/** @var array<string,mixed> $config */
$config = require $configFile;

// The base path must stand before the first address is built. Without a
// value in config.php the BASE_PATH environment variable applies.
if (isset($config['base_path'])) {
    base_path((string) $config['base_path']);
}
// Cache buster for style.css and app.js; without a value there is no ?v=.
asset_version((string) ($config['version'] ?? getenv('APP_VERSION') ?: ''));

$container = require __DIR__ . '/config/services.php';
// The one global handle, for t(), url(), asset() and icon() in the
// templates; see src/bootstrap.php.
app($container);

$request = Request::fromGlobals();
$container->setService(Request::class, $request);

$container->get(Kernel::class)->handle($request)->send();

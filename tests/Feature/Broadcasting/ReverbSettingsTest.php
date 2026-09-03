<?php

use Illuminate\Support\Facades\Config;

/**
 * Reads config/broadcasting.php with these values in the environment, then puts the
 * environment back.
 *
 * It writes $_ENV and $_SERVER as well as putenv(): Laravel's env() reads the server
 * and env constants first, so a putenv() alone is ignored for any key that .env
 * already set, which is most of these.
 *
 * @param  array<string, string|null>  $values
 * @return array<string, mixed>
 */
function broadcastingConfigWith(array $values): array
{
    $original = [];

    foreach ($values as $key => $value) {
        $original[$key] = $_SERVER[$key] ?? null;

        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        } else {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    try {
        return require config_path('broadcasting.php');
    } finally {
        foreach ($original as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}

/**
 * Two addresses, and only one of them can be REVERB_HOST.
 *
 * On one machine they are the same and nobody notices. In Docker the browser reaches
 * a port published on the host and the app reaches the container by name on the
 * network's own port, and pointing both at "localhost" makes the queue worker call
 * itself. ShouldRescue then swallows the failure, so the live table is simply dead
 * and nothing says why. It cost a rebuild to find; this file is so it stays found.
 */
it('publishes to the server address and points the browser at its own', function () {
    // The shape config/broadcasting.php reads, as .env.docker.example sets it.
    $client = config('broadcasting.client');
    $options = config('broadcasting.connections.reverb.options');

    expect($client)->toHaveKeys(['key', 'host', 'port', 'scheme'])
        ->and($options)->toHaveKeys(['host', 'port', 'scheme', 'useTLS']);
});

it('prefers the publish address when one is set', function () {
    $config = broadcastingConfigWith([
        'REVERB_HOST' => 'table.example.com',
        'REVERB_PORT' => '443',
        'REVERB_SCHEME' => 'https',
        'REVERB_PUBLISH_HOST' => 'reverb',
        'REVERB_PUBLISH_PORT' => '8080',
        'REVERB_PUBLISH_SCHEME' => 'http',
    ]);

    expect($config['connections']['reverb']['options']['host'])->toBe('reverb')
        ->and($config['connections']['reverb']['options']['port'])->toBe('8080')
        ->and($config['connections']['reverb']['options']['scheme'])->toBe('http')
        ->and($config['connections']['reverb']['options']['useTLS'])->toBeFalse()
        // The browser keeps the address a browser can actually reach.
        ->and($config['client']['host'])->toBe('table.example.com')
        ->and($config['client']['scheme'])->toBe('https');
});

it('falls back to the one address when only one exists', function () {
    $config = broadcastingConfigWith([
        'REVERB_HOST' => 'localhost',
        'REVERB_PORT' => '8080',
        'REVERB_SCHEME' => 'http',
        'REVERB_PUBLISH_HOST' => null,
        'REVERB_PUBLISH_PORT' => null,
        'REVERB_PUBLISH_SCHEME' => null,
    ]);

    expect($config['connections']['reverb']['options']['host'])->toBe('localhost')
        ->and($config['connections']['reverb']['options']['port'])->toBe('8080')
        ->and($config['connections']['reverb']['options']['scheme'])->toBe('http')
        ->and($config['client']['host'])->toBe('localhost');
});

it('never lets a deploy value reach the bundle', function () {
    // The layout renders the settings and resources/js reads them there. A VITE_
    // prefix would bake one host into the image and break every other one.
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
    $js = implode('', array_map(file_get_contents(...), glob(resource_path('js/*.js')) ?: []));

    expect($layout)->toContain("config('broadcasting.client')")
        ->and($js)->not->toContain('VITE_REVERB')
        ->and($js)->toContain('window.demgem');
});

afterEach(function () {
    Config::set('broadcasting', require config_path('broadcasting.php'));
});

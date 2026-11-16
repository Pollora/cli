<?php

declare(strict_types=1);

/*
 * Test doubles that stand in for global code the package calls directly.
 * They are loaded once, from tests/Pest.php, before anything uses them.
 */

namespace GuzzleHttp {
    use GuzzleHttp\Psr7\Response;
    use Pollora\Cli\Tests\Support\FakeHttp;

    if (! class_exists(Client::class, false)) {
        /**
         * Replaces the real HTTP client: it records the request and answers
         * from FakeHttp, so the tests never touch the network.
         */
        final class Client
        {
            /**
             * @param  array<string, mixed>  $options
             */
            public function get(string $uri, array $options = []): Response
            {
                FakeHttp::$requests[] = [$uri, $options];

                if (FakeHttp::$answer === null || FakeHttp::$answer instanceof \Throwable) {
                    throw FakeHttp::$answer ?? new \RuntimeException('network unreachable');
                }

                return new Response(200, [], FakeHttp::$answer);
            }
        }
    }
}

namespace Pollora\Cli {
    use Pollora\Cli\Tests\Support\Clock;

    // Unqualified time() calls inside Pollora\Cli resolve here first.
    function time(): int
    {
        return Clock::$now ?? \time();
    }
}

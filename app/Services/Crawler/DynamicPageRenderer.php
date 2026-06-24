<?php

namespace App\Services\Crawler;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin, dependency-optional wrapper around Symfony Panther + headless Chrome.
 *
 * Panther is intentionally NOT a hard dependency: this project's PHP runtime is
 * missing ext-zip and the locked Symfony 7 components conflict with older
 * Panther releases. The renderer therefore degrades gracefully and reports
 * itself unavailable until the package is installed:
 *
 *   composer require symfony/panther:^2.2 -W --ignore-platform-req=ext-zip
 *
 * and a Chrome/Chromedriver pair is present on the host.
 */
class DynamicPageRenderer
{
    private const PANTHER_CLIENT = '\\Symfony\\Component\\Panther\\Client';

    public function isAvailable(): bool
    {
        return class_exists(self::PANTHER_CLIENT);
    }

    /**
     * Render a URL with headless Chrome and return the resulting HTML, or null
     * when Panther is unavailable or rendering fails.
     */
    public function render(string $url): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $client = null;

        try {
            $client = $this->makeClient();
            $client->request('GET', $url);

            $waitSeconds = max(0, (int) config('crawler.panther.wait_seconds', 5));

            if ($waitSeconds > 0) {
                sleep($waitSeconds);
            }

            return $client->getPageSource();
        } catch (Throwable $error) {
            Log::warning('Panther dynamic render failed', [
                'url' => $url,
                'message' => $error->getMessage(),
            ]);

            return null;
        } finally {
            if ($client !== null) {
                try {
                    $client->quit();
                } catch (Throwable) {
                    // Browser already gone; nothing to clean up.
                }
            }
        }
    }

    /**
     * @return object Panther client (typed loosely to avoid a hard dependency).
     */
    private function makeClient(): object
    {
        $clientClass = self::PANTHER_CLIENT;

        $arguments = (array) config('crawler.panther.arguments', []);
        $chromeBinary = config('crawler.panther.chrome_binary');
        $driverBinary = config('crawler.panther.chrome_driver_binary');

        $options = [];

        if (is_string($chromeBinary) && $chromeBinary !== '') {
            $options['binary'] = $chromeBinary;
        }

        $managerOptions = [];

        if (is_string($driverBinary) && $driverBinary !== '') {
            $managerOptions['chromedriver_binary'] = $driverBinary;
        }

        return $clientClass::createChromeClient(
            $driverBinary !== '' && is_string($driverBinary) ? $driverBinary : null,
            $arguments,
            $options,
        );
    }
}

<?php

use Symfony\Component\Process\Process;
use Valet\Drivers\LaravelValetDriver;

/**
 * Serves the Testbench workbench through Laravel Herd or Valet after `herd link` in the package root.
 * Development only; excluded from package archives.
 */
class LocalValetDriver extends LaravelValetDriver
{
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
        return is_file($this->publicPath($sitePath) . '/index.php');
    }

    public function beforeLoading(string $sitePath, string $siteName, string $uri): void
    {
        parent::beforeLoading($sitePath, $siteName, $uri);

        // `testbench serve` passes the package root the same way. A constant only lives for this
        // request, while putenv() would leak into other sites served by the shared PHP-FPM worker.
        if (! defined('TESTBENCH_WORKING_PATH')) {
            define('TESTBENCH_WORKING_PATH', realpath($sitePath) ?: $sitePath);
        }
    }

    /** @return string|false */
    public function isStaticFile(string $sitePath, string $siteName, string $uri)
    {
        $staticFilePath = $this->publicPath($sitePath) . $uri;

        return $this->isActualFile($staticFilePath) ? $staticFilePath : false;
    }

    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        return $this->publicPath($sitePath) . '/index.php';
    }

    public function siteInformation(string $sitePath, string $phpBinary): array
    {
        try {
            $process = new Process([$phpBinary, 'vendor/bin/testbench', 'about', '--json'], $sitePath);
            $process->mustRun();

            return json_decode($process->getOutput(), true) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    private function publicPath(string $sitePath): string
    {
        return $sitePath . '/vendor/orchestra/testbench-core/laravel/public';
    }
}

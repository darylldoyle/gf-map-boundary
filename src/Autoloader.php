<?php
namespace GfMapBoundary;

class Autoloader
{
    /** @var string */
    private $baseNamespace = 'GfMapBoundary\\';
    /** @var string */
    private $baseDir;

    public function __construct(string $baseDir)
    {
        $this->baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    public function register(): void
    {
        spl_autoload_register([$this, 'autoload']);
    }

    private function autoload(string $class): void
    {
        if (strpos($class, $this->baseNamespace) !== 0) {
            return;
        }
        $relative = substr($class, strlen($this->baseNamespace));
        $relativePath = str_replace(['\\', '_'], [DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], $relative) . '.php';
        $file = $this->baseDir . $relativePath;
        if (is_readable($file)) {
            require $file;
        }
    }
}

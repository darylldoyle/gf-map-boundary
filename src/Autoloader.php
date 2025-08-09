<?php

namespace GfMapBoundary;

/**
 * Simple PSR-4-ish autoloader for this plugin's src/ namespace.
 *
 * What: Registers a callback to load classes under the base namespace by
 * mapping namespace separators to directory separators.
 * Why: We avoid requiring Composer for a small plugin while keeping classes
 * organized and lazily loaded. We also guard against loading classes outside
 * the plugin's namespace to prevent accidental file includes.
 */
class Autoloader {
	/**
	 * Base namespace prefix that this autoloader is responsible for.
	 *
	 * @var string
	 */
	private $baseNamespace = 'GfMapBoundary\\';

	/**
	 * Absolute path to the directory where the namespace root is located.
	 * Trailing slash is ensured in constructor so path joins are predictable.
	 *
	 * @var string
	 */
	private $baseDir;

	/**
	 * @param string $baseDir Absolute path to the src folder for the namespace root.
	 */
	public function __construct( string $baseDir ) {
		$this->baseDir = rtrim( $baseDir, '/\\' ) . DIRECTORY_SEPARATOR;
	}

	/**
	 * Register the autoload callback with SPL.
	 *
	 * Why: Delays any IO until a class is actually referenced, and keeps
	 * registration explicit from plugin bootstrap.
	 */
	public function register(): void {
		spl_autoload_register( [ $this, 'autoload' ] );
	}

	/**
	 * Attempt to autoload the given class if it matches the base namespace.
	 *
	 * @param string $class Fully-qualified class name that SPL requests.
	 *
	 * @return void
	 */
	private function autoload( string $class ): void {
		if ( strpos( $class, $this->baseNamespace ) !== 0 ) {
			// Not our namespace; bail to let other autoloaders handle it.
			return;
		}
		$relative     = substr( $class, strlen( $this->baseNamespace ) );
		$relativePath = str_replace( [ '\\', '_' ], [ DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR ], $relative ) . '.php';
		$file         = $this->baseDir . $relativePath;
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
}

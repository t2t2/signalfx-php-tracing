<?php

namespace DDTrace\Bridge;

/**
 * Tells whether or not tracing is enabled without having to fire the auto-loading mechanism.
 *
 * @return bool
 */
function dd_tracing_enabled()
{
    $value = getenv('SIGNALFX_TRACING_ENABLED');
    if (false === $value) {
        // Not setting the env means we default to enabled.
        return true;
    }

    $value = trim(strtolower($value));
    if ($value === '0' || $value === 'false') {
        return false;
    } else {
        return true;
    }

    if ('cli' === PHP_SAPI) {
        $cliEnabled = getenv('SIGNALFX_TRACING_CLI_ENABLED');
        if (false === $cliEnabled) {
            return false;
        }
        $cliEnabled = strtolower(trim($cliEnabled));
        return 'true' === $cliEnabled || '1' === $cliEnabled;
    }
}

/**
 * Extracts an array ['My\Autoloader\Class', 'method'] if the loader class and methods are in a known format, otherwise
 * it returns null.
 *
 * @param \callable $loader As in http://php.net/manual/en/language.types.callable.php
 * @return array|null
 */
function extract_autoloader_class_and_method($loader)
{
    // Covers case: spl_autoloader_register('Some\Class::load')
    if (is_string($loader)) {
        $parts = explode('::', $loader);
        return count($parts) === 2 ? [$parts[0], $parts[1]] : null;
    }
    // Covers case: spl_autoloader_register(['Some\Class', 'load'])
    if (is_array($loader) && count($loader) === 2) {
        if (is_string($loader[0])) {
            return [$loader[0], $loader[1]];
        } elseif (is_object($loader[0])) {
            return [get_class($loader[0]), $loader[1]];
        } else {
            return null;
        }
    }
    // Case not covered: spl_autoloader_register(null);
    // Case not covered: spl_autoloader_register(function () {});
    return null;
}

/**
 * Registers the Datadog.
 */
function dd_register_autoloader()
{
    require_once __DIR__ . '/dd_required_deps_autoloader.php';
    require_once __DIR__ . '/dd_optional_deps_autoloader.php';

    spl_autoload_register(['\DDTrace\Bridge\OptionalDepsAutoloader', 'load'], true, true);
    spl_autoload_register(['\DDTrace\Bridge\RequiredDepsAutoloader', 'load'], true, true);
}

/**
 * Unregisters the Datadog.
 */
function dd_unregister_autoloader()
{
    spl_autoload_unregister(['\DDTrace\Bridge\RequiredDepsAutoloader', 'load']);
    spl_autoload_unregister(['\DDTrace\Bridge\OptionalDepsAutoloader', 'load']);
}

/**
 * Traces spl_autoload_register in order to provide hooks for auto-instrumentation to be executed.
 */
function dd_wrap_autoloader()
{
    dd_register_autoloader();

    // Composer auto-generates a class loader with a varying name which follows the pattern
    // `ComposerAutoloaderInitaa9e6eaaeccc2dd24059c64bd3ff094c`. The name of this class varies and this variable is
    // used to keep track of the actual name.
    $composerAutogeneratedClass = null;

    dd_trace('spl_autoload_register', function () use (&$composerAutogeneratedClass) {
        $originalAutoloaderRegistered = dd_trace_forward_call();
        $args = func_get_args();
        if (sizeof($args) == 0) {
            return $originalAutoloaderRegistered;
        }
        list($loader) = $args;

        $extractedClassAndMethod = extract_autoloader_class_and_method($loader);
        if (empty($extractedClassAndMethod)) {
            return $originalAutoloaderRegistered;
        }
        list ($loaderClass) = $extractedClassAndMethod;

        // If we detect the composer autogenerated autoloader, there is nothing we have to do at this time.
        // We wait for the next class, which is the actual composer autoloader.
        // Composer registers its own autoloader pre-pending it to the list of already
        // registered auto-loaders. For this reason it would load the DDTrace namespace from its vendor folder
        // if available. On the other hand, returning when we detect a `ComposerAutoloaderInit*` class is required
        // otherwise we would trigger auto-instrumentation before the actual composer autoloader kicks in and we would
        // always use the DDTrace classes provided with the bundle even if the user declared `datadog/dd-trace` in his
        // composer.
        $generatedComposerClassPrefix = 'ComposerAutoloaderInit';
        if (substr($loaderClass, 0, strlen($generatedComposerClassPrefix)) === $generatedComposerClassPrefix) {
            $composerAutogeneratedClass = $loaderClass;
            return $originalAutoloaderRegistered;
        }

        dd_untrace('spl_autoload_register');
        require __DIR__ . '/dd_init.php';
    });
}

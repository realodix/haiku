<?php

use Composer\Autoload\ClassLoader;
use Illuminate\Container\Container;
use Symfony\Component\Filesystem\Path;

if (!function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @template TClass of object
     *
     * @param string|class-string<TClass> $abstract
     * @param list<mixed> $parameters
     * @return ($abstract is class-string<TClass> ? TClass : mixed)
     *
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    function app($abstract = null, array $parameters = [])
    {
        if ($abstract === null) {
            return Container::getInstance();
        }

        return Container::getInstance()->make($abstract, $parameters);
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the path to the base of the application root.
     */
    function base_path(string $path = ''): string
    {
        $basePath = dirname(array_values(array_filter(
            array_keys(ClassLoader::getRegisteredLoaders()),
            fn($pharPath) => !str_starts_with($pharPath, 'phar://'),
        ))[0]);

        return Path::join($basePath, $path);
    }
}

if (!function_exists('project_path')) {
    function project_path(string $path = ''): string
    {
        return Path::join(getcwd(), $path);
    }
}

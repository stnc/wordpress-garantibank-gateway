<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite848b28fab08bc66b69b61754741869b
{
    public static $prefixesPsr0 = array (
        'S' => 
        array (
            'SanalPos' => 
            array (
                0 => __DIR__ . '/../..' . '/src',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixesPsr0 = ComposerStaticInite848b28fab08bc66b69b61754741869b::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}

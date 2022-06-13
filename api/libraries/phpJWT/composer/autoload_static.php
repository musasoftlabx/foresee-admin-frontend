<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit68a2a7f629ec19d10d52a1df470c394c
{
    public static $prefixLengthsPsr4 = array (
        'F' => 
        array (
            'Firebase\\JWT\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Firebase\\JWT\\' => 
        array (
            0 => __DIR__ . '/..' . '/firebase/php-jwt/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit68a2a7f629ec19d10d52a1df470c394c::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit68a2a7f629ec19d10d52a1df470c394c::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
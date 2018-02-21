<?php

// autoload_static.php @generated by Composer
namespace Composer\Autoload;

class ComposerStaticInit7d97905834793182150cb6b60d8ad677
{

    public static $prefixLengthsPsr4 = array(
        'W' => array(
            'Workerman\\' => 10
        )
    );

    public static $prefixDirsPsr4 = array(
        'Workerman\\' => array(
            0 => __DIR__ . '/../..' . '/'
        )
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit7d97905834793182150cb6b60d8ad677::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit7d97905834793182150cb6b60d8ad677::$prefixDirsPsr4;
        }, null, ClassLoader::class);
    }
}

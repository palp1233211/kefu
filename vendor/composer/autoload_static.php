<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit116eb3f97831b981bdf8e85d41fcec82
{
    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'Workerman\\' => 10,
        ),
        'G' => 
        array (
            'GatewayWorker\\' => 14,
            'GatewayClient\\' => 14,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Workerman\\' => 
        array (
            0 => __DIR__ . '/..' . '/workerman/workerman',
        ),
        'GatewayWorker\\' => 
        array (
            0 => __DIR__ . '/..' . '/workerman/gateway-worker/src',
        ),
        'GatewayClient\\' => 
        array (
            0 => __DIR__ . '/..' . '/workerman/gatewayclient',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit116eb3f97831b981bdf8e85d41fcec82::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit116eb3f97831b981bdf8e85d41fcec82::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
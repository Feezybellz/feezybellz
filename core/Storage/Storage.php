<?php

namespace Framework\Core\Storage;

class Storage
{
    protected static $disks = [];

    /**
     * Get a filesystem disk instance.
     *
     * @param  string|null  $name
     * @return \Framework\Core\Storage\Drivers\StorageDriverInterface
     */
    public static function disk(?string $name = null)
    {
        $name = $name ?: config('filesystems.default');
        
        if (!isset(self::$disks[$name])) {
            self::$disks[$name] = self::resolve($name);
        }

        return self::$disks[$name];
    }

    /**
     * Resolve the given disk by name.
     */
    protected static function resolve(string $name)
    {
        $config = config("filesystems.disks.{$name}");
        if (!$config) {
            throw new \Exception("Storage disk [{$name}] is not configured.");
        }

        return self::build($config);
    }

    /**
     * Build an on-demand storage disk with runtime configuration.
     *
     * @param array $config
     * @return \Framework\Core\Storage\Drivers\StorageDriverInterface
     */
    public static function build(array $config)
    {
        $driver = $config['driver'] ?? 'local';
        
        switch ($driver) {
            case 'local':
                return new Drivers\LocalDriver($config);
            case 's3':
                return new Drivers\S3Driver($config);
            case 'r2':
                return new Drivers\R2Driver($config);
            case 'ftp':
                return new Drivers\FTPDriver($config);
            default:
                throw new \Exception("Storage driver [{$driver}] is not supported.");
        }
    }

    /**
     * Dynamically call the default driver instance.
     */
    public static function __callStatic($method, $args)
    {
        return self::disk()->$method(...$args);
    }
}

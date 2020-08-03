<?php
namespace Libs;

class FileSystemManager{
    /**
     * Create directory
     *
     * This method get data from data api
     *
     * @param string $path
     * @param string $name
     */
    public static function mkdir(string $path, string $name)
    {
        chdir($path);
        mkdir($name);
    }


}
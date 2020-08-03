<?php
namespace Libs;

class ReadFile{
    /**
     * Read json file
     *
     * This method read json file and return array.
     *
     * @param  string path to file
     *
     * @return array
     */
    public static function readJsonFile(string $path)
    {
        $json = file_get_contents($path);
        print_r($json);
        return json_decode($json, true);
    }

    /**
     * Read xml file
     *
     * This method read xml file and return array.
     *
     * @param  string path to file
     *
     * @return array
     */
    public static function readXmlFile(string $path)
    {
        $xml = simplexml_load_file( $path);
        $json = json_encode($xml);
        return json_decode($json,true);
    }
}
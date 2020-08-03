<?php
namespace Libs;
use Slim\Container;

class DataApi{
    private $config;
    private $c;

    function __construct() {
        $config = require __DIR__ . '/../../app/settings.local.php';
        $c = new Container($config);
    }
    /**
     * Get data
     *
     * This method get data from data api
     *
     * @param  string url
     *
     * @return array
     */
    public static function get(string $path)
    {
        $config = require __DIR__ . '/../../app/settings.local.php';
        $c = new Container($config);
        $ch = curl_init($c['settings']['data_api_url'] . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $json_data = curl_exec($ch);
        curl_close($ch);
        if($json_data === FALSE){
            return null;
        }
        return (json_decode( $json_data, true));
    }

    /**
     * Post data
     *
     * This method post data to data api.
     *
     * @param  string url
     * @param  string body
     *
     * @return array response
     */
    public static function post(string $path, string $body)
    {
        $config = require __DIR__ . '/../../app/settings.local.php';
        $c = new Container($config);
        $ch = curl_init($c['settings']['data_api_url']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        $json_data = curl_exec($ch);
        curl_close($ch);
        if($json_data === FALSE){
            return null;
        }
        return (json_decode( $json_data, true));
    }

    /**
     * Put data
     *
     * This method put data through data api.
     *
     * @param  string url
     * @param  string body
     *
     * @return array response
     */
    public static function put(string $path, string $body)
    {
        $config = require __DIR__ . '/../../app/settings.local.php';
        $c = new Container($config);
        $ch = curl_init($c['settings']['data_api_url']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        $json_data = curl_exec($ch);
        curl_close($ch);
        if($json_data === FALSE){
            return null;
        }
        return (json_decode( $json_data, true));
    }

    /**
     * Put data
     *
     * This method put data through data api.
     *
     * @param  string url
     * @param  string body
     *
     * @return array response
     */
    public static function delete(string $path)
    {
        $config = require __DIR__ . '/../../app/settings.local.php';
        $c = new Container($config);
        $ch = curl_init('http://localhost/ecyano-api/www/organisms/249');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $json_data = curl_exec($ch);
        curl_close($ch);
        if($json_data === FALSE){
            return null;
        }
        return (json_decode( $json_data, true));
    }
}
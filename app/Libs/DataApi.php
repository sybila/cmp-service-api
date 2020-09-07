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
        $ch = curl_init($c['settings']['data_api_url'] . $path);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        $json_data = curl_exec($ch);
        //ob_flush();
        curl_close($ch);
        if($json_data === FALSE){
            return null;
        }
        return (json_decode( $json_data, true));
    }

    /**
     * Multi post data
     *
     * This method post data to data api.
     *
     * @param  string url
     * @param  string body
     *
     * @return array response
     */
    public static function multiPost(array $urls, array $bodies, bool $skip_errors): array
    {
        $mh = curl_multi_init();
        foreach ($urls as $key => $url) {
            $config = require __DIR__ . '/../../app/settings.local.php';
            $c = new Container($config);
            $chs[$key] = curl_init($c['settings']['data_api_url'] . $url);
            curl_setopt($chs[$key], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chs[$key], CURLOPT_POST, true);
            curl_setopt($chs[$key], CURLOPT_POSTFIELDS, $bodies[$key]);
            curl_multi_add_handle($mh, $chs[$key]);
        }
        $running = null;
        $line = 0;
        $warnings = array();
        do{
            curl_multi_exec($mh, $running);
        }while($running);
        foreach(array_keys($chs) as $key){
            $response = json_decode(curl_multi_getcontent($chs[$key]), true);  // get results
            if($response['status'] == 'error') {
                if(!$skip_errors){
                    return array(
                        'status' => 'error',
                        "error" => array('line' => $line, 'data' => $bodies[$line])
                    );
                }
                $warnings[] = array('line' => $line, 'data' => $bodies[$line]);
            }
            $line++;
            curl_multi_remove_handle($mh, $chs[$key]);
        }
        curl_multi_close($mh);
        return array(
            'status' => 'ok',
            'warnings' => $warnings
        );
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
        $ch = curl_init($c['settings']['data_api_url']. $path);
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
     * Delete data
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
        $ch = curl_init($c['settings']['data_api_url'] . $path);
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
<?php
namespace Libs;
use Slim\Container;

class DataApi{
    /**
     * Get data
     *
     * This method get data from data api
     *
     * @param  string url
     * @param  string url
     * @return array
     */
    public static function get(string $path, string $access_token)
    {
        $config = require __DIR__ . '/../../app/settings.local.php';
        $c = new Container($config);
        $ch = curl_init($c['settings']['data_api_url'] . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', "Authorization: " . $access_token));
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
     * @param  string access_token
     * @return array response
     */
    public static function post(string $path, string $body, string $access_token)
    {
        $config = require __DIR__ . '/../../app/settings.local.php';
        $c = new Container($config);
        $ch = curl_init($c['settings']['data_api_url'] . $path);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', "Authorization: " . $access_token]);
        $json_data = curl_exec($ch);
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
     * @param  array url
     * @param  array body
     * @param  bool skip_errors
     * @param  string access_token
     * @return array response
     */
    public static function multiPost(array $urls, array $bodies, bool $skip_errors, string $access_token): array
    {
        $mh = curl_multi_init();
        $chs = [];
        foreach ($urls as $key => $url) {
            $config = require __DIR__ . '/../../app/settings.local.php';
            $c = new Container($config);
            $chs[$key] = curl_init($c['settings']['data_api_url'] . $url);
            curl_setopt($chs[$key], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chs[$key], CURLOPT_POST, true);
            curl_setopt($chs[$key], CURLOPT_POSTFIELDS, $bodies[$key]);
            curl_setopt($chs[$key], CURLOPT_HTTPHEADER, ["Authorization: " . $access_token]);
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
     * @param  string access_token
     * @return array response
     */
    public static function put(string $path, string $body, string $access_token)
    {
        $config = require __DIR__ . '/../../app/settings.local.php';
        $c = new Container($config);
        $ch = curl_init($c['settings']['data_api_url']. $path);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json', "Authorization: " . $access_token]);
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
     * @param  string path
     * @param  string access_token
     * @return array response
     */
    public static function delete(string $path, string $access_token)
    {
        $config = require __DIR__ . '/../../app/settings.local.php';
        $c = new Container($config);
        $ch = curl_init($c['settings']['data_api_url'] . $path);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $access_token]);
        $json_data = curl_exec($ch);
        curl_close($ch);
        if($json_data === FALSE){
            return null;
        }
        return (json_decode( $json_data, true));
    }
}
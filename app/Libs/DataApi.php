<?php
namespace Libs;
use App\Exceptions\DataAPIException;
use Slim\Container;
use App\Exceptions\OperationFailedException;

class DataApi{

    const GET_JSON = 1;

    /**
     * Get data
     *
     * This method get data from data api
     *
     * @param string path
     * @param string|null $access_token
     * @return array
     * @throws OperationFailedException
     */
    public static function get(string $path, ?string $access_token)
    {
        $config = require __DIR__ . '/../../app/settings.local.php';
        $c = new Container($config);
        $ch = curl_init($c['settings']['data_api_url'] . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if($access_token != null){
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', "Authorization: " . $access_token));
        } else{
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $json_data = curl_exec($ch);
        curl_close($ch);
        if($json_data === FALSE){
            throw new OperationFailedException("Get.");
        }
        return (json_decode( $json_data, true));
    }

    /**
     * Post data
     *
     * This method post data to data api.
     *
     * @param string url
     * @param string body
     * @param string access_token
     * @return array response
     * @throws \Exception
     */
    public static function post(string $path, string $body, string $access_token)
    {
        $config = require __DIR__ . '/../../app/settings.local.php';
        $c = new Container($config);
        $ch = curl_init($c['settings']['data_api_url'] . $path);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', "Authorization: " . $access_token));
        $json_data = curl_exec($ch);
        if (curl_errno($ch)) {
            dump(curl_error($ch)); exit;
        }
        curl_close($ch);
        if($json_data === FALSE){
            throw new OperationFailedException("Post.");
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
            curl_setopt($chs[$key], CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($chs[$key], CURLOPT_SSL_VERIFYPEER, 0);
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

    /**
     * Extended GET to get the data while sending the body
     *
     * This method gets data from data api
     *
     * @param string $path
     * @param string|null $access_token
     * @param string $body
     * @param int $outputType
     * @return array|string
     * @throws OperationFailedException
     */
    public static function getWithBody(string $path, ?string $access_token, string $body, int $outputType = 0)
    {
        $config = require __DIR__ . '/../../app/settings.local.php';
        $c = new Container($config);
        $ch = curl_init($c['settings']['data_api_url'] . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        if($access_token != null){
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', "Authorization: " . $access_token));
        } else{
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $data = curl_exec($ch);
        $jsonResponse = json_decode($data, true);
        if ($jsonResponse != null && $jsonResponse['status'] !== 'ok') {
            throw new DataAPIException($jsonResponse['message']);
        }
        curl_close($ch);
        if ($outputType === self::GET_JSON) {
            if($data === FALSE) {
                throw new OperationFailedException("Get.");
            }
            return (json_decode( $data, true));
        } else {
            return $data;
        }
    }
}
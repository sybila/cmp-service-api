<?php
namespace Libs;

use Slim\Http\Request;
use Slim\Http\Response;

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

    public static function downloadFile($path){
        $filename = basename($path);
        if(!empty($filename) && file_exists($path)){
            header("Cache-Control: public");
            header("Content-Description: File Transfer");
            header("Content-Disposition: attachment; filename=$filename");
            header("Content-Type: application/zip");
            header("Content-Transfer-Encoding: binary");
            readfile($path);
            exit;
        }
    }

    public static function uploadFile($destination){
        if(isset($_POST['upload'])){
            $file_name = $_FILES['file']['name'];
            $file_type = $_FILES['file']['type'];
            $file_size = $_FILES['file']['size'];
            $file_tem_loc = $_FILES['file']['tem_name'];
            move_uploaded_file($file_tem_loc, $destination);
        }
    }

}
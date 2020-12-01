<?php


namespace Controllers\Endpoints;


use App\Exceptions\AccessForbiddenException;
use Controllers\Abstracts\AbstractController;
use Libs\DataApi;
use Slim\Http\Request;

class ExperimentAccess extends AbstractController
{
    private static $access_token = "";

    /**
     * Check access by access token
     *
     * Throw error if user doesn't have access else return access token
     *
     * @param Request $request
     * @param $exp_id
     * @return string access token
     * @throws AccessForbiddenException
     */
    protected static function checkAccess(Request $request, $exp_id){
        $access_token = $request->getHeader("HTTP_AUTHORIZATION");
        if(!empty($access_token)) {
            $access_token = $access_token[0];
        } else{
            $access_token = null;
        }
        $access = DataApi::get("experiments/". $exp_id, $access_token);
        if($access['status'] != 'ok'){
            throw new AccessForbiddenException("Not authorized.");
        }
        return $access_token;
    }
}
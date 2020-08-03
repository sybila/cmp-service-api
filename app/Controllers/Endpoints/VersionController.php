<?php

namespace Controllers\Endpoints;
use App\Helpers\ArgumentParser;
use Controllers\Abstracts\AbstractController;
use Slim\Http\Request;
use Slim\Http\Response;

class VersionController extends AbstractController
{
    public function __invoke(Request $request, Response $response, $args)
    {
        return self::formatOk($response, ['version' => '0.2',
            '"password" hashed' => password_hash("password", PASSWORD_DEFAULT)]);
    }
}


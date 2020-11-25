<?php

namespace Controllers\Endpoints;

    use App\Exceptions\NonExistentVariableException;
    use Controllers\Abstracts\AbstractController;
    use Libs\DataApi;
    use Slim\Http\Request;
    use Slim\Http\Response;

class ConvertUnitsController extends AbstractController
{
    /**
     * @param $unitFromName
     * @param $unitToName
     * @return float|int
     * @throws NonExistentVariableException
     * @throws \App\Exceptions\OperationFailedException
     */
    private static function convert($unitFromName, $unitToName)
    {
        //$accessToken =

        $json_data = DataApi::get("unitsAliasesAll?filter[alternative_name]=" . $unitFromName, "");
        if($json_data['status'] != "ok" || empty($json_data['date'])) {
            throw new NonExistentVariableException($unitFromName);
        }
        $unitFromId = $json_data['data'][0]['unit_id'];
        $json_data = DataApi::get("unitsAliasesAll?filter[alternative_name]=" . $unitToName, "");
        if($json_data['status'] != "ok" || empty($json_data['date'])) {
            throw new NonExistentVariableException($unitToName);
        }
        $unitToId = $json_data['data'][0]['unit_id'];
        $json_data = DataApi::get("unitsall/" . $unitFromId, "");
        $unitFromCoefficient = $json_data['data']['coefficient'];
        $json_data = DataApi::get("unitsall/" . $unitToId, "");
        $unitToCoefficient = $json_data['data']['coefficient'];
        return $unitFromCoefficient / $unitToCoefficient;
    }

    public static function fromUnitToUnit(Request $request, Response $response, $args)
    {
        $access_token = $request->getHeader("HTTP_AUTHORIZATION");
        $unitFromName = $args['unitFromName'];
        $unitToName = $args['unitToName'];
        $coefficient = self::convert($unitFromName, $unitToName);
        return self::formatOk($response, ["coefficient" => $coefficient]);
    }
}
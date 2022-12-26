<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Response;

class PhitomasBNMController extends Controller
{
    public function exchangeRates(Request $request){ 
        
        $client = new Client(); //site url, username, password
        $token = $client->request('GET', $request->input('csi_url').'/ido/token/'.$request->input('csi_site').'/'.$request->input('csi_username').'/'.$request->input('csi_password'));
        $tokenData = json_decode($token->getBody(), true)['Token'];
        
        if($tokenData == null || $tokenData == ""){
            $tokenErrorMessage = json_decode($token->getBody(), true)['Message'];
            return Response::json(array(
                'code'      =>  404,
                'message'   =>  $tokenErrorMessage
            ), 404);
        }
        
        if ($request->input('to_currency') == "" && $request->input('from_currency') != "") {
            $loadCollectionIDO = 'ILNT_CurrencyMap_mst';
            $loadCollectionProperties = 'map_curr_code, curr_code';
            $loadCollectionFilter = "curr_code >= '" .$request->input('from_currency')."'";
        } else if ($request->input('to_currency') != "" && $request->input('from_currency') == "") {
            $loadCollectionIDO = 'ILNT_CurrencyMap_mst';
            $loadCollectionProperties = 'map_curr_code, curr_code';
            $loadCollectionFilter = "curr_code <= '" .$request->input('to_currency')."'";
        } else if ($request->input('to_currency') != "" && $request->input('from_currency') != "") {
            $loadCollectionIDO = 'ILNT_CurrencyMap_mst';
            $loadCollectionProperties = 'map_curr_code, curr_code';
            $loadCollectionFilter = "curr_code BETWEEN '" .$request->input('from_currency')."' AND '".$request->input('to_currency')."'";
        }

        $validateCheckLotExistsRes = $client->request('GET', $request->input('csi_url') . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
        $to_currency = json_decode($validateCheckLotExistsRes->getBody(), true);
        
        if(!$to_currency['Success']){
            $errorMessage = $to_currency['Message'];
            return Response::json(array(
                'code'      =>  404,
                'message'   =>  $errorMessage
            ), 404);
        }

        foreach ($to_currency['Items'] as $data) {
            $bnmClient = new Client(['headers' => ['Accept' => 'application/vnd.BNM.API.v1+json'], 'http_errors' => false]);
            $quote = 'rm';
            $res = $bnmClient->request('GET', $request->input('bnm_url'). $data['map_curr_code'] . '/date/' . $request->date . '?session=0900&quote=' . $quote);
            $bnmData = $res->getBody()->getContents();
            $datas = json_decode($bnmData, true);

            if($res->getStatusCode()!= 404){
                if ($request->input('type') == 'M') {
                    $SellRate = $datas['data']['rate']['middle_rate'];
                    $BuyRate = $datas['data']['rate']['middle_rate'];
                } else if ($request->input('type') == 'S') {
                    $SellRate = $datas['data']['rate']['selling_rate'];
                    $BuyRate = $datas['data']['rate']['selling_rate'];
                } else if ($request->input('type') == 'B') {
                    $SellRate = $datas['data']['rate']['buying_rate'];
                    $BuyRate = $datas['data']['rate']['buying_rate'];
                } else { //x
                    $SellRate = $datas['data']['rate']['selling_rate'];
                    $BuyRate = $datas['data']['rate']['buying_rate'];
                }

                $result[] = [
                    [
                        'Name' => "FromCurrCode",
                        'Value' => 'MYR',
                        'Modified' => true,
                        'ISNull' => false,
                    ],
                    [
                        'Name' => "ToCurrCode",
                        'Value' => $data['curr_code'],
                        'Modified' => true,
                        'ISNull' => false,
                    ],
                    [
                        'Name' => "SellRate",
                        'Value' => (string) $SellRate,
                        'Modified' => true,
                        'ISNull' => false,
                    ],
                    [
                        'Name' => "BuyRate",
                        'Value' => (string) $BuyRate,
                        'Modified' => true,
                        'ISNull' => false,
                    ],
                    [
                        'Name' => "EffDate",
                        'Value' => $request->date,
                        'Modified' => true,
                        'ISNull' => false,
                    ]
                ];
            } else {
                return [
                    'Currency Code' => $data['curr_code'],
                    'Date' => $request->date,
                    'Message' => $datas['message']
                ];
            }
        }

        foreach ($result as $data) {
            $changes[] = [
                'Action' => 1,
                'ItemId' => "",
                'UpdateLocking' => "1",
                'Properties' => $data
            ];
        }
        
        $insertBody['Changes'] = $changes;
        $insertRes = $client->request('POST', $request->input('csi_url').'/ido/update/SLCurrates?refresh=true', ['headers' => ['Authorization' => $tokenData], 'json' => $insertBody]);
        $insertResponse = json_decode($insertRes->getBody()->getContents(), true);

        return response()->json($insertResponse);
    }
}

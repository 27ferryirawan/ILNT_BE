<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Response;
// {
// 	"from_currency": "USD",
// 	"to_currency": "USD",
// 	"rate_date": "2022-12-27",
// 	"post_date": "2022-12-27 00:00:02",
// 	"rate_session": "0900",
// 	"type": "X",
// 	"csi_url": "http://MYUAT-APPL05/IDORequestService",
// 	"csi_username": "sa",
// 	"csi_password": "SLsa4471f",
// 	"bnm_url": "https://api.bnm.gov.my/public/exchange-rate/",
// 	"csi_site": "CRP_ILNTHQ",
// 	"is_get_bnm_currency": 1
// }
class PhitomasBNMController extends Controller
{
    public function exchangeRates(Request $request){ 
        
        if($request->input('csi_url') == null || $request->input('csi_url') == ""){
            $errorMessage = "Please maintain the CSI URL";
            return Response::json(array(
                'Success'   => false,
                'code'      =>  404,
                'Message'   =>  $errorMessage
            ), 404);
        }

        if($request->input('csi_site') == null || $request->input('csi_site') == ""){
            $errorMessage = "Please maintain the CSI Site";
            return Response::json(array(
                'Success'   => false,
                'code'      =>  404,
                'Message'   =>  $errorMessage
            ), 404);
        }

        if($request->input('csi_username') == null || $request->input('csi_username') == ""){
            $errorMessage = "Please maintain the Username";
            return Response::json(array(
                'Success'   => false,
                'code'      =>  404,
                'Message'   =>  $errorMessage
            ), 404);
        }

        if($request->input('csi_password') == null || $request->input('csi_password') == ""){
            $errorMessage = "Please maintain the PasswordL";
            return Response::json(array(
                'Success'   => false,
                'code'      =>  404,
                'Message'   =>  $errorMessage
            ), 404);
        }

        $client = new Client();
        $token = $client->request('GET', $request->input('csi_url').'/ido/token/'.$request->input('csi_site').'/'.$request->input('csi_username').'/'.$request->input('csi_password'));
        $tokenData = json_decode($token->getBody(), true)['Token'];
        
        if($tokenData == null || $tokenData == ""){
            $tokenErrorMessage = json_decode($token->getBody(), true)['Message'];
            $this->insertErrorLog($tokenErrorMessage, $client, $request, $tokenData);
            return Response::json(array(
                'Success'   => false,
                'Code'      =>  404,
                'Message'   =>  $tokenErrorMessage
            ), 404);
        }

        $loadCollectionIDO = 'ILNT_Parms';
        $loadCollectionProperties = 'process_url, bnm_url, csi_url, username, password, rate_session, post_date, post_date_time';
        $loadCollectionFilter = '';
        $loadCollectRes = $client->request('GET', $request->input('csi_url') . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
        $ILNTParms = json_decode($loadCollectRes->getBody(), true);
        
        if($ILNTParms['Items'][0]['process_url'] == null || $ILNTParms['Items'][0]['process_url'] == ""){
            $errorMessage = "Please maintain the Process URL";
            $this->insertErrorLog($errorMessage, $client, $request, $tokenData);
            return Response::json(array(
                'Success'   => false,
                'code'      =>  404,
                'Message'   =>  $errorMessage
            ), 404);
        }

        if($ILNTParms['Items'][0]['bnm_url'] == null || $ILNTParms['Items'][0]['bnm_url'] == ""){
            $errorMessage = "Please maintain the BNM URL";
            $this->insertErrorLog($errorMessage, $client, $request, $tokenData);
            return Response::json(array(
                'Success'   => false,
                'code'      =>  404,
                'Message'   =>  $errorMessage
            ), 404);
        }

        if($ILNTParms['Items'][0]['rate_session'] == null || $ILNTParms['Items'][0]['rate_session'] == ""){
            $errorMessage = "Please maintain the Rate Session";
            $this->insertErrorLog($errorMessage, $client, $request, $tokenData);
            return Response::json(array(
                'Success'   => false,
                'code'      =>  404,
                'Message'   =>  $errorMessage
            ), 404);
        }

        if($ILNTParms['Items'][0]['post_date'] == null || $ILNTParms['Items'][0]['post_date'] == ""){
            $errorMessage = "Please maintain the Post Date Increment";
            $this->insertErrorLog($errorMessage, $client, $request, $tokenData);
            return Response::json(array(
                'Success'   => false,
                'code'      =>  404,
                'Message'   =>  $errorMessage
            ), 404);
        }
        
        if($ILNTParms['Items'][0]['post_date_time'] == null || $ILNTParms['Items'][0]['post_date_time'] == ""){
            $errorMessage = "Please maintain the Post Date Time";
            $this->insertErrorLog($errorMessage, $client, $request, $tokenData);
            return Response::json(array(
                'Success'   => false,
                'code'      =>  404,
                'Message'   =>  $errorMessage
            ), 404);
        }

        $bnmMapClient = new Client(['headers' => ['Accept' => 'application/vnd.BNM.API.v1+json'], 'http_errors' => false]);
        $quote = 'rm';
        $res = $bnmMapClient->request('GET', 'https://api.bnm.gov.my/public/exchange-rate');
        $bnmData = $res->getBody()->getContents();
        $bnmDatas = json_decode($bnmData, true)['data'];

        $loadCollectionClient = new Client();
        $loadCollectionIDO = 'UserDefinedTypeValues';
        $loadCollectionProperties = 'Value, TypeName';
        $loadCollectionFilter = "TypeName = 'ILNT_BNMCurrCode'";
        $loadBNMUDTRes = $loadCollectionClient->request('GET', $request->input('csi_url') . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
        $loadBNMUDTResponse = json_decode($loadBNMUDTRes->getBody()->getContents(), true)['Items'];

        if($request->input('is_get_bnm_currency') == 1){
            foreach ($bnmDatas as $bnmMapCurrData) {
                $isCurrCodeExist = 0;
                foreach ($loadBNMUDTResponse as $sytelineMapCurrData) {
                    if ($sytelineMapCurrData['Value'] == $bnmMapCurrData['currency_code'] ){
                        $isCurrCodeExist = 1;
                    }
                }
                if($isCurrCodeExist == 0){
                    $currResult[] = [
                        [
                            'Name' => "TypeName",
                            'Value' => 'ILNT_BNMCurrCode',
                            'Modified' => true,
                            'ISNull' => false,
                        ],
                        [
                            'Name' => "Value",
                            'Value' => $bnmMapCurrData['currency_code'],
                            'Modified' => true,
                            'ISNull' => false,
                        ]
                    ];
                }
            }
            if(count($currResult) > 0){
                foreach ($currResult as $data) {
                    $currChanges[] = [
                        'Action' => 1,
                        'ItemId' => "",
                        'UpdateLocking' => "1",
                        'Properties' => $data
                    ];
                }
                
                $insertCurrBody['Changes'] = $currChanges;
                $insertRes = $client->request('POST', $request->input('csi_url').'/ido/update/UserDefinedTypeValues?refresh=true', ['headers' => ['Authorization' => $tokenData], 'json' => $insertCurrBody]);
                $insertResponse = json_decode($insertRes->getBody()->getContents(), true);
            }
        }
        
        $loadCollectionIDO = 'ILNT_CurrencyMap_mst';
        $loadCollectionProperties = 'map_curr_code, curr_code';
        $loadCollectionFilter = '';
        if ($request->input('to_currency') != "") {
            $loadCollectionFilter = $loadCollectionFilter. "curr_code >= '" .$request->input('from_currency')."'";
        }
        if ($request->input('from_currency') != "") {
            if ($loadCollectionFilter != ""){
                $loadCollectionFilter = $loadCollectionFilter." AND ";
            }
            $loadCollectionFilter = $loadCollectionFilter. "curr_code <= '" .$request->input('to_currency')."'";
        } 
        $loadCollectRes = $client->request('GET', $request->input('csi_url') . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
        $to_currency = json_decode($loadCollectRes->getBody(), true);
        
        if(!$to_currency['Success']){
            $errorMessage = $to_currency['Message'];
            $this->insertErrorLog($errorMessage, $client, $request, $tokenData);
            return Response::json(array(
                'Success'   => false,
                'code'      =>  404,
                'Message'   =>  $errorMessage
            ), 404);
        }
        
        if(count($to_currency['Items']) == 0){
            $errorMessage = "Currency Code doesnt match";
            $this->insertErrorLog($errorMessage, $client, $request, $tokenData);
            return Response::json(array(
                'Success'   => false,
                'code'      => 404,
                'Message'   => $errorMessage
            ), 404);
        }

        foreach ($to_currency['Items'] as $data) {
            $bnmClient = new Client(['headers' => ['Accept' => 'application/vnd.BNM.API.v1+json'], 'http_errors' => false]);
            $quote = 'rm';
            $res = $bnmClient->request('GET', $request->input('bnm_url'). $data['map_curr_code'] . '/date/' . $request->rate_date . '?session='.$request->rate_session.'&quote=' . $quote);
            $bnmData = $res->getBody()->getContents();
            $datas = json_decode($bnmData, true);

            if($res->getStatusCode()!= 404){
                $loadCollectionIDO = 'SLCurrencyCodes';
                $loadCollectionProperties = 'CurrCode, RateIsDivisor';
                $loadCollectionFilter = "CurrCode = '". $data['curr_code'] ."'";
                $loadCollectRes = $client->request('GET', $request->input('csi_url') . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
                $rate_is_divisor = json_decode($loadCollectRes->getBody(), true)['Items'][0]['RateIsDivisor'];

                //round down 7 digit
                //if unit!=1; formula 1 = unit/rate; formula 1 = rate
                //if rateisdivisor == 0; 1/formula 1; formula 1
                
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

                if ($datas['data']['unit'] != 1){
                    $tempSellRate = $SellRate/$datas['data']['unit'];
                    $tempBuyRate = $BuyRate/$datas['data']['unit'];
                } else {
                    $tempSellRate = $SellRate;
                    $tempBuyRate = $BuyRate;
                }

                if($rate_is_divisor == 0){
                    $tempSellRate = 1/$SellRate;
                    $tempBuyRate = 1/$BuyRate;
                }
                $fixSellRate = number_format(round($tempSellRate, 7, PHP_ROUND_HALF_DOWN), 7);
                $fixBuyRate = number_format(round($tempBuyRate, 7, PHP_ROUND_HALF_DOWN), 7);
                $result[] = [
                    [
                        'Name' => "FromCurrCode",
                        'Value' => $data['curr_code'],
                        'Modified' => true,
                        'ISNull' => false,
                    ],
                    [
                        'Name' => "ToCurrCode",
                        'Value' => 'MYR',
                        'Modified' => true,
                        'ISNull' => false,
                    ],
                    [
                        'Name' => "SellRate",
                        'Value' => (string) $fixSellRate,
                        'Modified' => true,
                        'ISNull' => false,
                    ],
                    [
                        'Name' => "BuyRate",
                        'Value' => (string) $fixBuyRate,
                        'Modified' => true,
                        'ISNull' => false,
                    ],
                    [
                        'Name' => "EffDate",
                        'Value' => $request->post_date,
                        'Modified' => true,
                        'ISNull' => false,
                    ]
                ];
            } else {
                $this->insertErrorLog($datas['message'], $client, $request, $tokenData);
                return [
                    'Currency Code' => $data['curr_code'],
                    'Date' => $request->rate_date,
                    'Message' => $datas['message'],
                    'Success'=> false
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
        if(!$insertResponse['Success']){
            $this->insertErrorLog($insertResponse['Message'], $client, $request, $tokenData);
        }
        return response()->json($insertResponse);
    }

    protected function insertErrorLog($process_message, $client, $request, $tokenData){
        $getBNMCurrency = $request->input('is_get_bnm_currency') == 1 ? 'Yes' : 'No';
        $process_parameter = 'From Currency: ' . $request->input('from_currency') . ',
        End Currency: ' . $request->input('to_currency') . ',
        Rate Date: ' . $request->input('rate_date') . ',
        Post Date: ' . $request->input('post_date') . ',
        Rate Session: ' . $request->input('rate_session') . ',
        Type: ' .$request->input('type') . ',
        CSI URL: ' . $request->input('csi_url') . ',
        CSI Username: ' . $request->input('csi_username') . ',
        CSI Password: ' . $request->input('csi_password') . ',
        BNM URL: ' . $request->input('bnm_url') . ',
        CSI Site: '. $request->input('csi_site'). ',
        Get BNM Currency": ' . $getBNMCurrency . '';
        $logData[] = [
            [
                'Name' => "process_date",
                'Value' => now()->toDateTimeString(),
                'Modified' => true,
                'ISNull' => false,
            ],
            [
                'Name' => "process_name",
                'Value' => 'BNM Rate Process',
                'Modified' => true,
                'ISNull' => false,
            ],
            [
                'Name' => "process_parameter",
                'Value' => $process_parameter,
                'Modified' => true,
                'ISNull' => false,
            ],
            [
                'Name' => "process_message",
                'Value' => $process_message,
                'Modified' => true,
                'ISNull' => false,
            ]
        ];

        if(count($logData) > 0){
            foreach ($logData as $data) {
                $logChanges[] = [
                    'Action' => 1,
                    'ItemId' => "",
                    'UpdateLocking' => "1",
                    'Properties' => $data
                ];
            }
            
            $insertLogBody['Changes'] = $logChanges;
            $insertRes = $client->request('POST', $request->input('csi_url').'/ido/update/ILNT_BNMRateProcessLog?refresh=true', ['headers' => ['Authorization' => $tokenData], 'json' => $insertLogBody]);
            $insertResponse = json_decode($insertRes->getBody()->getContents(), true);
        }
    }
}

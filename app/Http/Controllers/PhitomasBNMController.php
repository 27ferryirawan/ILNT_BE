<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;

class PhitomasBNMController extends Controller
{
    public function exchangeRates(Request $request){ 
        
        $tokenClient = new Client(); //site url, username, password
        $token = $tokenClient->get($request->input('csi_url').'/ido/token/'.$request->input('csi_site').'/'.$request->input('csi_username').'/'.$request->input('csi_password'));
        $tokenData = json_decode($token->getBody()->getContents(), true)['Token'];
        if ($request->input('to_currency') == "" && $request->input('from_currency') != "") {
            $to_currency = DB::select("
            select map_curr_code, curr_code
            from(
            select ROW_NUMBER ( ) OVER ( order by curr_code asc) as row_id, * from ILNT_CurrencyMap_mst ) AS  a 
            where row_id >= (
            select row_id 
            from(
            select ROW_NUMBER ( ) OVER ( order by curr_code asc) as row_id, * from ILNT_CurrencyMap_mst ) AS  a 
            where curr_code='" . $request->input('from_currency') . "') order by curr_code asc;");
        } else if ($request->input('to_currency') != "" && $request->input('from_currency') == "") {
            $to_currency = DB::select("
            select map_curr_code, curr_code
            from(
            select ROW_NUMBER ( ) OVER ( order by curr_code asc) as row_id, * from ILNT_CurrencyMap_mst ) AS  a 
            where row_id <= (
            select row_id 
            from(
            select ROW_NUMBER ( ) OVER ( order by curr_code asc) as row_id, * from ILNT_CurrencyMap_mst ) AS  a 
            where curr_code='" . $request->input('to_currency') . "') order by curr_code asc;");
        } else if ($request->input('to_currency') != "" && $request->input('from_currency') != "") {
            $to_currency = DB::select("
            select map_curr_code, curr_code
            from(
            select ROW_NUMBER ( ) OVER ( order by curr_code asc) as row_id, * from ILNT_CurrencyMap_mst ) AS  a 
            where row_id BETWEEN (
                select row_id 
                from(
                select ROW_NUMBER ( ) OVER ( order by curr_code asc) as row_id, * from ILNT_CurrencyMap_mst ) AS  a 
                where curr_code='" . $request->input('from_currency') . "') 
            AND
            (
                select row_id 
                from(
                select ROW_NUMBER ( ) OVER ( order by curr_code asc) as row_id, * from ILNT_CurrencyMap_mst ) AS  a 
                where curr_code='" . $request->input('to_currency') . "')
                order by curr_code asc;");
        }

        foreach ($to_currency as $data) {
            $client = new Client(['headers' => ['Accept' => 'application/vnd.BNM.API.v1+json'], 'http_errors' => false]);
            $quote = 'rm';
            $res = $client->request('GET', $request->input('bnm_url'). $data->map_curr_code . '/date/' . $request->date . '?session=0900&quote=' . $quote);
            // return $res->getStatusCode();
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
                        'Value' => $data->curr_code,
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
                    'Currency Code' => $data->curr_code,
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
        $insertClient = new Client();
        $insertRes = $insertClient->request('POST', 'http://20.247.180.239/IDORequestService/ido/update/SLCurrates?refresh=true', ['headers' => ['Authorization' => $tokenData], 'json' => $insertBody]);
        $insertResponse = json_decode($insertRes->getBody()->getContents(), true);

        return response()->json($insertResponse);
    }
}

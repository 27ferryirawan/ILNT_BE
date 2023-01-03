<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use function GuzzleHttp\Promise\all;

class PhitomasControllerV2 extends Controller
{
    public function inventoryDataMigration(Request $request){
        $config = DB::connection('mysql')->select("select * from config where row_id =" . $request->input('config_id'));
        
        $tokenClient = new Client();
        $token = $tokenClient->get($config[0]->url . "/ido/token/" . $config[0]->config_name . "/" . $config[0]->username . "/" . $config[0]->password);
        $tokenData = json_decode($token->getBody()->getContents(), true)['Token'];

        $isBatchExist = false;
        $loadCollectionClient = new Client();
        $loadCollectionIDO = 'SLMatltrans';
        $loadCollectionProperties = 'DocumentNum, TransType, RefType';
        $loadCollectionFilter = "DocumentNum = '" . $request->input('batch_id') . "' AND TransType= 'H' AND RefType = 'I'";
        $validateBatchRes = $loadCollectionClient->request('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
        $validateBatchResponse = json_decode($validateBatchRes->getBody()->getContents(), true);

        $isBatchExist = count($validateBatchResponse['Items']) > 0 ? true : false;

        if ($isBatchExist) {
            $returnMessage = [
                "Status" => "Batch Number Exists",
                "Detail" => [],
                "DetailAll" => []
            ];
            return $returnMessage;
        }

        //Get Batch
        $UniqueLot = '';
        $LotGenExp = '';
        $loadCollectionClient = new Client();
        $loadCollectionIDO = 'SLInvparms';
        $loadCollectionProperties = 'UniqueLot, LotGenExp';
        $loadCollectionFilter = "ParmKey = 0";

        $validateLotRes = $loadCollectionClient->request('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
        $validateLotResponse = json_decode($validateLotRes->getBody()->getContents(), true);
        $UniqueLot = $validateLotResponse['Items'][0]['UniqueLot'];
        $LotGenExp = $validateLotResponse['Items'][0]['LotGenExp'];


        $allSuccess = 0;
        $reader = Excel::load($request->file('files'));
        $results = $reader->get()->toArray();
        // dd($results);

        $isLoopIn = $request->input('loop_count');
        $start_time = Carbon::now()->toDateTimeString();
        $time = DB::connection('mysql')->table('inventory_data_migration_log')->insertGetId(
            [
                'batch_id' => $request->input('batch_id'),
                'row_no' => -1,
                'method_name' => 'Validation',
                'start_time' => $start_time,
                // 'end_time' => $end_time,
                // 'process_duration' => gmdate("H:i:s", (strtotime($end_time) - strtotime($start_time))),
            ]
        );
        for ($i = 0; $i < count($results); $i=$i+$request->input('loop_count')) {
            // Invoke trans date
            //0
            $invokeIDO1 = 'SLPeriods';
            $invokeMethod1 = 'DateChkSp';
            $invokeBody1 = [
                $results[$i]['trans_date'],
                "Transaction Date",
                "@%update",
                "",
                "",
                ""
            ];
            //1
            $invokeBody11 = [
                $results[$i + 1]['trans_date'],
                "Transaction Date",
                "@%update",
                "",
                "",
                ""
            ];
            //2
            $invokeBody12 = [
                $results[$i + 2]['trans_date'],
                "Transaction Date",
                "@%update",
                "",
                "",
                ""
            ];
            //3
            $invokeBody13 = [
                $results[$i + 3]['trans_date'],
                "Transaction Date",
                "@%update",
                "",
                "",
                ""
            ];
            //4
            $invokeBody14 = [
                $results[$i + 4]['trans_date'],
                "Transaction Date",
                "@%update",
                "",
                "",
                ""
            ];

            //load item
            //0
            $loadCollectionIDO2 = 'SLItems';
            $loadCollectionProperties2 = 'Item, LotTracked, SerialTracked, UM, CostType, CostMethod';
            $loadCollectionFilter2 = "Item = '".$results[$i]['item']. "'";
            //1
            $loadCollectionFilter21 = "Item = '".$results[$i+1]['item']. "'";
            //2
            $loadCollectionFilter22 = "Item = '".$results[$i+2]['item']. "'";
            //3
            $loadCollectionFilter23 = "Item = '".$results[$i+3]['item']. "'";
            //4
            $loadCollectionFilter24 = "Item = '".$results[$i+4]['item']. "'";


            //load whse
            //0
            $loadCollectionIDO3 = 'SLItemWhses';
            $loadCollectionProperties3 = 'Item, Whse';
            $loadCollectionFilter3 = "Item = '" . $results[$i]['item'] . "' AND Whse = '" . $results[$i]['whse'] . "'";
            //1
            $loadCollectionFilter31 = "Item = '" . $results[$i + 1]['item'] . "' AND Whse = '" . $results[$i + 1]['whse'] . "'";
            //2
            $loadCollectionFilter32 = "Item = '" . $results[$i + 2]['item'] . "' AND Whse = '" . $results[$i + 2]['whse'] . "'";
            //3
            $loadCollectionFilter33 = "Item = '" . $results[$i + 3]['item'] . "' AND Whse = '" . $results[$i + 3]['whse'] . "'";
            //4
            $loadCollectionFilter34 = "Item = '" . $results[$i + 4]['item'] . "' AND Whse = '" . $results[$i + 4]['whse'] . "'";


            //load loc
            //0
            $loadCollectionIDO4 = 'SLLocations';
            $loadCollectionProperties4 = 'Loc';
            $loadCollectionFilter4 = "Loc = '" . $results[$i]['loc'] . "'";
            //1
            $loadCollectionFilter41 = "Loc = '" . $results[$i + 1]['loc'] . "'";
            //2
            $loadCollectionFilter42 = "Loc = '" . $results[$i + 2]['loc'] . "'";
            //3
            $loadCollectionFilter43 = "Loc = '" . $results[$i + 4]['loc'] . "'";
            //4
            $loadCollectionFilter44 = "Loc = '" . $results[$i + 4]['loc'] . "'";

            //load reason
            //0
            $loadCollectionIDO5 = 'SLReasons';
            $loadCollectionProperties5 = 'ReasonCode,Description';
            $loadCollectionFilter5 = "ReasonClass = 'MISC RCPT' AND ReasonCode = '" . $results[$i]['reason_code'] . "'";
            //1
            $loadCollectionFilter51 = "ReasonClass = 'MISC RCPT' AND ReasonCode = '" . $results[$i + 1]['reason_code'] . "'";
            //2
            $loadCollectionFilter52 = "ReasonClass = 'MISC RCPT' AND ReasonCode = '" . $results[$i + 2]['reason_code'] . "'";
            //3
            $loadCollectionFilter53 = "ReasonClass = 'MISC RCPT' AND ReasonCode = '" . $results[$i + 3]['reason_code'] . "'";
            //4
            $loadCollectionFilter54 = "ReasonClass = 'MISC RCPT' AND ReasonCode = '" . $results[$i + 4]['reason_code'] . "'";

            // validate reason code
            //0
            $invokeIDO6 = 'SLReasons';
            $invokeMethod6 = 'ReasonGetInvAdjAcctSp';
            $invokeBody6 = [
                $results[$i]['reason_code'],
                "MISC RCPT",
                $results[$i]['item'],
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                ""
            ];
            //1
            $invokeBody61 = [
                $results[$i + 1]['reason_code'],
                "MISC RCPT",
                $results[$i + 1]['item'],
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                ""
            ];
            //2
            $invokeBody62 = [
                $results[$i + 2]['reason_code'],
                "MISC RCPT",
                $results[$i + 2]['item'],
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                ""
            ];
            //3
            $invokeBody63 = [
                $results[$i + 3]['reason_code'],
                "MISC RCPT",
                $results[$i + 3]['item'],
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                ""
            ];
            //4
            $invokeBody64 = [
                $results[$i + 4]['reason_code'],
                "MISC RCPT",
                $results[$i + 4]['item'],
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                "",
                ""
            ];

            // invoke physical count in place
            //0
            $WhsePhyInvFlg = false;
            $invokeIDO7 = 'SLWhses';
            $invokeMethod7 = 'CheckWhsePhyInvFlgSp';
            $invokeBody7 = [
                $results[$i]['whse'],
                $config[0]->site,
                "",
                ""
            ];
            //1
            $WhsePhyInvFlg1 = false;
            $invokeBody71 = [
                $results[$i + 1]['whse'],
                $config[0]->site,
                "",
                ""
            ];
            //2
            $WhsePhyInvFlg2 = false;
            $invokeBody72 = [
                $results[$i + 2]['whse'],
                $config[0]->site,
                "",
                ""
            ];
            //3
            $WhsePhyInvFlg3 = false;
            $invokeBody73 = [
                $results[$i + 3]['whse'],
                $config[0]->site,
                "",
                ""
            ];
            //4
            $WhsePhyInvFlg4 = false;
            $invokeBody74 = [
                $results[$i + 4]['whse'],
                $config[0]->site,
                "",
                ""
            ];

            // invoke obsolete item
            //0
            $invokeIDO8 = 'SLItems';
            $invokeMethod8 = 'ObsSlowSp';
            $invokeBody8 = [
                $results[$i]['item'],
                "1",
                "0",
                "0",
                "1",
                "",
                "",
                "",
                ""
            ];
            //1
            $invokeBody81 = [
                $results[$i + 1]['item'],
                "1",
                "0",
                "0",
                "1",
                "",
                "",
                "",
                ""
            ];
            //2
            $invokeBody82 = [
                $results[$i + 2]['item'],
                "1",
                "0",
                "0",
                "1",
                "",
                "",
                "",
                ""
            ];
            //3
            $invokeBody83 = [
                $results[$i + 3]['item'],
                "1",
                "0",
                "0",
                "1",
                "",
                "",
                "",
                ""
            ];
            //4
            $invokeBody84 = [
                $results[$i + 4]['item'],
                "1",
                "0",
                "0",
                "1",
                "",
                "",
                "",
                ""
            ];


            //get default cost
            //0
            $invokeIDO9 = 'SLItems';
            $invokeMethod9 = 'MisReceiptItemWhseGetCostValuesSp';
            $invokeBody9 = [
                $results[$i]['whse'],
                $results[$i]['item'],
                "",
                "",
                "",
                "",
                "",
                "",
                ""
            ];
            //1
            $invokeBody91 = [
                $results[$i + 1]['whse'],
                $results[$i + 1]['item'],
                "",
                "",
                "",
                "",
                "",
                "",
                ""
            ];
            //2
            $invokeBody92 = [
                $results[$i + 2]['whse'],
                $results[$i + 2]['item'],
                "",
                "",
                "",
                "",
                "",
                "",
                ""
            ];
            //3
            $invokeBody93 = [
                $results[$i + 3]['whse'],
                $results[$i + 3]['item'],
                "",
                "",
                "",
                "",
                "",
                "",
                ""
            ];
            //4
            $invokeBody94 = [
                $results[$i + 4]['whse'],
                $results[$i + 4]['item'],
                "",
                "",
                "",
                "",
                "",
                "",
                ""
            ];

            $client = new Client();
            
            if(count($results)%5 == 0 && $isLoopIn == 5){
                $responses[] = all([
                    //data 1
                    //0
                    "InvokeTransDate" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO1 . "?method=" . $invokeMethod1 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody1]),
                    //1
                    "LoadItem" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO2 . "?properties=" . $loadCollectionProperties2 . "&filter=" . $loadCollectionFilter2, ['headers' => ['Authorization' => $tokenData]]),
                    //2
                    "LoadWhse" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO3 . "?properties=" . $loadCollectionProperties3 . "&filter=" . $loadCollectionFilter3, ['headers' => ['Authorization' => $tokenData]]),
                    //3
                    "LoadLoc" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO4 . "?properties=" . $loadCollectionProperties4 . "&filter=" . $loadCollectionFilter4, ['headers' => ['Authorization' => $tokenData]]),
                    //4
                    "LoadReason" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO5 . "?properties=" . $loadCollectionProperties5 . "&filter=" . $loadCollectionFilter5, ['headers' => ['Authorization' => $tokenData]]),
                    //5
                    "InvokeReason" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO6 . "?method=" . $invokeMethod6 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody6]),
                    //6
                    "InvokePhysicalCount" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO7 . "?method=" . $invokeMethod7 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody7]),
                    //7
                    "InvokeObsoleteItem" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO8 . "?method=" . $invokeMethod8 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody8]),
                    //8
                    "InvokeDefaultCost" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO9 . "?method=" . $invokeMethod9 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody9]),

                    //data 2
                    "InvokeTransDate1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO1 . '?method=' . $invokeMethod1 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody11]),

                    "LoadItem1" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO2 . '?properties=' . $loadCollectionProperties2 . '&filter=' . $loadCollectionFilter21, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadWhse1" =>$client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO3 . '?properties=' . $loadCollectionProperties3 . '&filter=' . $loadCollectionFilter31, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadLoc1" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO4 . '?properties=' . $loadCollectionProperties4 . '&filter=' . $loadCollectionFilter41, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadReason1" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO5 . '?properties=' . $loadCollectionProperties5 . '&filter=' . $loadCollectionFilter51, ['headers' => ['Authorization' => $tokenData]]),

                    "InvokeReason1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO6 . '?method=' . $invokeMethod6 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody61]),

                    "InvokePhysicalCount1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO7 . '?method=' . $invokeMethod7 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody71]),

                    "InvokeObsoleteItem1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO8 . '?method=' . $invokeMethod8 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody81]),

                    "InvokeDefaultCost1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO9 . '?method=' . $invokeMethod9 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody91]),

                    //data 3
                    "InvokeTransDate2" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO1 . '?method=' . $invokeMethod1 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody12]),

                    "LoadItem2" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO2 . '?properties=' . $loadCollectionProperties2 . '&filter=' . $loadCollectionFilter22, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadWhse2" =>$client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO3 . '?properties=' . $loadCollectionProperties3 . '&filter=' . $loadCollectionFilter32, ['headers' => ['Authorization' => $tokenData]]),
                    
                    "LoadLoc2" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO4 . '?properties=' . $loadCollectionProperties4 . '&filter=' . $loadCollectionFilter42, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadReason2" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO5 . '?properties=' . $loadCollectionProperties5 . '&filter=' . $loadCollectionFilter52, ['headers' => ['Authorization' => $tokenData]]),

                    "InvokeReason2" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO6 . '?method=' . $invokeMethod6 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody62]),

                    "InvokePhysicalCount2" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO7 . '?method=' . $invokeMethod7 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody72]),

                    "InvokeObsoleteItem2" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO8 . '?method=' . $invokeMethod8 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody82]),

                    "InvokeDefaultCost2" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO9 . '?method=' . $invokeMethod9 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody92]),

                    //data 4
                    "InvokeTransDate3" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO1 . '?method=' . $invokeMethod1 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody13]),

                    "LoadItem3" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO2 . '?properties=' . $loadCollectionProperties2 . '&filter=' . $loadCollectionFilter23, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadWhse3" =>$client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO3 . '?properties=' . $loadCollectionProperties3 . '&filter=' . $loadCollectionFilter33, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadLoc3" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO4 . '?properties=' . $loadCollectionProperties4 . '&filter=' . $loadCollectionFilter43, ['headers' => ['Authorization' => $tokenData]]),
                    
                    "LoadReason3" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO5 . '?properties=' . $loadCollectionProperties5 . '&filter=' . $loadCollectionFilter53, ['headers' => ['Authorization' => $tokenData]]),

                    "InvokeReason3" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO6 . '?method=' . $invokeMethod6 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody63]),

                    "InvokePhysicalCount3" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO7 . '?method=' . $invokeMethod7 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody73]),

                    "InvokeObsoleteItem3" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO8 . '?method=' . $invokeMethod8 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody83]),

                    "InvokeDefaultCost3" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO9 . '?method=' . $invokeMethod9 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody93]),

                    //data 5
                    "InvokeTransDate4" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO1 . '?method=' . $invokeMethod1 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody14]),

                    "LoadItem4" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO2 . '?properties=' . $loadCollectionProperties2 . '&filter=' . $loadCollectionFilter24, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadWhse4" =>$client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO3 . '?properties=' . $loadCollectionProperties3 . '&filter=' . $loadCollectionFilter34, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadLoc4" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO4 . '?properties=' . $loadCollectionProperties4 . '&filter=' . $loadCollectionFilter44, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadReason4" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO5 . '?properties=' . $loadCollectionProperties5 . '&filter=' . $loadCollectionFilter54, ['headers' => ['Authorization' => $tokenData]]),

                    "InvokeReason4" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO6 . '?method=' . $invokeMethod6 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody64]),

                    "InvokePhysicalCount4" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO7 . '?method=' . $invokeMethod7 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody74]),
                    
                    "InvokeObsoleteItem4" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO8 . '?method=' . $invokeMethod8 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody84]),

                    "InvokeDefaultCost4" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO9 . '?method=' . $invokeMethod9 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody94]),
                ]);
            } else if(count($results)%4 == 0 && $isLoopIn == 4){
                $responses[] = all([
                    //data 1
                    //0
                    "InvokeTransDate" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO1 . "?method=" . $invokeMethod1 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody1]),
                    //1
                    "LoadItem" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO2 . "?properties=" . $loadCollectionProperties2 . "&filter=" . $loadCollectionFilter2, ['headers' => ['Authorization' => $tokenData]]),
                    //2
                    "LoadWhse" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO3 . "?properties=" . $loadCollectionProperties3 . "&filter=" . $loadCollectionFilter3, ['headers' => ['Authorization' => $tokenData]]),
                    //3
                    "LoadLoc" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO4 . "?properties=" . $loadCollectionProperties4 . "&filter=" . $loadCollectionFilter4, ['headers' => ['Authorization' => $tokenData]]),
                    //4
                    "LoadReason" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO5 . "?properties=" . $loadCollectionProperties5 . "&filter=" . $loadCollectionFilter5, ['headers' => ['Authorization' => $tokenData]]),
                    //5
                    "InvokeReason" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO6 . "?method=" . $invokeMethod6 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody6]),
                    //6
                    "InvokePhysicalCount" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO7 . "?method=" . $invokeMethod7 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody7]),
                    //7
                    "InvokeObsoleteItem" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO8 . "?method=" . $invokeMethod8 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody8]),
                    //8
                    "InvokeDefaultCost" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO9 . "?method=" . $invokeMethod9 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody9]),

                    //data 2
                    "InvokeTransDate1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO1 . '?method=' . $invokeMethod1 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody11]),

                    "LoadItem1" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO2 . '?properties=' . $loadCollectionProperties2 . '&filter=' . $loadCollectionFilter21, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadWhse1" =>$client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO3 . '?properties=' . $loadCollectionProperties3 . '&filter=' . $loadCollectionFilter31, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadLoc1" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO4 . '?properties=' . $loadCollectionProperties4 . '&filter=' . $loadCollectionFilter41, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadReason1" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO5 . '?properties=' . $loadCollectionProperties5 . '&filter=' . $loadCollectionFilter51, ['headers' => ['Authorization' => $tokenData]]),

                    "InvokeReason1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO6 . '?method=' . $invokeMethod6 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody61]),

                    "InvokePhysicalCount1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO7 . '?method=' . $invokeMethod7 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody71]),

                    "InvokeObsoleteItem1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO8 . '?method=' . $invokeMethod8 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody81]),

                    "InvokeDefaultCost1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO9 . '?method=' . $invokeMethod9 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody91]),

                    //data 3
                    "InvokeTransDate2" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO1 . '?method=' . $invokeMethod1 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody12]),

                    "LoadItem2" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO2 . '?properties=' . $loadCollectionProperties2 . '&filter=' . $loadCollectionFilter22, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadWhse2" =>$client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO3 . '?properties=' . $loadCollectionProperties3 . '&filter=' . $loadCollectionFilter32, ['headers' => ['Authorization' => $tokenData]]),
                    
                    "LoadLoc2" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO4 . '?properties=' . $loadCollectionProperties4 . '&filter=' . $loadCollectionFilter42, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadReason2" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO5 . '?properties=' . $loadCollectionProperties5 . '&filter=' . $loadCollectionFilter52, ['headers' => ['Authorization' => $tokenData]]),

                    "InvokeReason2" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO6 . '?method=' . $invokeMethod6 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody62]),

                    "InvokePhysicalCount2" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO7 . '?method=' . $invokeMethod7 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody72]),

                    "InvokeObsoleteItem2" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO8 . '?method=' . $invokeMethod8 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody82]),

                    "InvokeDefaultCost2" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO9 . '?method=' . $invokeMethod9 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody92]),

                    //data 4
                    "InvokeTransDate3" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO1 . '?method=' . $invokeMethod1 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody13]),

                    "LoadItem3" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO2 . '?properties=' . $loadCollectionProperties2 . '&filter=' . $loadCollectionFilter23, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadWhse3" =>$client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO3 . '?properties=' . $loadCollectionProperties3 . '&filter=' . $loadCollectionFilter33, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadLoc3" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO4 . '?properties=' . $loadCollectionProperties4 . '&filter=' . $loadCollectionFilter43, ['headers' => ['Authorization' => $tokenData]]),
                    
                    "LoadReason3" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO5 . '?properties=' . $loadCollectionProperties5 . '&filter=' . $loadCollectionFilter53, ['headers' => ['Authorization' => $tokenData]]),

                    "InvokeReason3" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO6 . '?method=' . $invokeMethod6 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody63]),

                    "InvokePhysicalCount3" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO7 . '?method=' . $invokeMethod7 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody73]),

                    "InvokeObsoleteItem3" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO8 . '?method=' . $invokeMethod8 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody83]),

                    "InvokeDefaultCost3" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO9 . '?method=' . $invokeMethod9 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody93]),
                ]);
            } else if(count($results)%3 == 0 && $isLoopIn == 3){
                $responses[] = all([
                    //data 1
                    //0
                    "InvokeTransDate" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO1 . "?method=" . $invokeMethod1 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody1]),
                    //1
                    "LoadItem" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO2 . "?properties=" . $loadCollectionProperties2 . "&filter=" . $loadCollectionFilter2, ['headers' => ['Authorization' => $tokenData]]),
                    //2
                    "LoadWhse" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO3 . "?properties=" . $loadCollectionProperties3 . "&filter=" . $loadCollectionFilter3, ['headers' => ['Authorization' => $tokenData]]),
                    //3
                    "LoadLoc" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO4 . "?properties=" . $loadCollectionProperties4 . "&filter=" . $loadCollectionFilter4, ['headers' => ['Authorization' => $tokenData]]),
                    //4
                    "LoadReason" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO5 . "?properties=" . $loadCollectionProperties5 . "&filter=" . $loadCollectionFilter5, ['headers' => ['Authorization' => $tokenData]]),
                    //5
                    "InvokeReason" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO6 . "?method=" . $invokeMethod6 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody6]),
                    //6
                    "InvokePhysicalCount" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO7 . "?method=" . $invokeMethod7 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody7]),
                    //7
                    "InvokeObsoleteItem" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO8 . "?method=" . $invokeMethod8 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody8]),
                    //8
                    "InvokeDefaultCost" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO9 . "?method=" . $invokeMethod9 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody9]),

                    //data 2
                    "InvokeTransDate1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO1 . '?method=' . $invokeMethod1 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody11]),

                    "LoadItem1" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO2 . '?properties=' . $loadCollectionProperties2 . '&filter=' . $loadCollectionFilter21, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadWhse1" =>$client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO3 . '?properties=' . $loadCollectionProperties3 . '&filter=' . $loadCollectionFilter31, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadLoc1" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO4 . '?properties=' . $loadCollectionProperties4 . '&filter=' . $loadCollectionFilter41, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadReason1" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO5 . '?properties=' . $loadCollectionProperties5 . '&filter=' . $loadCollectionFilter51, ['headers' => ['Authorization' => $tokenData]]),

                    "InvokeReason1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO6 . '?method=' . $invokeMethod6 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody61]),

                    "InvokePhysicalCount1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO7 . '?method=' . $invokeMethod7 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody71]),

                    "InvokeObsoleteItem1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO8 . '?method=' . $invokeMethod8 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody81]),

                    "InvokeDefaultCost1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO9 . '?method=' . $invokeMethod9 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody91]),

                    //data 3
                    "InvokeTransDate2" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO1 . '?method=' . $invokeMethod1 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody12]),

                    "LoadItem2" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO2 . '?properties=' . $loadCollectionProperties2 . '&filter=' . $loadCollectionFilter22, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadWhse2" =>$client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO3 . '?properties=' . $loadCollectionProperties3 . '&filter=' . $loadCollectionFilter32, ['headers' => ['Authorization' => $tokenData]]),
                    
                    "LoadLoc2" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO4 . '?properties=' . $loadCollectionProperties4 . '&filter=' . $loadCollectionFilter42, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadReason2" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO5 . '?properties=' . $loadCollectionProperties5 . '&filter=' . $loadCollectionFilter52, ['headers' => ['Authorization' => $tokenData]]),

                    "InvokeReason2" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO6 . '?method=' . $invokeMethod6 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody62]),

                    "InvokePhysicalCount2" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO7 . '?method=' . $invokeMethod7 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody72]),

                    "InvokeObsoleteItem2" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO8 . '?method=' . $invokeMethod8 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody82]),

                    "InvokeDefaultCost2" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO9 . '?method=' . $invokeMethod9 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody92]),
                ]);
            } else if(count($results)%2 == 0 && $isLoopIn == 2){
                $responses[] = all([
                    //data 1
                    //0
                    "InvokeTransDate" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO1 . "?method=" . $invokeMethod1 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody1]),
                    //1
                    "LoadItem" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO2 . "?properties=" . $loadCollectionProperties2 . "&filter=" . $loadCollectionFilter2, ['headers' => ['Authorization' => $tokenData]]),
                    //2
                    "LoadWhse" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO3 . "?properties=" . $loadCollectionProperties3 . "&filter=" . $loadCollectionFilter3, ['headers' => ['Authorization' => $tokenData]]),
                    //3
                    "LoadLoc" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO4 . "?properties=" . $loadCollectionProperties4 . "&filter=" . $loadCollectionFilter4, ['headers' => ['Authorization' => $tokenData]]),
                    //4
                    "LoadReason" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO5 . "?properties=" . $loadCollectionProperties5 . "&filter=" . $loadCollectionFilter5, ['headers' => ['Authorization' => $tokenData]]),
                    //5
                    "InvokeReason" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO6 . "?method=" . $invokeMethod6 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody6]),
                    //6
                    "InvokePhysicalCount" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO7 . "?method=" . $invokeMethod7 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody7]),
                    //7
                    "InvokeObsoleteItem" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO8 . "?method=" . $invokeMethod8 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody8]),
                    //8
                    "InvokeDefaultCost" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO9 . "?method=" . $invokeMethod9 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody9]),

                    //data 2
                    "InvokeTransDate1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO1 . '?method=' . $invokeMethod1 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody11]),

                    "LoadItem1" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO2 . '?properties=' . $loadCollectionProperties2 . '&filter=' . $loadCollectionFilter21, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadWhse1" =>$client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO3 . '?properties=' . $loadCollectionProperties3 . '&filter=' . $loadCollectionFilter31, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadLoc1" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO4 . '?properties=' . $loadCollectionProperties4 . '&filter=' . $loadCollectionFilter41, ['headers' => ['Authorization' => $tokenData]]),

                    "LoadReason1" => $client->requestAsync('GET', $config[0]->url . '/ido/load/' . $loadCollectionIDO5 . '?properties=' . $loadCollectionProperties5 . '&filter=' . $loadCollectionFilter51, ['headers' => ['Authorization' => $tokenData]]),

                    "InvokeReason1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO6 . '?method=' . $invokeMethod6 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody61]),

                    "InvokePhysicalCount1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO7 . '?method=' . $invokeMethod7 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody71]),

                    "InvokeObsoleteItem1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO8 . '?method=' . $invokeMethod8 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody81]),

                    "InvokeDefaultCost1" => $client->requestAsync('POST', $config[0]->url . '/ido/invoke/' . $invokeIDO9 . '?method=' . $invokeMethod9 . '', ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody91]),
                ]);
            }  else if(count($results)%1 == 0 && $isLoopIn == 1){
                $responses[] = all([
                    //data 1
                    //0
                    "InvokeTransDate" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO1 . "?method=" . $invokeMethod1 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody1]),
                    //1
                    "LoadItem" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO2 . "?properties=" . $loadCollectionProperties2 . "&filter=" . $loadCollectionFilter2, ['headers' => ['Authorization' => $tokenData]]),
                    //2
                    "LoadWhse" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO3 . "?properties=" . $loadCollectionProperties3 . "&filter=" . $loadCollectionFilter3, ['headers' => ['Authorization' => $tokenData]]),
                    //3
                    "LoadLoc" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO4 . "?properties=" . $loadCollectionProperties4 . "&filter=" . $loadCollectionFilter4, ['headers' => ['Authorization' => $tokenData]]),
                    //4
                    "LoadReason" => $client->requestAsync('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO5 . "?properties=" . $loadCollectionProperties5 . "&filter=" . $loadCollectionFilter5, ['headers' => ['Authorization' => $tokenData]]),
                    //5
                    "InvokeReason" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO6 . "?method=" . $invokeMethod6 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody6]),
                    //6
                    "InvokePhysicalCount" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO7 . "?method=" . $invokeMethod7 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody7]),
                    //7
                    "InvokeObsoleteItem" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO8 . "?method=" . $invokeMethod8 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody8]),
                    //8
                    "InvokeDefaultCost" => $client->requestAsync('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO9 . "?method=" . $invokeMethod9 . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody9]),
                ]);
            }
        }
        $responseData = [];
        $rowResponseData = [];
        $promiseResults = all($responses)->wait();
        for ($i = 0; $i < count($promiseResults); $i++) {
            if(count($results)%5 == 0 && $isLoopIn == 5){
                array_push($responseData, 
                    [
                        json_decode($promiseResults[$i]['InvokeTransDate']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadItem']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadWhse']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadLoc']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadReason']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeReason']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokePhysicalCount']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeObsoleteItem']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeDefaultCost']->getBody()->getContents(), true),
                    ],
                    [
                        json_decode($promiseResults[$i]['InvokeTransDate1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadItem1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadWhse1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadLoc1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadReason1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeReason1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokePhysicalCount1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeObsoleteItem1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeDefaultCost1']->getBody()->getContents(), true),
                    ],
                    [
                        json_decode($promiseResults[$i]['InvokeTransDate2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadItem2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadWhse2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadLoc2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadReason2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeReason2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokePhysicalCount2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeObsoleteItem2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeDefaultCost2']->getBody()->getContents(), true),
                    ],
                    [
                        json_decode($promiseResults[$i]['InvokeTransDate3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadItem3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadWhse3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadLoc3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadReason3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeReason3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokePhysicalCount3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeObsoleteItem3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeDefaultCost3']->getBody()->getContents(), true),
                    ],
                    [
                        json_decode($promiseResults[$i]['InvokeTransDate4']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadItem4']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadWhse4']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadLoc4']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadReason4']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeReason4']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokePhysicalCount4']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeObsoleteItem4']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeDefaultCost4']->getBody()->getContents(), true),
                    ]
                );
            } else if(count($results)%4 == 0 && $isLoopIn == 4){
                array_push($responseData, 
                    [
                        json_decode($promiseResults[$i]['InvokeTransDate']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadItem']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadWhse']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadLoc']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadReason']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeReason']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokePhysicalCount']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeObsoleteItem']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeDefaultCost']->getBody()->getContents(), true),
                    ],
                    [
                        json_decode($promiseResults[$i]['InvokeTransDate1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadItem1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadWhse1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadLoc1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadReason1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeReason1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokePhysicalCount1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeObsoleteItem1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeDefaultCost1']->getBody()->getContents(), true),
                    ],
                    [
                        json_decode($promiseResults[$i]['InvokeTransDate2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadItem2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadWhse2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadLoc2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadReason2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeReason2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokePhysicalCount2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeObsoleteItem2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeDefaultCost2']->getBody()->getContents(), true),
                    ],
                    [
                        json_decode($promiseResults[$i]['InvokeTransDate3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadItem3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadWhse3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadLoc3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadReason3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeReason3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokePhysicalCount3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeObsoleteItem3']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeDefaultCost3']->getBody()->getContents(), true),
                    ]
                );
            } else if(count($results)%3 == 0 && $isLoopIn == 3){
                array_push($responseData, 
                    [
                        json_decode($promiseResults[$i]['InvokeTransDate']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadItem']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadWhse']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadLoc']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadReason']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeReason']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokePhysicalCount']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeObsoleteItem']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeDefaultCost']->getBody()->getContents(), true),
                    ],
                    [
                        json_decode($promiseResults[$i]['InvokeTransDate1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadItem1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadWhse1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadLoc1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadReason1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeReason1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokePhysicalCount1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeObsoleteItem1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeDefaultCost1']->getBody()->getContents(), true),
                    ],
                    [
                        json_decode($promiseResults[$i]['InvokeTransDate2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadItem2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadWhse2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadLoc2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadReason2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeReason2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokePhysicalCount2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeObsoleteItem2']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeDefaultCost2']->getBody()->getContents(), true),
                    ]
                );
            } else if(count($results)%2 == 0 && $isLoopIn == 2){
                array_push($responseData, 
                    [
                        json_decode($promiseResults[$i]['InvokeTransDate']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadItem']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadWhse']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadLoc']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadReason']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeReason']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokePhysicalCount']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeObsoleteItem']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeDefaultCost']->getBody()->getContents(), true),
                    ],
                    [
                        json_decode($promiseResults[$i]['InvokeTransDate1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadItem1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadWhse1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadLoc1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadReason1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeReason1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokePhysicalCount1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeObsoleteItem1']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeDefaultCost1']->getBody()->getContents(), true),
                    ]
                );
            } else if(count($results)%1 == 0 && $isLoopIn == 1){
                array_push($responseData, 
                    [
                        json_decode($promiseResults[$i]['InvokeTransDate']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadItem']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadWhse']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadLoc']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['LoadReason']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeReason']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokePhysicalCount']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeObsoleteItem']->getBody()->getContents(), true),
                        json_decode($promiseResults[$i]['InvokeDefaultCost']->getBody()->getContents(), true),
                    ]
                );
            }
        }
        $rowResponseData = $responseData;
        
        for($i = 0; $i < count($rowResponseData); $i++){
            $messageArray = [];
            //1
            $validateTransDateResponse = $rowResponseData[$i][0];

            if ($validateTransDateResponse['ReturnValue'] != 0)
                array_push($messageArray, $validateTransDateResponse['Parameters'][3]);
            //2
            $LotTracked = '';
            $SerialTracked = '';
            $UM = '';
            $CostType = '';
            $CostMethod = '';
            $validateItemResponse = $rowResponseData[$i][1];
            if (count($validateItemResponse['Items']) == 0) {
                array_push($messageArray, 'Invalid Item, LotTracked, SerialTracked, UM');
            } else {
                $LotTracked = $validateItemResponse['Items'][0]['LotTracked'];
                $SerialTracked = $validateItemResponse['Items'][0]['SerialTracked'];
                $CostType = $validateItemResponse['Items'][0]['CostType'];
                $CostMethod = $validateItemResponse['Items'][0]['CostMethod'];
                $UM = $validateItemResponse['Items'][0]['UM'];
                if ($LotTracked == 0 && $results[$i]['lot'] != '') {
                    array_push($messageArray, 'This is not a lot tracked item, lot is not required');
                } else if ($LotTracked == 1 && $results[$i]['lot'] == '') {
                    array_push($messageArray, 'This is a lot tracked item, lot is required');
                }
            }
            //3
            $validateWhseResponse = $rowResponseData[$i][2];
            if (count($validateWhseResponse['Items']) == 0)
                array_push($messageArray, 'Invalid Item and Warehouse');
            //4
            $validateLocResponse = $rowResponseData[$i][3];
            if (count($validateLocResponse['Items']) == 0)
                array_push($messageArray, 'Invalid Location');
            //5
            $validateCheckReasonCodeResponse = $rowResponseData[$i][4];
            if (count($validateCheckReasonCodeResponse['Items']) == 0)
                array_push($messageArray, 'Invalid Reason Code');
            //6
            $Acct = '';
            $AcctUnit1 = '';
            $AcctUnit2 = '';
            $AcctUnit3 = '';
            $AcctUnit4 = '';
            $validateReasonDateResponse = $rowResponseData[$i][5];
            if ($validateReasonDateResponse['ReturnValue'] != 0) {
                array_push($messageArray, $validateReasonDateResponse['Parameters'][13]);
            } else {
                $Acct = $validateReasonDateResponse['Parameters'][3];
                $AcctUnit1 = $validateReasonDateResponse['Parameters'][4];
                $AcctUnit2 = $validateReasonDateResponse['Parameters'][5];
                $AcctUnit3 = $validateReasonDateResponse['Parameters'][6];
                $AcctUnit4 = $validateReasonDateResponse['Parameters'][7];
            }
            //7
            $validatePhysicalCountResponse = $rowResponseData[$i][6];
            $validatePhysicalCountResponse['ReturnValue'] != 0 ? array_push($messageArray, $validatePhysicalCountResponse['Parameters'][3]) : $WhsePhyInvFlg = $validatePhysicalCountResponse['Parameters'][2];
            //8
            $validateCheckObsoleteItemResponse = $rowResponseData[$i][7];
            if ($validateCheckObsoleteItemResponse['ReturnValue'] != 0)
                array_push($messageArray, $validateCheckObsoleteItemResponse['Parameters'][5]);
            //9
            $MatlCost = '';
            $LbrCost = '';
            $FovhdCost = '';
            $VovhdCost = '';
            $OutCost = '';
            $UnitCost = '';
            $validateGetDefaultCostResponse = $rowResponseData[$i][8];
            if ($validateGetDefaultCostResponse['ReturnValue'] != 0) {
                array_push($messageArray, 'Get default cost error');
            } else {
                if (($CostType == 'S' && $CostMethod = 'C')) {
                    $MatlCost = $validateGetDefaultCostResponse['Parameters'][2];
                    $LbrCost = $validateGetDefaultCostResponse['Parameters'][3];
                    $FovhdCost = $validateGetDefaultCostResponse['Parameters'][4];
                    $VovhdCost = $validateGetDefaultCostResponse['Parameters'][5];
                    $OutCost = $validateGetDefaultCostResponse['Parameters'][6];
                    $UnitCost = $validateGetDefaultCostResponse['Parameters'][7];
                } else {
                    $MatlCost = $results[$i]['matl_cost'] == "" ? 0 : $results[$i]['matl_cost'];
                    $LbrCost = $results[$i]['lbr_cost'] == "" ? 0 : $results[$i]['lbr_cost'];
                    $FovhdCost = $results[$i]['fovhd_cost'] == "" ? 0 : $results[$i]['fovhd_cost'];
                    $VovhdCost = $results[$i]['vovhd_cost'] == "" ? 0 : $results[$i]['vovhd_cost'];
                    $OutCost = $results[$i]['out_cost'] == "" ? 0 : $results[$i]['out_cost'];
                    $UnitCost = $results[$i]['matl_cost'] + $results[$i]['lbr_cost'] + $results[$i]['fovhd_cost'] + $results[$i]['vovhd_cost'] + $results[$i]['out_cost'];
                }
            }
            ($MatlCost + $LbrCost + $FovhdCost + $VovhdCost + $OutCost == 0) && $request->input('is_zero_cost') == 0 ? array_push($messageArray, 'Unit cost is zero, not allowed to process') : null;
            $costObject[] = [
                'MatlCost' => $MatlCost,
                'LbrCost' => $LbrCost,
                'FovhdCost' => $FovhdCost,
                'VovhdCost' => $VovhdCost,
                'OutCost' => $OutCost,
                'UnitCost' => $UnitCost,
            ];
            //10
            if ($results[$i]['qty_on_hand'] < 1)
                array_push($messageArray, 'Qty on hand must greater than zero');
            //11
            if ($results[$i]['reason_code'] == "")
                array_push($messageArray, 'Reason code cant be empty');

            if (count($messageArray) > 0)
            $return[] = [
                'Item' => $results[$i]['item'],
                'Message' => $messageArray
            ];

            $returnAll[] = [
                'Trans Date' => $results[$i]['trans_date'],
                'Item' => $results[$i]['item'],
                'Whse' => $results[$i]['whse'],
                'Loc' => $results[$i]['loc'],
                'Lot' => $results[$i]['lot'],
                'Qty on Hand' => $results[$i]['qty_on_hand'],
                'Expired Date' => $results[$i]['expired_date'],
                'Vendor Lot' => $results[$i]['vendor_lot'],
                'Reason Code' => $results[$i]['reason_code'],
                'Perm Flag' => $results[$i]['perm_flag'],
                'Matl Cost' => $results[$i]['matl_cost'],
                'Lbr Cost' => $results[$i]['lbr_cost'],
                'Fovhd Cost' => $results[$i]['fovhd_cost'],
                'Vovhd Cost' => $results[$i]['vovhd_cost'],
                'Out Cost' => $results[$i]['out_cost'],
                'Document Num' => $request->input('batch_id'),
                'Importdoc Id' => $results[$i]['importdoc_id'],
                'Notes' => $results[$i]['notes'],
                'Message' => $messageArray
            ];

            $allSuccess = count($messageArray) == 0 ? $allSuccess : $allSuccess + 1;
            $successObject[] = [
                'Acct' => $Acct,
                'AcctUnit1' => $AcctUnit1,
                'AcctUnit2' => $AcctUnit2,
                'AcctUnit3' => $AcctUnit3,
                'AcctUnit4' => $AcctUnit4,
                'WhsePhyInvFlg' => $WhsePhyInvFlg,
                'LotTracked' => $LotTracked,
                'SerialTracked' => $SerialTracked,
                'UM' => $UM,
            ];
        }
        $end_time = Carbon::now()->toDateTimeString();
        $time = DB::connection('mysql')->table('inventory_data_migration_log')->insertGetId(
            [
                'batch_id' => $request->input('batch_id'),
                'row_no' => -1,
                'method_name' => 'Validation',
                'start_time' => $start_time,
                'end_time' => $end_time,
                'process_duration' => gmdate("H:i:s", (strtotime($end_time) - strtotime($start_time))),
            ]
        );

        if ($allSuccess == 0) {
            for ($i = 0; $i < count($results); $i++) {
                $start_time1 = Carbon::now()->toDateTimeString();
                $time = DB::connection('mysql')->table('inventory_data_migration_log')->insertGetId(
                    [
                        'batch_id' => $request->input('batch_id'),
                        'row_no' => $i+1,
                        'method_name' => 'Insert',
                        'start_time' => $start_time1,
                        // 'end_time' => $end_time1,
                        // 'process_duration' => gmdate("H:i:s", (strtotime($end_time1) - strtotime($start_time1))),
                    ]
                );


                $messageArray = [];
                $allSuccess = 0;
                
                //load loc exist
                $loadCollectionIDO = 'SLItemLocs';
                $loadCollectionProperties = 'Item, Loc';
                $loadCollectionFilter = "Item = '" . $results[$i]['item'] . "' AND Loc = '" . $results[$i]['loc'] . "'";
                $validateItemLocExistsRes = $client->request('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
                $validateItemLocExistsResponse = json_decode($validateItemLocExistsRes->getBody()->getContents(), true);


                if (count($validateItemLocExistsResponse['Items']) == 0) {
                    $invokeIDO = 'SLItemLocs';
                    $invokeMethod = 'ItemLocAddSp';
                    $invokeBody = [
                        $results[$i]['whse'],
                        $results[$i]['item'],
                        $results[$i]['loc'],
                        "1",
                        "0",
                        "0",
                        "0",
                        "0",
                        "0",
                        "0",
                        "0",
                        "",
                        "",
                        $results[$i]['perm_flag'],
                    ];
                    $validateAddLocRes = $client->request('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO . "?method=" . $invokeMethod . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
                    $validateAddLocResponse = json_decode($validateAddLocRes->getBody()->getContents(), true);

                    if ($validateAddLocResponse['ReturnValue'] != 0) array_push($messageArray, 'Add location error');
                }


                if ($successObject[$i]['LotTracked'] == 1) {
                    // Invoke expand lot
                    if ($LotGenExp == 1) {
                        $ExpandLotResult = '';
                        $invokeIDO = 'SLPurchaseOrders';
                        $invokeMethod = 'ExpandKyByTypeSp';
                        $invokeBody = [
                            'LotType',
                            $results[$i]['lot'],
                            $config[0]->site,
                            "",
                        ];
                        $validateExpandLotRes = $client->request('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO . "?method=" . $invokeMethod . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
                        $validateExpandLotResponse = json_decode($validateExpandLotRes->getBody()->getContents(), true);
                        if ($validateExpandLotResponse['ReturnValue'] != 0) {
                            array_push($messageArray, 'Expand error');
                        } else {
                            $ExpandLotResult = $validateExpandLotResponse['Parameters'][3];
                        }
                    }

                    //load lot exists
                    $loadCollectionIDO = 'SLLots';
                    $loadCollectionProperties = 'Item, Lot';
                    $filteredLot = ($LotGenExp == 1 && $successObject[$i]['LotTracked'] == 1) ? $ExpandLotResult : $results[$i]['lot'];
                    $loadCollectionFilter = "Item = '" . $results[$i]['item'] . "' AND Lot = '" . $filteredLot . "'";
                    $validateCheckLotExistsRes = $client->request('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
                    $validateCheckLotExistsResponse = json_decode($validateCheckLotExistsRes->getBody()->getContents(), true);


                    if (count($validateCheckLotExistsResponse['Items']) == 0) {
                        // invoke add lot
                        $invokeIDO = 'SLLots';
                        $invokeMethod = 'LotAddSp';
                        $invokeBody = [
                            $results[$i]['item'],
                            ($LotGenExp == 1 && $successObject[$i]['LotTracked'] == 1) ? $ExpandLotResult : $results[$i]['lot'],
                            "0",
                            "",
                            $results[$i]['vendor_lot'],
                            "",
                            "",
                            "",
                            $config[0]->site,
                            "",
                            $results[$i]['expired_date'],
                            ""
                        ];
                        $validateAddLotRes = $client->request('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO . "?method=" . $invokeMethod . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
                        $validateAddLotResponse = json_decode($validateAddLotRes->getBody()->getContents(), true);
                        if ($validateAddLotResponse['ReturnValue'] != 0 || $validateAddLotResponse['ReturnValue'] == null) {
                            array_push($messageArray, $validateAddLotResponse['Message']);
                        }
                    }
                }

                $allSuccess = count($messageArray) == 0 ? $allSuccess : $allSuccess + 1;
                if ($allSuccess == 0) {
                    //final process
                    $invokeIDO = 'SLStockActItems';
                    $invokeMethod = 'ItemMiscReceiptSp';
                    $invokeBody = [
                        $results[$i]['item'],
                        $results[$i]['whse'],
                        $results[$i]['qty_on_hand'],
                        $successObject[$i]['UM'],
                        $costObject[$i]['MatlCost'],
                        $costObject[$i]['LbrCost'],
                        $costObject[$i]['FovhdCost'],
                        $costObject[$i]['VovhdCost'],
                        $costObject[$i]['OutCost'],
                        $costObject[$i]['UnitCost'],
                        $results[$i]['loc'],
                        ($LotGenExp == 1 && $successObject[$i]['LotTracked'] == 1) ? $ExpandLotResult : $results[$i]['lot'],
                        $results[$i]['reason_code'],
                        $successObject[$i]['Acct'],
                        $successObject[$i]['AcctUnit1'],
                        $successObject[$i]['AcctUnit2'],
                        $successObject[$i]['AcctUnit3'],
                        $successObject[$i]['AcctUnit4'],
                        $results[$i]['trans_date'],
                        "",
                        $request->input('batch_id'), //document_num <= 12 character
                        "", //ImportDocId
                        "",
                        "",
                        // ""
                    ];

                    $validateFinalProcessRes = $client->request('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO . "?method=" . $invokeMethod . "", ['headers' => ['Authorization' => $tokenData], 'json' =>     $invokeBody]);
                    $validateFinalProcessResponse = json_decode($validateFinalProcessRes->getBody()->getContents(), true);
                    $end_time1 = Carbon::now()->toDateTimeString();
                    $time = DB::connection('mysql')->table('inventory_data_migration_log')->insertGetId(
                        [
                            'batch_id' => $request->input('batch_id'),
                            'row_no' => $i+1,
                            'method_name' => 'Insert',
                            'start_time' => $start_time1,
                            'end_time' => $end_time1,
                            'process_duration' => gmdate("H:i:s", (strtotime($end_time1) - strtotime($start_time1))),
                        ]
                    );
                    if ($validateFinalProcessResponse['ReturnValue'] != 0 || $validateFinalProcessResponse['ReturnValue'] == null) {
                        $errorMessage = $validateFinalProcessResponse['Message'];
                        array_push($messageArray, $errorMessage);
                    } else {
                    }

                    $allSuccess = count($messageArray) == 0 ? $allSuccess : $allSuccess + 1;
                    if ($allSuccess == 0) {
                        $status = "Data inserted!";
                    } else {
                        $status = "Error";
                    }
                } else {
                    $status = "Error";
                }

                $returnProcess[] = [
                    'Item' => $results[$i]['item'],
                    'Message' => $messageArray
                ];
                $returnProcessAll[] = [
                    'Trans Date' => $results[$i]['trans_date'],
                    'Item' => $results[$i]['item'],
                    'Whse' => $results[$i]['whse'],
                    'Loc' => $results[$i]['loc'],
                    'Lot' => $results[$i]['lot'],
                    'Qty on Hand' => $results[$i]['qty_on_hand'],
                    'Expired Date' => $results[$i]['expired_date'],
                    'Vendor Lot' => $results[$i]['vendor_lot'],
                    'Reason Code' => $results[$i]['reason_code'],
                    'Perm Flag' => $results[$i]['perm_flag'],
                    'Matl Cost' => $results[$i]['matl_cost'],
                    'Lbr Cost' => $results[$i]['lbr_cost'],
                    'Fovhd Cost' => $results[$i]['fovhd_cost'],
                    'Vovhd Cost' => $results[$i]['vovhd_cost'],
                    'Out Cost' => $results[$i]['out_cost'],
                    'Document Num' => $request->input('batch_id'),
                    'Importdoc Id' => $results[$i]['importdoc_id'],
                    'Notes' => $results[$i]['notes'],
                    'Message' => $messageArray
                ];
            }

            $returnMessage = [
                "Status" => $status,
                "Detail" => $returnProcess,
                "DetailAll" => $returnProcessAll
            ];
            return $returnMessage;
        } else {
            $status = "Data error! Data not uploaded!";
            $returnMessage = [
                "Status" => $status,
                "Detail" => $return,
                "DetailAll" => $returnAll
            ];
            return $returnMessage;
        }
    }
}

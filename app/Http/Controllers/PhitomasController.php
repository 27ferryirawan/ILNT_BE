<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;


class PhitomasController extends Controller
{
    public function inventoryDataMigration(Request $request){
        
        $tokenClient = new Client();
        $token = $tokenClient->get('http://20.247.180.239/IDORequestService/ido/token/Demo_DALS/sa');
        $tokenData = json_decode($token->getBody()->getContents(), true)['Token'];
        // $test = DB::connection('mysql')->select("select * from PHI_ItemLocLoad");

        $allSuccess = 0;
        $reader = Excel::load($request->file('files'));             
        $results = $reader->get()->toArray();
        
        foreach($results as $data){
            $messageArray = [];
            
            // validate trans date
            $invokeClient = new Client();
            $invokeIDO = 'SLPeriods';
            $invokeMethod = 'DateChkSp';
            $invokeBody = [
                $data['trans_date'], 
                "Transaction Date", 
                "@%update", 
                "", 
                "", 
                ""
            ];
            $validateTransDateRes = $invokeClient->request('POST',"http://20.247.180.239/IDORequestService/ido/invoke/".$invokeIDO."?method=".$invokeMethod."", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
            $validateTransDateResponse = json_decode($validateTransDateRes->getBody()->getContents(), true);

            if ($validateTransDateResponse['ReturnValue'] > 0){
                $errorMessage = $validateTransDateResponse['Parameters'][3];
                array_push($messageArray, $errorMessage);
            } else {
                // $errorMessage = '';
            }

            //filter item
            $LotTracked;
            $SerialTracked;
            $UM;
            $loadCollectionClient = new Client();
            $loadCollectionIDO = 'SLItems';
            $loadCollectionProperties = 'Item, LotTracked, SerialTracked, UM';
            $loadCollectionFilter = 'Item';
            $validateItemRes = $loadCollectionClient->request('GET',"http://20.247.180.239/IDORequestService/ido/load/".$loadCollectionIDO."?properties=".$loadCollectionProperties."&filter=".$loadCollectionFilter."='".$data['item']."'", ['headers' => ['Authorization' => $tokenData]]);
            $validateItemResponse = json_decode($validateItemRes->getBody()->getContents(), true);
            
            if(count($validateItemResponse['Items']) == 0){
                $errorMessage = 'Invalid Item, LotTracked, SerialTracked, UM';
                array_push($messageArray, $errorMessage);
            } else {
                $LotTracked = $validateItemResponse['Items'][0]['LotTracked'];
                $SerialTracked = $validateItemResponse['Items'][0]['SerialTracked'];
                $UM = $validateItemResponse['Items'][0]['UM'];
                if ($LotTracked == 0 && $data['lot'] != ""){
                    $errorMessage = 'This is not a lot tracked item, lot is not required';
                    array_push($messageArray, $errorMessage);
                } else if($LotTracked == 1 && $data['lot'] == ""){
                    $errorMessage = 'This is a lot tracked item, lot is required';
                    array_push($messageArray, $errorMessage);
                }
            }

            //filter whse
            $loadCollectionClient = new Client();
            $loadCollectionIDO = 'SLItemWhses';
            $loadCollectionProperties = 'Item, Whse';
            $loadCollectionFilter = "Item = '".$data['item']."' AND Whse = '".$data['whse']."'";
            $validateWhseRes = $loadCollectionClient->request('GET',"http://20.247.180.239/IDORequestService/ido/load/".$loadCollectionIDO."?properties=".$loadCollectionProperties."&filter=".$loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
            $validateWhseResponse = json_decode($validateWhseRes->getBody()->getContents(), true);

            if(count($validateWhseResponse['Items']) == 0){
                $errorMessage = 'Invalid Item and Warehouse';
                array_push($messageArray, $errorMessage);
            } else {
                // $errorMessage = '';
            }

            //filter loc
            $loadCollectionClient = new Client();
            $loadCollectionIDO = 'SLLocations';
            $loadCollectionProperties = 'Loc';
            $loadCollectionFilter = "Loc = '".$data['loc']."'";
            $validateLocRes = $loadCollectionClient->request('GET',"http://20.247.180.239/IDORequestService/ido/load/".$loadCollectionIDO."?properties=".$loadCollectionProperties."&filter=".$loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
            $validateLocResponse = json_decode($validateLocRes->getBody()->getContents(), true);

            if(count($validateLocResponse['Items']) == 0){
                $errorMessage = 'Invalid Location';
                array_push($messageArray, $errorMessage);
            } else {
                // $errorMessage = '';
            }

            //validate qty on hand
            if($data['qty_on_hand'] < 1){
                $errorMessage = 'Qty on hand must greater than zero';
                array_push($messageArray, $errorMessage);
            } else {
                // $errorMessage = '';
            }

            // validate reason code
            $invokeClient = new Client();
            $invokeIDO = 'SLReasons';
            $invokeMethod = 'ReasonGetInvAdjAcctSp';
            $Acct;
            $AcctUnit1;
            $AcctUnit2;
            $AcctUnit3;
            $AcctUnit4;
            $invokeBody = [
                $data['reason_code'], 
                "MISC RCPT",
                $data['item'], 
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
            $validateReasonDateRes = $invokeClient->request('POST',"http://20.247.180.239/IDORequestService/ido/invoke/".$invokeIDO."?method=".$invokeMethod."", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
            $validateReasonDateResponse = json_decode($validateReasonDateRes->getBody()->getContents(), true);

            if ($validateReasonDateResponse['ReturnValue'] > 0){
                $errorMessage = $validateReasonDateResponse['Parameters'][13];
                array_push($messageArray, $errorMessage);
            } else {
                $Acct = $validateReasonDateResponse['Parameters'][3];
                $AcctUnit1 = $validateReasonDateResponse['Parameters'][4];
                $AcctUnit2 = $validateReasonDateResponse['Parameters'][5];
                $AcctUnit3 = $validateReasonDateResponse['Parameters'][6];
                $AcctUnit4 = $validateReasonDateResponse['Parameters'][7];
            }

            // validate physical count in place
            $WhsePhyInvFlg;
            $invokeClient = new Client();
            $invokeIDO = 'SLWhses';
            $invokeMethod = 'CheckWhsePhyInvFlgSp';
            $invokeBody = [
                $data['whse'],
                "LA", //HARDCODE
                "",
                "" 
            ];
            $validatePhysicalCountRes = $invokeClient->request('POST',"http://20.247.180.239/IDORequestService/ido/invoke/".$invokeIDO."?method=".$invokeMethod."", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
            $validatePhysicalCountResponse = json_decode($validatePhysicalCountRes->getBody()->getContents(), true);
        
            if ($validatePhysicalCountResponse['ReturnValue'] > 0){
                $errorMessage = $validatePhysicalCountResponse['Parameters'][3];
                array_push($messageArray, $errorMessage);
            } else {
                $WhsePhyInvFlg = $validatePhysicalCountResponse['Parameters'][2];
            }

             // validate check obsolete item
             $invokeClient = new Client();
             $invokeIDO = 'SLItems';
             $invokeMethod = 'ObsSlowSp';
             $invokeBody = [
                 $data['item'], 
                 "1", 
                 "0", 
                 "0", 
                 "1", 
                 "", 
                 "",
                 "",
                 ""
             ];
             $validateCheckObsoleteItemRes = $invokeClient->request('POST',"http://20.247.180.239/IDORequestService/ido/invoke/".$invokeIDO."?method=".$invokeMethod."", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
             $validateCheckObsoleteItemResponse = json_decode($validateCheckObsoleteItemRes->getBody()->getContents(), true);
 
             if ($validateCheckObsoleteItemResponse['ReturnValue'] > 0){
                 $errorMessage = $validateCheckObsoleteItemResponse['Parameters'][5];
                 array_push($messageArray, $errorMessage);
             } else {
                 // $errorMessage = '';
             }

            if(count($messageArray) > 0){
                $return[] = [
                    'Item' => $data['item'],
                    'Message' => $messageArray
                ];
            }

            $allSuccess = count($messageArray) == 0 ? $allSuccess : $allSuccess+1;
            $status = "Data error! Data not uploaded!";
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
        if($allSuccess == 0){
            // validate get default cost 
            
            // RVAR P(MatlCost), RVAR P(LbrCost), RVAR P(FovhdCost), RVAR P(VovhdCost), RVAR P(OutCost), RVAR P(UnitCost), Message, Severity

            $isBatchExist = false;
            $loadCollectionClient = new Client();
            $loadCollectionIDO = 'SLMatltrans';
            $loadCollectionProperties = 'DocumentNum, TransType, RefType';
            $loadCollectionFilter = "DocumentNum = '".$request->input('batch_id')."' AND TransType= 'H' AND RefType = 'I'";
            $validateBatchRes = $loadCollectionClient->request('GET',"http://20.247.180.239/IDORequestService/ido/load/".$loadCollectionIDO."?properties=".$loadCollectionProperties."&filter=".$loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
            $validateBatchResponse = json_decode($validateBatchRes->getBody()->getContents(), true);

            $isBatchExist = count($validateBatchResponse['Items']) > 0 ? true : false;

            if($isBatchExist){
                $returnProcess = [];
                $status = "Batch Number Exists";
            } else {
                for($i = 0; $i < count($results); $i++){
                    $messageArray = [];
                    $MatlCost;
                    $LbrCost;
                    $FovhdCost;
                    $VovhdCost;
                    $OutCost;
                    $UnitCost;
                    $invokeClient = new Client();
                    $invokeIDO = 'SLItems';
                    $invokeMethod = 'MisReceiptItemWhseGetCostValuesSp';
                    $invokeBody = [
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
                    $validateGetDefaultCostRes = $invokeClient->request('POST',"http://20.247.180.239/IDORequestService/ido/invoke/".$invokeIDO."?method=".$invokeMethod."", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
                    $validateGetDefaultCostResponse = json_decode($validateGetDefaultCostRes->getBody()->getContents(), true);
                    $MatlCost = $validateGetDefaultCostResponse['Parameters'][2];
                    $LbrCost = $validateGetDefaultCostResponse['Parameters'][3];
                    $FovhdCost = $validateGetDefaultCostResponse['Parameters'][4];
                    $VovhdCost = $validateGetDefaultCostResponse['Parameters'][5];
                    $OutCost = $validateGetDefaultCostResponse['Parameters'][6];
                    $UnitCost = $validateGetDefaultCostResponse['Parameters'][7];

                    //filter item loc exist
                    $loadCollectionClient = new Client();
                    $loadCollectionIDO = 'SLItemLocs';
                    $loadCollectionProperties = 'Item, Loc';
                    $loadCollectionFilter = "Item = '".$results[$i]['item']."' AND Loc = '".$results[$i]['loc']."'";
                    $validateItemLocExistsRes = $loadCollectionClient->request('GET',"http://20.247.180.239/IDORequestService/ido/load/".$loadCollectionIDO."?properties=".$loadCollectionProperties."&filter=".$loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
                    $validateItemLocExistsResponse = json_decode($validateItemLocExistsRes->getBody()->getContents(), true);
                    
                    if(count($validateItemLocExistsResponse['Items']) == 0){
                        // validate add location
                        $invokeClient = new Client();
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
                            ""
                        ];
                        $validateAddLocRes = $invokeClient->request('POST',"http://20.247.180.239/IDORequestService/ido/invoke/".$invokeIDO."?method=".$invokeMethod."", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
                        $validateAddLocResponse = json_decode($validateAddLocRes->getBody()->getContents(), true);
                    } else {
                        // $errorMessage = '';
                    }

                    if($successObject[$i]['LotTracked'] == 1){
                        //filter check lot exists
                        $loadCollectionClient = new Client();
                        $loadCollectionIDO = 'SLLots';
                        $loadCollectionProperties = 'Item, Lot';
                        $loadCollectionFilter = "Item = '".$results[$i]['item']."' AND Lot = '".$results[$i]['lot']."'";
                        $validateCheckLotExistsRes = $loadCollectionClient->request('GET',"http://20.247.180.239/IDORequestService/ido/load/".$loadCollectionIDO."?properties=".$loadCollectionProperties."&filter=".$loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
                        $validateCheckLotExistsResponse = json_decode($validateCheckLotExistsRes->getBody()->getContents(), true);
            
                        // return $validateCheckLotExistsResponse;
            
                        if(count($validateCheckLotExistsResponse['Items']) == 0){
                            
                            // validate add lot
                            $invokeClient = new Client();
                            $invokeIDO = 'SLLots';
                            $invokeMethod = 'LotAddSp';
                            $invokeBody = [
                                $results[$i]['item'], 
                                $results[$i]['lot'],
                                "",
                                "", 
                                "", 
                                "", 
                                "", 
                                "", 
                                "LA",//HARDCODE
                                "",
                                "",
                                ""
                            ];
                            $validateAddLocRes = $invokeClient->request('POST',"http://20.247.180.239/IDORequestService/ido/invoke/".$invokeIDO."?method=".$invokeMethod."", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
                            $validateAddLocResponse = json_decode($validateAddLocRes->getBody()->getContents(), true);
                        } else {
                            // $errorMessage = ''; this is not lot tracked item, lot is not required
                        }
                    }
                    //final process
                    $invokeClient = new Client();
                    $invokeIDO = 'SLStockActItems';
                    $invokeMethod = 'ItemMiscReceiptSp';
                    //  'S', <Item>,<Whse>, <Qty>, <UM>, <MatlCost>, <LbrCost>, <FovhdCost>, <VovhdCost>, <OutCost>, <UnitCost>, <Loc>, <Lot>, <ReasonCode>, <Acct>, <AcctUnit1>, <AcctUnit2>, AcctUnit3>,<AcctUnit4>, <Transdate>, , <DocumentNum>, <ImportDocId>, , , 
                    
                    $invokeBody = [
                        $results[$i]['item'], 
                        $results[$i]['whse'],
                        $results[$i]['qty_on_hand'],
                        $successObject[$i]['UM'],
                        $MatlCost,
                        $LbrCost,
                        $FovhdCost,
                        $VovhdCost,
                        $OutCost,
                        $UnitCost,
                        $results[$i]['loc'],
                        $results[$i]['lot'],
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
                    $validateFinalProcessRes = $invokeClient->request('POST',"http://20.247.180.239/IDORequestService/ido/invoke/".    $invokeIDO."?method=".$invokeMethod."", ['headers' => ['Authorization' => $tokenData], 'json' =>     $invokeBody]);
                    $validateFinalProcessResponse = json_decode($validateFinalProcessRes->getBody()->getContents(), true);
                    
                    if ($validateFinalProcessResponse['Success'] == false){
                        $errorMessage = $validateFinalProcessResponse['Message'];
                        array_push($messageArray, $errorMessage);
                    } else {
                        // $errorMessage = $validateFinalProcessResponse['Message'] == null ? "" : $validateFinalProcessResponse['Message'];
                        // array_push($messageArray, $errorMessage);
                    }

                    $returnProcess[] = [
                        'Item' => $results[$i]['item'],
                        'Message' => $messageArray
                    ];
                    $status = "Data inserted!";
                }
            }
            
            // $returnMessage["Detail"] = $returnProcess;
            $returnMessage = [
                "Status" => $status,
                "Detail" => $returnProcess
            ];
            return $returnMessage;
                
        } else {
            $returnMessage = [
                "Status" => $status,
                "Detail" => $return
            ];
            return $returnMessage;
        }
        // return $test;
    }

    public function getBatchId(){
        $batchReturn = DB::connection('mysql')->select("select distinct batch_id as name, batch_id as code from PHI_ItemLocLoad order by batch_id");

        // $result = collect($batchReturn)->pluck('batch_id')->toArray();

        // return $result;
        return $batchReturn;
    }

    public function exchangeRates(Request $request){

        $tokenClient = new Client();
        $token = $tokenClient->get('http://20.247.180.239/IDORequestService/ido/token/Demo_DALS/sa');
        $tokenData = json_decode($token->getBody()->getContents(), true)['Token'];
        if($request->input('to_currency') == "" && $request->input('from_currency') != "" ){
            $to_currency = DB::select("
            select map_curr_c   ode, curr_code
            from(
            select ROW_NUMBER ( ) OVER ( order by curr_code asc) as row_id, * from ILNT_CurrencyMap_mst ) AS  a 
            where row_id >= (
            select row_id 
            from(
            select ROW_NUMBER ( ) OVER ( order by curr_code asc) as row_id, * from ILNT_CurrencyMap_mst ) AS  a 
            where curr_code='".$request->input('from_currency')."') order by curr_code asc;");
        }else if($request->input('to_currency') != "" && $request->input('from_currency') == "" ){
            $to_currency = DB::select("
            select map_curr_code, curr_code
            from(
            select ROW_NUMBER ( ) OVER ( order by curr_code asc) as row_id, * from ILNT_CurrencyMap_mst ) AS  a 
            where row_id <= (
            select row_id 
            from(
            select ROW_NUMBER ( ) OVER ( order by curr_code asc) as row_id, * from ILNT_CurrencyMap_mst ) AS  a 
            where curr_code='".$request->input('to_currency')."') order by curr_code asc;");
        }else if($request->input('to_currency') != "" && $request->input('from_currency') != "" ){
            $to_currency = DB::select("
            select map_curr_code, curr_code
            from(
            select ROW_NUMBER ( ) OVER ( order by curr_code asc) as row_id, * from ILNT_CurrencyMap_mst ) AS  a 
            where row_id BETWEEN (
                select row_id 
                from(
                select ROW_NUMBER ( ) OVER ( order by curr_code asc) as row_id, * from ILNT_CurrencyMap_mst ) AS  a 
                where curr_code='".$request->input('from_currency')."') 
            AND
            (
                select row_id 
                from(
                select ROW_NUMBER ( ) OVER ( order by curr_code asc) as row_id, * from ILNT_CurrencyMap_mst ) AS  a 
                where curr_code='".$request->input('to_currency')."')
            order by curr_code asc;");
        }

        foreach($to_currency as $data){
            $client = new Client(['headers' => ['Accept' => 'application/vnd.BNM.API.v1+json']]);
            $quote = 'rm';
            $res = $client->get('https://api.bnm.gov.my/public/exchange-rate/'.$data->map_curr_code.'/date/'.$request->date.'?session=0900&quote='.$quote);
            $bnmData = $res->getBody()->getContents();
            $datas = json_decode($bnmData, true);

            if ($request->input('type') == 'M'){
                $SellRate = $datas['data']['rate']['middle_rate'];
                $BuyRate = $datas['data']['rate']['middle_rate'];
            } else if ($request->input('type') == 'S'){
                $SellRate = $datas['data']['rate']['selling_rate'];
                $BuyRate = $datas['data']['rate']['selling_rate'];
            } else if ($request->input('type') == 'B'){
                $SellRate = $datas['data']['rate']['buying_rate'];
                $BuyRate = $datas['data']['rate']['buying_rate'];
            } else { //x
                $SellRate = $datas['data']['rate']['selling_rate'];
                $BuyRate = $datas['data']['rate']['buying_rate'];
            }
            
            $result[] = [[
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
            ]];
        }

        foreach($result as $data){
            $changes[] = [
                'Action' => 1,
                'ItemId' => "",
                'UpdateLocking' => "1",
                'Properties' => $data
            ];
        }

        $insertBody['Changes'] = $changes;
        $insertClient =new Client();
        $insertRes = $insertClient->request('POST','http://20.247.180.239/IDORequestService/ido/update/SLCurrates?refresh=true', ['headers' => ['Authorization' => $tokenData], 'json' => $insertBody]);
        $insertResponse = json_decode($insertRes->getBody()->getContents(), true);

        return response()->json($insertResponse);
    }
}
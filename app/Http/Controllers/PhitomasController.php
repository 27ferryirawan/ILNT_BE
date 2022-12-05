<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;


class PhitomasController extends Controller
{
    public function batchDeleteConfig(Request $request)
    {

        $config = DB::connection('mysql')->table('config')->whereIn('row_id', $request->input('batch_row_id'))->delete();

        if ($config > 0) {
            $return = [
                'Status' => 'Delete Success, affected row: ' . $config,
                'Id' => $config,
                'Success' => true
            ];
        } else {
            $return = [
                'Status' => 'Delete Failed, affected row: ' . $config,
                'Id' => $config,
                'Success' => false
            ];
        }
        return $return;
    }

    public function importConfig(Request $request)
    {

        $reader = Excel::load($request->file('files'));
        $results = $reader->get()->toArray();
        $config = DB::connection('mysql')->table('config')->insert($results);
        $lastIds = DB::connection('mysql')->table('config')->orderBy('row_id', 'desc')->take(count($results))->pluck('row_id');
        $concatIds = '';
        $i = 0;

        if (count($lastIds) > 0) {
            foreach ($lastIds as $data) {
                if ($i == 0) {
                    $concatIds = $data;
                } else {
                    $concatIds = $concatIds . ', ' . $data;
                }
                $i++;
            }
        }

        if ($concatIds != "") {
            $return = [
                'Status' => 'Insert Success',
                'Id' => $concatIds,
                'Success' => true
            ];
        } else {
            $return = [
                'Status' => 'Insert Failed',
                'Id' => $concatIds,
                'Success' => false
            ];
        }
        return $return;
    }

    public function readConfig()
    {
        $config = DB::connection('mysql')->select("select *,CONCAT(site, ', ', config_name, ', ',url) as config_detail from config");

        return $config;
    }

    public function createConfig(Request $request)
    {

        $config = DB::connection('mysql')->table('config')->insertGetId(
            [
                'site' => $request->input('site'),
                'config_name' => $request->input('config_name'),
                'username' => $request->input('username'),
                'password' => $request->input('password'),
                'url' => $request->input('url')
            ]
        );

        if ($config > 0) {
            $return = [
                'Status' => 'Insert Success',
                'Id' => $config,
                'Success' => true
            ];
        } else {
            $return = [
                'Status' => 'Insert Failed',
                'Id' => $config,
                'Success' => false
            ];
        }
        return $return;
    }

    public function updateConfig(Request $request)
    {

        $config = DB::connection('mysql')->update("
        update config set 
        site = '" . $request->input('site') . "', 
        config_name = '" . $request->input('config_name') . "', 
        username = '" . $request->input('username') . "', 
        password = '" . $request->input('password') . "', 
        url = '" . $request->input('url') . "'
        where row_id = " . $request->input('row_id'));

        if ($config > 0) {
            $return = [
                'Status' => 'Update Success, affected row: ' . $config,
                'Id' => $config,
                'Success' => true
            ];
        } else {
            $return = [
                'Status' => 'Update Failed, affected row: ' . $config,
                'Id' => '',
                'Success' => false
            ];
        }
        return $return;
    }

    public function deleteConfig(Request $request)
    {

        $config = DB::connection('mysql')->delete("delete from config where row_id = " . $request->input('row_id'));

        if ($config > 0) {
            $return = [
                'Status' => 'Delete Success, affected row: ' . $config,
                'Id' => '',
                'Success' => true
            ];
        } else {
            $return = [
                'Status' => 'Delete Failed, affected row: ' . $config,
                'Id' => '',
                'Success' => false
            ];
        }
        return $return;
    }

    public function inventoryDataMigration(Request $request){

        $config = DB::connection('mysql')->select("select * from config where row_id =" . $request->input('config_id'));
        $tokenClient = new Client();
        $token = $tokenClient->get($config[0]->url . "/ido/token/" . $config[0]->config_name . "/" . $config[0]->username . "/" . $config[0]->password);
        $tokenData = json_decode($token->getBody()->getContents(), true)['Token'];
        
        //object.UniqueLot
        //object.LotGenExp
        //SL.SLInvparms
        //filter ParmKey = 0
        $validateCount = 0;
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
        

        foreach ($results as $data) {
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
            
            $validateTransDateRes = $invokeClient->request('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO . "?method=" . $invokeMethod . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
            $validateTransDateResponse = json_decode($validateTransDateRes->getBody()->getContents(), true);
    
            // if ($validateTransDateResponse['ReturnValue'] != 0){
            //     $errorMessage = $validateTransDateResponse['Parameters'][3];
            //     array_push($messageArray, $errorMessage);
            // } 

            if ($validateTransDateResponse['ReturnValue'] != 0)
                array_push($messageArray, $validateTransDateResponse['Parameters'][3]);

            //filter item
            $LotTracked = '';
            $SerialTracked = '';
            $UM = '';
            $CostType = '';
            $CostMethod = '';
            $loadCollectionClient = new Client();
            $loadCollectionIDO = 'SLItems';
            $loadCollectionProperties = 'Item, LotTracked, SerialTracked, UM, CostType, CostMethod';
            $loadCollectionFilter = 'Item';
            $validateItemRes = $loadCollectionClient->request('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter . "='" . $data['item'] . "'", ['headers' => ['Authorization' => $tokenData]]);
            $validateItemResponse = json_decode($validateItemRes->getBody()->getContents(), true);
      
            if (count($validateItemResponse['Items']) == 0) {
                // $errorMessage = 'Invalid Item, LotTracked, SerialTracked, UM';
                // array_push($messageArray, $errorMessage);
                array_push($messageArray, 'Invalid Item, LotTracked, SerialTracked, UM');
            } else {
                $LotTracked = $validateItemResponse['Items'][0]['LotTracked'];
                $SerialTracked = $validateItemResponse['Items'][0]['SerialTracked'];
                $CostType = $validateItemResponse['Items'][0]['CostType'];
                $CostMethod = $validateItemResponse['Items'][0]['CostMethod'];
                $UM = $validateItemResponse['Items'][0]['UM'];
                if ($LotTracked == 0 && $data['lot'] != "") {
                    // $errorMessage = 'This is not a lot tracked item, lot is not required';
                    // array_push($messageArray, $errorMessage);
                    array_push($messageArray, 'This is not a lot tracked item, lot is not required');
                } else if ($LotTracked == 1 && $data['lot'] == "") {
                    // $errorMessage = 'This is a lot tracked item, lot is required';
                    // array_push($messageArray, $errorMessage);
                    array_push($messageArray, 'This is a lot tracked item, lot is required');
                }
            }

            //filter whse
            $loadCollectionClient = new Client();
            $loadCollectionIDO = 'SLItemWhses';
            $loadCollectionProperties = 'Item, Whse';
            $loadCollectionFilter = "Item = '" . $data['item'] . "' AND Whse = '" . $data['whse'] . "'";
            $validateWhseRes = $loadCollectionClient->request('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
            $validateWhseResponse = json_decode($validateWhseRes->getBody()->getContents(), true);

            // if(count($validateWhseResponse['Items']) == 0){
            //     $errorMessage = 'Invalid Item and Warehouse';
            //     array_push($messageArray, $errorMessage);
            // } 
            if (count($validateWhseResponse['Items']) == 0)
                array_push($messageArray, 'Invalid Item and Warehouse');

            //filter loc
            $loadCollectionClient = new Client();
            $loadCollectionIDO = 'SLLocations';
            $loadCollectionProperties = 'Loc';
            $loadCollectionFilter = "Loc = '" . $data['loc'] . "'";
            $validateLocRes = $loadCollectionClient->request('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
            $validateLocResponse = json_decode($validateLocRes->getBody()->getContents(), true);

            // if(count($validateLocResponse['Items']) == 0){
            //     $errorMessage = 'Invalid Location';
            //     array_push($messageArray, $errorMessage);
            // } 

            if (count($validateLocResponse['Items']) == 0)
                array_push($messageArray, 'Invalid Location');

            //validate qty on hand
            // if($data['qty_on_hand'] < 1){
            //     $errorMessage = 'Qty on hand must greater than zero';
            //     array_push($messageArray, $errorMessage);
            // } 

            if ($data['qty_on_hand'] < 1)
                array_push($messageArray, 'Qty on hand must greater than zero');

            if ($data['reason_code'] == "")
                array_push($messageArray, 'Reason code cant be empty');

            $loadCollectionClient = new Client();
            $loadCollectionIDO = 'SLReasons';
            $loadCollectionProperties = 'ReasonCode,Description';['lot'];
            $loadCollectionFilter = "ReasonClass = 'MISC RCPT' AND ReasonCode = '" .$data['reason_code']. "'";
            $validateCheckLotExistsRes = $loadCollectionClient->request('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
            $validateCheckReasonCodeResponse = json_decode($validateCheckLotExistsRes->getBody()->getContents(), true);
            
            if (count($validateCheckReasonCodeResponse['Items']) == 0) {
                array_push($messageArray, 'Invalid Reason Code');
            }


            // validate reason code
            $invokeClient = new Client();
            $invokeIDO = 'SLReasons';
            $invokeMethod = 'ReasonGetInvAdjAcctSp';
            $Acct = '';
            $AcctUnit1 = '';
            $AcctUnit2 = '';
            $AcctUnit3 = '';
            $AcctUnit4 = '';
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
            $validateReasonDateRes = $invokeClient->request('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO . "?method=" . $invokeMethod . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
            $validateReasonDateResponse = json_decode($validateReasonDateRes->getBody()->getContents(), true);

            if ($validateReasonDateResponse['ReturnValue'] != 0) {
                // $errorMessage = $validateReasonDateResponse['Parameters'][13];
                // array_push($messageArray, $errorMessage);
                array_push($messageArray, $validateReasonDateResponse['Parameters'][13]);
            } else {
                $Acct = $validateReasonDateResponse['Parameters'][3];
                $AcctUnit1 = $validateReasonDateResponse['Parameters'][4];
                $AcctUnit2 = $validateReasonDateResponse['Parameters'][5];
                $AcctUnit3 = $validateReasonDateResponse['Parameters'][6];
                $AcctUnit4 = $validateReasonDateResponse['Parameters'][7];
            }

            // validate physical count in place
            $WhsePhyInvFlg = false;
            $invokeClient = new Client();
            $invokeIDO = 'SLWhses';
            $invokeMethod = 'CheckWhsePhyInvFlgSp';
            $invokeBody = [
                $data['whse'],
                $config[0]->site,
                "",
                ""
            ];
            $validatePhysicalCountRes = $invokeClient->request('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO . "?method=" . $invokeMethod . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
            $validatePhysicalCountResponse = json_decode($validatePhysicalCountRes->getBody()->getContents(), true);

            // if ($validatePhysicalCountResponse['ReturnValue'] != 0){
            //     $errorMessage = $validatePhysicalCountResponse['Parameters'][3];
            //     array_push($messageArray, $errorMessage);
            // } else {
            //     $WhsePhyInvFlg = $validatePhysicalCountResponse['Parameters'][2];
            // }

            $validatePhysicalCountResponse['ReturnValue'] != 0 ? array_push($messageArray, $validatePhysicalCountResponse['Parameters'][3]) : $WhsePhyInvFlg = $validatePhysicalCountResponse['Parameters'][2];

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
            $validateCheckObsoleteItemRes = $invokeClient->request('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO . "?method=" . $invokeMethod . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
            $validateCheckObsoleteItemResponse = json_decode($validateCheckObsoleteItemRes->getBody()->getContents(), true);

            //  if ($validateCheckObsoleteItemResponse['ReturnValue'] != 0){
            //      $errorMessage = $validateCheckObsoleteItemResponse['Parameters'][5];
            //      array_push($messageArray, $errorMessage);
            //  } 

            if ($validateCheckObsoleteItemResponse['ReturnValue'] != 0)
                array_push($messageArray, $validateCheckObsoleteItemResponse['Parameters'][5]);

            // validate get default cost  
            //if costtype = 'S' AND costmethod = 'C' or excel matlcost empty
            //get all default cost from step 8
            // RVAR P(MatlCost), RVAR P(LbrCost), RVAR P(FovhdCost), RVAR P(VovhdCost), RVAR P(OutCost), RVAR P(UnitCost), Message, Severity
            //if total all cost except unitcost = 0 AND flag allowed zero = 0 and return error message unit cost is zero, not allowed to process

            $MatlCost = '';
            $LbrCost = '';
            $FovhdCost = '';
            $VovhdCost = '';
            $OutCost = '';
            $UnitCost = '';
            $invokeClient = new Client();
            $invokeIDO = 'SLItems';
            $invokeMethod = 'MisReceiptItemWhseGetCostValuesSp';
            $invokeBody = [
                $data['whse'],
                $data['item'],
                "",
                "",
                "",
                "",
                "",
                "",
                ""
            ];
            $validateGetDefaultCostRes = $invokeClient->request('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO . "?method=" . $invokeMethod . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
            $validateGetDefaultCostResponse = json_decode($validateGetDefaultCostRes->getBody()->getContents(), true);
            
            if ($validateGetDefaultCostResponse['ReturnValue'] != 0){
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
                    $MatlCost = $data['matl_cost'] == "" ? 0 : $data['matl_cost'];
                    $LbrCost = $data['lbr_cost'] == "" ? 0 : $data['lbr_cost'];
                    $FovhdCost = $data['fovhd_cost'] == "" ? 0 : $data['fovhd_cost'];
                    $VovhdCost = $data['vovhd_cost'] == "" ? 0 : $data['vovhd_cost'];
                    $OutCost = $data['out_cost'] == "" ? 0 : $data['out_cost'];
                    $UnitCost = $data['matl_cost'] + $data['lbr_cost'] + $data['fovhd_cost'] + $data['vovhd_cost'] + $data['out_cost'];
                }
            }
            $costObject[] = [
                'MatlCost' => $MatlCost,
                'LbrCost' => $LbrCost,
                'FovhdCost' => $FovhdCost,
                'VovhdCost' => $VovhdCost,
                'OutCost' => $OutCost,
                'UnitCost' => $UnitCost,
            ];
            // if (($MatlCost + $LbrCost + $FovhdCost + $VovhdCost + $OutCost == 0) && $request->input('is_zero_cost') == 0){
            //     $errorMessage = 'Unit cost is zero, not allowed to process';
            //     array_push($messageArray, $errorMessage);
            // }

            ($MatlCost + $LbrCost + $FovhdCost + $VovhdCost + $OutCost == 0) && $request->input('is_zero_cost') == 0 ? array_push($messageArray, 'Unit cost is zero, not allowed to process') : null;

            if (count($messageArray) > 0)
                $return[] = [
                    'Item' => $data['item'],
                    'Message' => $messageArray
                ];


            $returnAll[] = [
                'Trans Date' => $data['trans_date'],
                'Item' => $data['item'],
                'Whse' => $data['whse'],
                'Loc' => $data['loc'],
                'Lot' => $data['lot'],
                'Qty on Hand' => $data['qty_on_hand'],
                'Expired Date' => $data['expired_date'],
                'Vendor Lot' => $data['vendor_lot'],
                'Reason Code' => $data['reason_code'],
                'Perm Flag' => $data['perm_flag'],
                'Matl Cost' => $data['matl_cost'],
                'Lbr Cost' => $data['lbr_cost'],
                'Fovhd Cost' => $data['fovhd_cost'],
                'Vovhd Cost' => $data['vovhd_cost'],
                'Out Cost' => $data['out_cost'],
                'Document Num' => $request->input('batch_id'),
                'Importdoc Id' => $data['importdoc_id'],
                'Notes' => $data['notes'],
                'Message' => $messageArray
            ];

            $allSuccess = count($messageArray) == 0 ? $allSuccess : $allSuccess + 1;
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
            $validateCount++;
        }
        
        if ($allSuccess == 0) {
            $isBatchExist = false;
            $loadCollectionClient = new Client();
            $loadCollectionIDO = 'SLMatltrans';
            $loadCollectionProperties = 'DocumentNum, TransType, RefType';
            $loadCollectionFilter = "DocumentNum = '" . $request->input('batch_id') . "' AND TransType= 'H' AND RefType = 'I'";
            $validateBatchRes = $loadCollectionClient->request('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
            $validateBatchResponse = json_decode($validateBatchRes->getBody()->getContents(), true);

            $isBatchExist = count($validateBatchResponse['Items']) > 0 ? true : false;

            if ($isBatchExist) {
                $returnProcess = [];
                $returnProcessAll = [];
                $status = "Batch Number Exists";
            } else {
                for ($i = 0; $i < count($results); $i++) {

                    $messageArray = [];
                    $allSuccess = 0;
                    //filter item loc exist
                    $loadCollectionClient = new Client();
                    $loadCollectionIDO = 'SLItemLocs';
                    $loadCollectionProperties = 'Item, Loc';
                    $loadCollectionFilter = "Item = '" . $results[$i]['item'] . "' AND Loc = '" . $results[$i]['loc'] . "'";
                    $validateItemLocExistsRes = $loadCollectionClient->request('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
                    $validateItemLocExistsResponse = json_decode($validateItemLocExistsRes->getBody()->getContents(), true);

                    if (count($validateItemLocExistsResponse['Items']) == 0) {
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
                            "",
                            $results[$i]['perm_flag'],
                        ];
                        $validateAddLocRes = $invokeClient->request('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO . "?method=" . $invokeMethod . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
                        $validateAddLocResponse = json_decode($validateAddLocRes->getBody()->getContents(), true);

                        if($validateAddLocResponse['ReturnValue'] != 0) array_push($messageArray, 'Add location error');
                    }
                    

                    if ($successObject[$i]['LotTracked'] == 1) {

                        // validate expand lot
                        // if LotGenExp == 1
                        //ExpandKyByTypeSp 

                        // DataType == LotType
                        // Key == LotNum excel
                        // Site == site config
                        // Result => lotNumber to final process
                        // if not equal 1, lot num from excel
                        if($LotGenExp == 1){
                            $ExpandLotResult = '';
                            $invokeClient = new Client();
                            $invokeIDO = 'SLPurchaseOrders';
                            $invokeMethod = 'ExpandKyByTypeSp';
                            $invokeBody = [
                                'LotType',
                                $results[$i]['lot'],
                                $config[0]->site,
                                "",
                            ];
                            $validateExpandLotRes = $invokeClient->request('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO . "?method=" . $invokeMethod . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
                            $validateExpandLotResponse = json_decode($validateExpandLotRes->getBody()->getContents(), true);
                            if($validateExpandLotResponse['ReturnValue'] != 0){
                                array_push($messageArray, 'Expand error');
                            }else{
                                $ExpandLotResult = $validateExpandLotResponse['Parameters'][3];
                            }
                        }

                        //filter check lot exists
                        $loadCollectionClient = new Client();
                        $loadCollectionIDO = 'SLLots';
                        $loadCollectionProperties = 'Item, Lot';
                        $filteredLot = ($LotGenExp == 1 && $successObject[$i]['LotTracked'] == 1) ? $ExpandLotResult : $results[$i]['lot'];
                        $loadCollectionFilter = "Item = '" . $results[$i]['item'] . "' AND Lot = '" .$filteredLot. "'";
                        $validateCheckLotExistsRes = $loadCollectionClient->request('GET', $config[0]->url . "/ido/load/" . $loadCollectionIDO . "?properties=" . $loadCollectionProperties . "&filter=" . $loadCollectionFilter, ['headers' => ['Authorization' => $tokenData]]);
                        $validateCheckLotExistsResponse = json_decode($validateCheckLotExistsRes->getBody()->getContents(), true);
                        

                        if (count($validateCheckLotExistsResponse['Items']) == 0) {
                            
                            
                            // validate add lot
                            $invokeClient = new Client();
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
                            $validateAddLocRes = $invokeClient->request('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO . "?method=" . $invokeMethod . "", ['headers' => ['Authorization' => $tokenData], 'json' => $invokeBody]);
                            $validateAddLotResponse = json_decode($validateAddLocRes->getBody()->getContents(), true);
                            if($validateAddLotResponse['ReturnValue'] != 0 || $validateAddLotResponse['ReturnValue'] == null ){
                                array_push($messageArray, $validateAddLotResponse['Message']);
                            } 
                        }
                    }

                    $allSuccess = count($messageArray) == 0 ? $allSuccess : $allSuccess + 1;
                    if($allSuccess == 0){
                        //final process
                        $invokeClient = new Client();
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

                        $validateFinalProcessRes = $invokeClient->request('POST', $config[0]->url . "/ido/invoke/" . $invokeIDO . "?method=" . $invokeMethod . "", ['headers' => ['Authorization' => $tokenData], 'json' =>     $invokeBody]);
                        $validateFinalProcessResponse = json_decode($validateFinalProcessRes->getBody()->getContents(), true);

                        if ($validateFinalProcessResponse['ReturnValue'] != 0 || $validateFinalProcessResponse['ReturnValue'] == null) {
                            $errorMessage = $validateFinalProcessResponse['Message'];
                            array_push($messageArray, $errorMessage);
                        } else {
                            // $errorMessage = $validateFinalProcessResponse['Message'] == null ? "" : $validateFinalProcessResponse['Message'];
                            // array_push($messageArray, $errorMessage);
                        }
                        $allSuccess = count($messageArray) == 0 ? $allSuccess : $allSuccess + 1;
                        if($allSuccess == 0){
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
            }

            // $returnMessage["Detail"] = $returnProcess;
            $returnMessage = [
                "Status" => $status,
                "Detail" => $returnProcess,
                "DetailAll" => $returnProcessAll
            ];
            return $returnMessage;
        } else {
            $returnMessage = [
                "Status" => $status,
                "Detail" => $return,
                "DetailAll" => $returnAll
            ];
            return $returnMessage;
        }
        // return $test;
    }

    public function getBatchId()
    {
        $batchReturn = DB::connection('mysql')->select("select distinct batch_id as name, batch_id as code from PHI_ItemLocLoad order by batch_id");

        // $result = collect($batchReturn)->pluck('batch_id')->toArray();

        // return $result;
        return $batchReturn;
    }

    public function exchangeRates(Request $request)
    { 
        $tokenClient = new Client();
        $token = $tokenClient->get('http://20.247.180.239/IDORequestService/ido/token/Demo_DALS/sa');
        $tokenData = json_decode($token->getBody()->getContents(), true)['Token'];
        if ($request->input('to_currency') == "" && $request->input('from_currency') != "") {
            $to_currency = DB::select("
            select map_curr_c   ode, curr_code
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
            $client = new Client(['headers' => ['Accept' => 'application/vnd.BNM.API.v1+json']]);
            $quote = 'rm';
            // dd('https://api.bnm.gov.my/public/exchange-rate/' . $data->map_curr_code . '/date/' . $request->date . '?session=0900&quote=' . $quote);
            $res = $client->get('https://api.bnm.gov.my/public/exchange-rate/' . $data->map_curr_code . '/date/' . $request->date . '?session=0900&quote=' . $quote);
            ;
            $bnmData = $res->getBody()->getContents();
            $datas = json_decode($bnmData, true);

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
        return($insertBody);
        $insertClient = new Client();
        $insertRes = $insertClient->request('POST', 'http://20.247.180.239/IDORequestService/ido/update/SLCurrates?refresh=true', ['headers' => ['Authorization' => $tokenData], 'json' => $insertBody]);
        $insertResponse = json_decode($insertRes->getBody()->getContents(), true);

        return response()->json($insertResponse);
    }
}

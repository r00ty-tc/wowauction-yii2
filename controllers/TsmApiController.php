<?php

namespace app\controllers;

use app\models\Factions;
use app\models\ItemPrices;
use app\models\Realm;
use yii\base\Controller;

class TsmApiController extends Controller
{
    public function actionGetRealms(){
        $realms = Realm::find()->all();
        $result = [];
        $result['servers'] =[];
	foreach($realms as $realm){
            if(!isset($result['servers'][$realm->server->name])){
                $result['servers'][$realm->server->name] = [];
		$result['servers'][$realm->server->name]['realms'] = [];
	    }
	    $thisRealm = [];
	    $thisRealm['realm'] = $realm->name;
	    $result['servers'][$realm->server->name]['realms'][] = $thisRealm;
	}
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
	return $result;
    }
    public function actionGetScans(){

        if(!isset($_GET['faction']))
            die("no faction");

        if(!isset($_GET['realm']))
		die("no realm");

	$lastScan = 0;
	if(isset($_GET['lastscan']))
	    $lastScan = $_GET['lastscan'];
        $realm = $_GET['realm'];
        $factionid = $_GET['faction'];
        if($factionid == "h")
            $factionid = 1;
        else
	    $factionid = 2;

        $faction= Factions::findOne(['id'=>$factionid]);
        if($faction == NULL){
            return json_encode(['result'=>'error','message'=>'Faction Not Found!']);
        }

        $realm = Realm::findOne(['name'=>$realm]);
        if($realm == NULL){
            die("realm not found");
	}

	$connection = \Yii::$app->getDb();
	$command = $connection->createCommand("SELECT DISTINCT(datetime) FROM item_prices WHERE realm_id = :realmid AND faction_id = :factionid AND datetime > from_unixtime(:lastscan) ORDER BY datetime", [':realmid' => $realm->id,':factionid' => $faction->id,':lastscan' => $lastScan]);
	$result = [];
	$result['scans'] = [];
	$queryResult = $command->queryAll();
	foreach ($queryResult as $scan) {
            $scanItem = [];
            $dt = new \DateTime($scan['datetime']);
	    $scanItem['scantime'] = $dt->getTimestamp();
            $result['scans'][] = $scanItem;
	}
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $result;
        
    }

    public function actionGetScanData(){
        if(!isset($_GET['faction']))
            die("no faction");

        if(!isset($_GET['realm']))
            die("no realm");
        $realm = $_GET['realm'];
        $factionid = $_GET['faction'];
        if($factionid == "h")
            $factionid = 1;
        else
            $factionid = 2;

    	$scantime = 0;
	    if(isset($_GET['scantime']))
            $scantime = $_GET['scantime'];

        $faction= Factions::findOne(['id'=>$factionid]);
        if($faction == NULL){
            return json_encode(['result'=>'error','message'=>'Faction Not Found!']);
        }

        $realm = Realm::findOne(['name'=>$realm]);
        if($realm == NULL){
            die("realm not found");
        }

	    if ($scantime == 0){
            $connection = \Yii::$app->getDb();
	        $command = $connection->createCommand("SELECT datetime FROM item_prices WHERE realm_id = :realmid  AND faction_id = :factionid ORDER BY datetime DESC LIMIT 1", [':realmid' => $realm->id,':factionid' => $faction->id]);
	        $queryResult = $command->queryOne();
	        $dt = new \DateTime($queryResult['datetime']);
	        $scantime = $dt->getTimestamp();
	    }
        $qry = "select * from item_prices where realm_id=:realm and faction_id=:faction and `datetime`=from_unixtime(:scantime) group by itemid";
        $ip = ItemPrices::findBySql($qry,[':realm'=>$realm->id,':faction'=>$faction->id,':scantime'=>$scantime])->all();
        if(count($ip)==0){
            return json_encode(['result'=>'error','message'=>'No data found for '.$scantime.'!']);
        }
        else {
            $out = [];
            $out['result'] = 'OK';
            $out['realm_name'] = $realm->name;
            $out['server_name'] = $realm->server->name;
            $out['faction'] = $faction->name;
            $out['data'] = [];
            $first = true;
            foreach($ip as $price){
                if ($first){
                    $dt = new \DateTime($price->datetime);
                    $out['scan_time'] = $dt->getTimestamp();
                    $first = false;
                }
                $item['b'] = $price->buyout_min;
                $item['m'] = $price->buyout_median;
                $item['q'] = $price->quantity;
                $out['data'][strtolower($faction->name)][$price->itemid] = $item;
            }
            return '["'.$realm->name.'-'.$faction->name.'-'.$out['scan_time'].'"] = '."'".json_encode($out['data'])."'";
	    }
    }
}

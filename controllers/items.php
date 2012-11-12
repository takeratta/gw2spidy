<?php

use GW2Spidy\Dataset\ItemVolumeDataset;

use GW2Spidy\Dataset\ItemDataset;

use GW2Spidy\Dataset\DatasetManager;

use \DateTime;

use GW2Spidy\Application;
use Symfony\Component\HttpFoundation\Request;

use GW2Spidy\DB\DisciplineQuery;
use GW2Spidy\DB\ItemSubTypeQuery;
use GW2Spidy\DB\ItemType;
use GW2Spidy\DB\RecipeQuery;
use GW2Spidy\DB\GW2Session;
use GW2Spidy\DB\GoldToGemRateQuery;
use GW2Spidy\DB\GemToGoldRateQuery;
use GW2Spidy\DB\ItemQuery;
use GW2Spidy\DB\ItemTypeQuery;
use GW2Spidy\DB\SellListingQuery;
use GW2Spidy\DB\WorkerQueueItemQuery;
use GW2Spidy\DB\ItemPeer;
use GW2Spidy\DB\BuyListingPeer;
use GW2Spidy\DB\SellListingPeer;
use GW2Spidy\DB\BuyListingQuery;

use GW2Spidy\Util\Functions;

/**
 * ----------------------
 *  route /types
 * ----------------------
 */
$app->get("/types", function() use($app) {
    $types = ItemTypeQuery::getAllTypes();

    return $app['twig']->render('types.html.twig', array(
        'types' => $types,
    ));
})
->bind('types');

/**
 * ----------------------
 *  route /type
 * ----------------------
 */
$app->get("/type/{type}/{subtype}/{page}", function(Request $request, $type, $subtype, $page) use($app) {
    $page = $page > 0 ? $page : 1;

    $q = ItemQuery::create();

    if ($type == -1) {
        $type = null;
    }
    if ($subtype == -1) {
        $subtype = null;
    }

    if (!is_null($type)) {
        if (!($type = ItemTypeQuery::create()->findPk($type))) {
            return $app->abort(404, "bad type");
        }
        $q->filterByItemType($type);

        if (!is_null($subtype)) {
            if (!($subtype = ItemSubTypeQuery::create()->findPk(array($subtype, $type->getId())))) {
                return $app->abort(404, "bad type");
            }
            $q->filterByItemSubType($subtype);
        }
    }

    // use generic function to render
    return item_list($app, $request, $q, $page, 50, array('type' => $type, 'subtype' => $subtype));
})
->assert('type',     '-?\d*')
->assert('subtype',  '-?\d*')
->assert('page',     '-?\d*')
->value('type',      -1)
->value('subtype',   -1)
->value('page',      1)
->bind('type');

/**
 * ----------------------
 *  route /item
 * ----------------------
 */
$app->get("/item/{dataId}", function($dataId) use ($app) {
    $item = ItemQuery::create()->findPK($dataId);

    if (!$item) {
        return $app->abort(404, "Page does not exist.");
    }

    return $app['twig']->render('item.html.twig', array(
        'item'        => $item,
    ));
})
->assert('dataId',  '\d+')
->bind('item');

/**
 * ----------------------
 *  route /chart
 * ----------------------
 */
$app->get("/chart/{dataId}", function($dataId) use ($app) {
    $con  = \Propel::getConnection();
    $item = ItemQuery::create()->findPK($dataId);

    if (!$item) {
        return $app->abort(404, "Page does not exist.");
    }

    $chart = array();
    $sellListing['price']  = array();
    $sellListing['volume'] = array();
    $buyListing['price']   = array();
    $buyListing['volume']  = array();

    $sql = "SELECT
                id, UNIX_TIMESTAMP(listing_datetime) AS ts, unit_price AS unitPrice, listings, quantity
            FROM [table]
            WHERE item_id = {$dataId}
            ORDER BY listing_datetime ASC";
    $stmt = $con->prepare(str_replace('[table]', 'sell_listing', $sql));

    $stmt->execute();
    $listings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($listings as $listing) {
        $ts = $listing['ts'];

        if (!$ts) {
            continue;
        }

        $sellListing['price'][]  = array($ts * 1000, intval($listing['unitPrice']));
        $sellListing['volume'][] = array($ts * 1000, intval($listing['quantity']));
    }

    $stmt = $con->prepare(str_replace('[table]', 'buy_listing', $sql));

    $stmt->execute();
    $listings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($listings as $listing) {
        $ts = $listing['ts'];

        $buyListing['price'][]  = array($ts * 1000, intval($listing['unitPrice']));
        $buyListing['volume'][] = array($ts * 1000, intval($listing['quantity']));
    }

    /*----------------
     *  SELL LISTINGS
    *----------------*/
    $chart[] = array(
        'data'     => $sellListing['price'],
        'name'     => "Sell Listings Raw Data",
        'visible'  => true,
        'gw2money' => true,
    );
    $chart[] = array(
        'data'     => $sellListing['volume'],
        'name'     => "Sell Listings Volume",
        'visible'  => true,
        'gw2money' => false,
        'type'    => 'column',
        'yAxis'    => 1,
    );

    /*----------------
     *  BUY LISTINGS
     *----------------*/
    $chart[] = array(
        'data'     => $sellListing['price'],
        'name'     => "Buy Listings Raw Data",
    	'visible'  => true,
        'gw2money' => true,
    );
    $chart[] = array(
        'data'     => $sellListing['volume'],
        'name'     => "Buy Listings Volume",
        'visible'  => true,
        'gw2money' => false,
        'type'    => 'column',
        'yAxis'    => 1,
    );

    $content = json_encode($chart);

    return $content;
})
->assert('dataId',  '\d+')
->bind('chart');


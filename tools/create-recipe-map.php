<?php
use GW2Spidy\Util\CurlRequest;
use GW2Spidy\DB\ItemQuery;

ini_set('memory_limit', '1G');

require dirname(__FILE__) . '/../autoload.php';

class FailedImportException extends Exception {}
class NoResultItemException extends FailedImportException {}
class NoIngredientItemException extends FailedImportException {}

$recipe_list = new ArrayObject();

//Allows one-liner ingredient list adding.
class Ingredient {
    public $ItemID;
    public $Count;

    public function __construct($ItemID, $Count) {
        $this->ItemID = $ItemID;
        $this->Count = $Count;
    }
}

/*
Target output style

{
    "0":{
        "DataID":1275,
        "Name":"Resilient Seeker Coat",
        "Rating":25,
        "Type":5,
        "Count":1,
        "CreatedItemId":11541,
        "RequiresRecipeItem":false,
        "Ingredients":[{"ItemID":19797,"Count":1},{"ItemID":13094,"Count":1},{"ItemID":13093,"Count":1}]
    },
    "1":{
        "DataID":3219,
        "Name":"Feast of Veggie Pizzas",
        "Rating":125,
        "Type":8,
        "Count":1,
        "CreatedItemId":12602,
        "RequiresRecipeItem":true,
        "Ingredients":[{"ItemID":12346,"Count":10}]}
    }
}

*/

//Quick and dirty discipline name to ID translation.
$disciplines = array(
    'Huntsman' => 1,
    'Artificer' => 2,
    'Weaponsmith' => 3,
    'Armorsmith' => 4,
    'Leatherworker' => 5,
    'Tailor' => 6,
    'Jeweler' => 7,
    'Chef' => 8,
    'Scribe' => 9
);

//Gather all recipes by recipe_id
$curl = CurlRequest::newInstance(getAppConfig('gw2spidy.gw2api_url')."/v1/recipes.json") ->exec();
$data = json_decode($curl->getResponseBody(), true);
$multi_curl = EpiCurl::getInstance();
$recipe_curls = array();

$recipe_count = count($data['recipes']);

$error_values = array();

$i = 0;
$ii = 0;

foreach (array_chunk($data['recipes'], 200) as $recipes) {
    //Add all curl requests to the EpiCurl instance.
    foreach ($recipes as $recipe_id) {
        $i ++;

        $ch = curl_init(getAppConfig('gw2spidy.gw2api_url')."/v1/recipe_details.json?recipe_id={$recipe_id}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $recipe_curls[$recipe_id] = $multi_curl->addCurl($ch);

        echo "[{$i} / {$recipe_count}]: $recipe_id\n";
    }

    foreach ($recipes as $recipe_id) {
        $ii ++;

        try {
            echo "[{$ii} / {$recipe_count}] ";

            $recipe_details = json_decode($recipe_curls[$recipe_id]->data, true);

            if (!isset($recipe_details['output_item_id']) && isset($recipe_details['text']) && $recipe_details['text'] === "too many requests") {
                die("TOO MANY REQUESTS");
            }

            $created_item = ItemQuery::create()->findPK($recipe_details['output_item_id']);

            if (!$created_item) throw new NoResultItemException("no result [[ {$recipe_details['output_item_id']} ]]");

            echo $created_item->getName() . "\n";

            //If a recipe has multiple disciplines, treat each one like a separate recipe to be inserted.
            foreach($recipe_details['disciplines'] as $discipline) {
                $recipe = new stdClass();
                $recipe->DataID = $recipe_id;
                $recipe->Name = $created_item->getName();
                $recipe->Rating = (int) $recipe_details['min_rating'];
                $recipe->Type = $disciplines[$discipline];
                $recipe->Count = (int) $recipe_details['output_item_count'];
                $recipe->CreatedItemId = (int) $recipe_details['output_item_id'];
                $recipe->RequiresRecipeItem = in_array("LearnedFromItem", $recipe_details['flags']);
                $recipe->Ingredients = array();

                foreach($recipe_details['ingredients'] as $ingredient) {
                    $recipe->Ingredients[] = new Ingredient((int) $ingredient['item_id'], (int) $ingredient['count']);
                }

                $recipe_list->append($recipe);
            }
        } catch (Exception $e) {
            $error_values[] = $recipe_id;
            echo "failed [[ {$e->getMessage()} ]] .. \n";
        }
    }

    sleep(180);
}

if (count($error_values) > 0)
    var_dump($error_values);

file_put_contents($argv[1], json_encode($recipe_list));

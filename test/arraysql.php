<?php


/**
 * Klasa ArraySQL przeznaczona jest przetwarzania bardzo duzej ilosci danych.
 * Cel pominiecie ograniczenia memory_limit, oraz odciazenia bazy danych.
 * Klasa ArraySQL posiada rowniez zaawansowane mozliwosci przeszukiwania danych poprzez funkcje in_array, search, queryPropel.
 * Zapis i odczyt danych jest analogiczny do ArrayObject.
 */


use Propel\Runtime\ActiveQuery\Criteria;
use Diagnolek\Db\ArraySQL;
use Diagnolek\Db\StoreQuery;
use Diagnolek\Db\ArraySQLSearch;
use Diagnolek\Db\BazaHelper;
use Diagnolek\Db\RowsFormatter;

require '../vendor/autoload.php';
require '../propel/config.php';

/**
 * Test, porownanie zapisu i odczytu w ArraySQL i ArrayObject
 */
echo "<div>----------------------------------------------------------------</div>".PHP_EOL;
echo "<div>ArrayObject</div>".PHP_EOL;
echo "<div>----------------------------------------------------------------</div>".PHP_EOL;
$tmp = new \ArrayObject();
$tmp['a'] = [1,2,'end' => [3,4], false];
$tmp['c'] = null;
$tmp['b'] = [12.6,13.6];
$tmp[3] = 'start';
$tmp->append('co¶tam');
$tmp[40][0] = "a";

echo "<div>count ".count($tmp)."</div>".PHP_EOL;

foreach ($tmp as $key => $val) {
    echo $key;
    var_dump($val);
}

var_dump($tmp['a'][1]);

/**
 * Tworzenie obiektu ArraySQL
 * ####
 * $arr = new ArraySQL(); - korzysta z pliku /tmp/array.db, wspolnym dla wszystkich wywolan plikow php
 * W ten sposob korzystamy (najczesciej), wtedy kiedy nie mamy bardzo duzej ilosci danych a bardziej zalezy nam na zawansowanych funkcjach przeszukiwania tablicy,
 * oraz spodziewamy sie bardzo czesto wywolywany (ansychroniczny request)
 * ####
 * $arr = BazaHelper::getInstance()->createArraySQL() - korzysta z indywidualnego pliku /tmp/array_(id).db
 * lub
 * $arr = BazaHelper::getInstance()->createArraySQL(true) - dodatkowo tworzy index dla efektywnego przeszukiwania tablicy
 * W ten sposob korzystamy wtedy, kiedy mamy duza ilosc danych np z bazy,
 * oraz nie spodziewamy sie czestych ansychroniczny wywolan, dobrze sie sprawdzi w screen
 * ####
 * $arr = new ArraySQL(new \SQLite3('/var/www/html/array.db'), null, null, [], false);
 * W ten sposob kiedy chcemy testowac (co zapisalo nam sie w array.db)
 */

echo "<div>----------------------------------------------------------------</div>".PHP_EOL;
echo "<div>ArraySQL</div>".PHP_EOL;
echo "<div>----------------------------------------------------------------</div>".PHP_EOL;
$arr = new ArraySQL();
$arr['a'] = [1,2,'end' => [3,4], false];
$arr['c'] = null;
$arr['b'] = [12.6,13.6];
$arr[3] = 'start';
$arr->append('co¶tam');
$arr[40]=["a"]; //zamiast zapisu $tmp[40][0] = "a" - taki zapis nie dziala dla ArraySql

echo "<div>count ".count($arr)."</div>".PHP_EOL;

foreach ($arr as $key => $val) {
    echo $key;
    if ($val instanceof ArraySQL) {
        var_dump($val->toArray());
    } else {
        var_dump($val);
    }
}

var_dump($arr['a'][1]);

/**
 * Test, szukania za pomoc± funkcji:
 * in_array - zwraca ilosc wystapien danej wartosci w tablicy, ma mozliwosc szukania recursive
 * search - zwraca tablice z szukanymi wartosciami (key) i klucze (value) pod ktorych byly zapisane wartosci
 */

echo "<div>----------------------------------------------------------------</div>".PHP_EOL;
echo "<div>Szukanie za w ArraySQL</div>".PHP_EOL;
echo "<div>----------------------------------------------------------------</div>".PHP_EOL;

echo "<div>in_array - szukanie abc: " . $arr->in_array('abc')."</div>".PHP_EOL;
echo "<div>in_array - szukanie 4 recursive: " . $arr->in_array(4, true)."</div>".PHP_EOL;

echo "<div>search - zawansowane wyszukiwanie</div>".PHP_EOL;
$search = $arr->search((new ArraySQLSearch())
    ->addAnd([3,4,'co¶tam'],ArraySQLSearch::COL_VALUE, ArraySQLSearch::IN)
    ->addOr(13.6, ArraySQLSearch::COL_VALUE, ArraySQLSearch::GREATER_EQUAL)
    ->addOr(false, ArraySQLSearch::COL_VALUE), true);

foreach ($search as $find => $keys) {
    echo "<div>$find - keys: ".implode(",", $keys)."</div>".PHP_EOL;
}

echo "<div>----------------------------------------------------------------</div>".PHP_EOL;
echo "<div>RowsFormatter z ArraySQL</div>".PHP_EOL;
echo "<div>----------------------------------------------------------------</div>".PHP_EOL;

die();

/**
 * Test porownania uzycia RowsFormatter ze standartowa tablica i tablica sql
 */

$queryStd = BoczniceQuery::create()
    ->setFormatter(new RowsFormatter(null, null,false, RowsFormatter::ARRAY_TYPE_STD))
    ->filterByIdObszaruWarstwy(122)
    ->find();

$count1 = count($queryStd);

/* @var $querySQL ArraySQL */
$querySQL = BoczniceQuery::create()
    ->setFormatter(new RowsFormatter(null, null,false, RowsFormatter::ARRAY_TYPE_SQL))
    ->find();

$count2 = $querySQL->getQueryPropel()
    ->filterByValue(122)
    ->filterByKey(BazaHelper::getInstance()->translateColumnName(BoczniceTableMap::COL_ID_OBSZARU_WARSTWY)['columnName'])
    ->find()->count();

echo "<div>count std: $count1 | count sql: $count2".PHP_EOL;

/**
 * Test przeszukiwania danych za pomoca queryPropel (StoreQuery)
 */

/* @var $query1 StoreQuery */
$query1 = $querySQL->getQueryPropel();

/**
 * chce znalesc ilosc wystapien dla poszczegolnego id_obszaru_warstwy
 */
$query1->addAsColumn('ile', 'COUNT(*)')
    ->addAnd('key', BazaHelper::getInstance()->translateColumnName(BoczniceTableMap::COL_ID_OBSZARU_WARSTWY)['columnName'])
    ->addGroupByColumn('value');

$data = $query1->find();

$i = 1;
foreach ($data as $key => $row) {
    if ($i > 3) {
        continue;
    }
    echo "<div>id_obszaru_warstwy: {$row['value']} wystapien: {$row['ile']}</div>".PHP_EOL;
    $i++;
}

/**
 * pobranie firm aby nie robic join na tabelach w ktorych chcemy wykorzystac fid. np pobrac nazwe firmy
 * @var $querySQL ArraySQL
 */
$querySQL2 = FirmyQuery::create()
    ->setFormatter(new RowsFormatter(null, null,false, RowsFormatter::ARRAY_TYPE_SQL))
    ->find();

/**
 * chce znalesc rows dla ktorych id_obszaru_warstwy jest wieksze od 110
 */

/* @var $query1 StoreQuery */
$query2 = $querySQL->getQueryPropel();
$tmp = new StoreQuery();
$tmp->addSelectColumn('value')
    ->addSelectColumn('deep')
    ->addAsColumn('ile', 'COUNT(*)')
    ->filterByKey(BazaHelper::getInstance()->translateColumnName(BoczniceTableMap::COL_ID_OBSZARU_WARSTWY)['columnName'])
    ->filterByValue(110, Criteria::GREATER_THAN)
    ->addGroupByColumn('value')
    ->addGroupByColumn('deep');

$query2->addSelectQuery($tmp, 'tmp')
    ->addAnd('', 's.value=tmp.value', Criteria::CUSTOM)
    ->addAnd('', 's.deep=tmp.deep', Criteria::CUSTOM);

$i = 1;
$data = $query2->find();

/* @var $queryFirmy StoreQuery */
$queryFirmy = $querySQL2->getQueryPropel();

foreach ($data as $key => $row) {
    if ($i > 3) {
        continue;
    }
    $firma = $queryFirmy->filterByKey('fid')->filterByValue($row['id_firmy'])->findOne(); //zamiast robienia join firmy na bocznice
    echo "<div>firma: ".($firma ? $firma['nazwa'] : "")." nazwa: {$row['nazwa']} id_obszaru_warstwy: {$row['id_obszaru_warstwy']} </div>".PHP_EOL;
    $i++;
}

//echo memory_get_peak_usage(true);
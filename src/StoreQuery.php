<?php


namespace Diagnolek\Db;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\Adapter\Pdo\SqliteAdapter;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\BadMethodCallException;
use Propel\Runtime\Exception\InvalidArgumentException;
use Propel\Runtime\Exception\RuntimeException;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\DatabaseMap;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;
use SplFixedArray;


class StoreQuery extends Criteria
{
    const DATABASE_NAME = 'array';

    const OM_CLASS = '';

    const TABLE_NAME = 'store';

    protected $con;
    protected $id;
    protected $deep;

    public function __construct($con = null, $id = null, $deep = null)
    {
        $this->con = $con;
        $this->id = $id;
        $this->deep = $deep;

        $this->tableMap = self::buildTableMap();
        $this->primaryTableName =  self::TABLE_NAME;
        Propel::getServiceContainer()->setAdapter(self::DATABASE_NAME, new SqliteAdapter());
        Propel::getServiceContainer()->setDefaultDatasource(self::DATABASE_NAME);
    }

    public static function buildTableMap()
    {
        $dbMap =  new DatabaseMap(self::DATABASE_NAME);

        $tableMap = new TableMap(self::TABLE_NAME, $dbMap);
        $tableMap->setClassName(self::OM_CLASS);

        $tableMap->addColumn('id', 'Id', 'INTEGER');
        $tableMap->addColumn('deep', 'Deep', 'INTEGER');
        $tableMap->addColumn('key', 'Key', 'VARCHAR');
        $tableMap->addColumn('value', 'Value', 'VARCHAR');
        $tableMap->addColumn('typ', 'Typ', 'INTEGER');

        if (!$tableMap->getDatabaseMap()->hasTable(self::TABLE_NAME)) {
            $tableMap->getDatabaseMap()->addTableObject($tableMap);
        }

        return $tableMap;
    }

    private function correctInit()
    {
        if (!$this->id) {
            throw new RuntimeException("id not exits");
        }
        ArraySQL::correctAdapter($this->con);
    }

    /**
     * @param $val string
     * @param $comparison string defaults to Criteria::EQUAL
     * @return StoreQuery
     */
    public function filterByKey($val, $comparison = null)
    {
        $this->addAnd('key', $val, $comparison);
        return $this;
    }

    /**
     * @param $val string
     * @param $comparison string defaults to Criteria::EQUAL
     * @return StoreQuery
     */
    public function filterByValue($val, $comparison = null)
    {
        $this->addAnd('value', $val, $comparison);
        return $this;
    }

    /**
     * @param $val string
     * @param $comparison string defaults to Criteria::EQUAL
     * @return StoreQuery
     */
    public function filterTyp($val, $comparison = null)
    {
        $this->addAnd('typ', $val, $comparison);
        return $this;
    }

    private function sql()
    {
        if (!empty($this->groupByColumns)) {
            $this->selectColumns = $this->groupByColumns;
        } else if (empty($this->selectColumns)) {
            $this->addSelectColumn('*');
        }

        if (empty($this->selectQueries)) {
            $this->primaryTableName =  self::TABLE_NAME;
            $this->addAnd('id', $this->id);
        } else {
            $this->addAlias('s', self::TABLE_NAME);
            $this->addAnd('s.id', $this->id);
        }
        $params = [];
        $sql = $this->createSelectSql($params);
        return $this->fillParams($sql, $params);
    }

    private function fillParams($sql, array $params, $position = 0)
    {
        $i = 1;
        $c = preg_match('/^INSERT INTO/i', $sql) ? "," : "";
        $length = count($params);
        foreach ($params as $param) {
            if (!empty($c) && $i == $length) {
                $c = ")";
            }
            $position++;
            $parameter = ':p' . $position.$c;
            $value = $param['value'];
            if (null === $value) {
                $sql = str_replace($parameter, "null".$c, $sql);
            } elseif (is_int($value)) {
                $sql = str_replace($parameter, "$value".$c, $sql);
            } elseif (is_bool($value)) {
                if ($value == true ) {
                    $sql = str_replace($parameter, "true".$c, $sql);
                } elseif ($value == false) {
                    $sql = str_replace($parameter, "false".$c, $sql);
                }
            } else {
                $sql = str_replace($parameter, "'$value'".$c, $sql);
            }
            $i++;
        }

        return $sql;
    }

    /**
     * @param bool $deep
     * @return SplFixedArray
     */
    public function find($deep = true)
    {
        $this->correctInit();
        $sql = $this->sql();
        if ($this->con instanceof \SQLite3) {
            $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') propelmatch4cnt';
            $count = $this->con->querySingle($countSql);
            if ($count) {
                $data = new SplFixedArray($count);
                $result = $this->con->query($sql);
                $i = 0;
                while($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    if (isset($row['deep']) || (isset($row['value']) && isset($row['typ']))) {
                        $store = $deep ? $this->parseDeep($row['deep']) : $this->parseRow($row);
                        $data->offsetSet($i, $store);
                    } else {
                        $store = $row;
                    }
                    $data->offsetSet($i, $store);
                    $i++;
                }
                return $data;
            }
        }
        return new SplFixedArray(0);
    }

    /**
     * @param bool $deep
     * @return array|null
     */
    public function findOne($deep = true)
    {
        $this->correctInit();
        $sql = $this->sql();
        if ($this->con instanceof \SQLite3) {
            $result = $this->con->query($sql);
            $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : [];
            if (isset($row['deep']) || (isset($row['value']) && isset($row['typ']))) {
                return $deep ? $this->parseDeep($row['deep']) : $this->parseRow($row);
            } else {
                return $row;
            }
        }
        return null;
    }

    private function parseRow($row)
    {
        $value = [$row['value'], $row['typ']];

        if ($value[1] == ArraySQLTyp::TYP_ARR) {
            $value[0] = (new ArraySQL($this->con, $this->id, $value[0]))->toArray();
        } else {
            ArraySQLTyp::convertFrom($value);
        }

        $row['value'] = $value[0];

        return $row;
    }

    private function parseDeep($deep)
    {
        $store = [];
        $deepSql = "SELECT key, value, typ FROM store WHERE id={$this->id} AND deep=$deep";
        $result = $this->con->query($deepSql);
        if ($result) {
            while($row = $result->fetchArray(SQLITE3_NUM))
            {
                $value = [$row[1], $row[2]];

                if ($value[1] == ArraySQLTyp::TYP_ARR) {
                    $value[0] = (new ArraySQL($this->con, $this->id, $value[0]))->toArray();
                } else {
                    ArraySQLTyp::convertFrom($value);
                }

                $store[$row[0]] = $value[0];
            }
        }
        return $store;
    }

    public function clearInstancePool()
    {
        //nothing doing
    }

    public function clearRelatedInstancePool()
    {
        //nothing doing
    }

    public function doDelete(ConnectionInterface $con = null)
    {
        throw new BadMethodCallException('delete not supported');
    }

    public function doUpdate($updateValues, ConnectionInterface $con = null)
    {
        throw new BadMethodCallException('update not supported');
    }

    public function doInsert(ConnectionInterface $con = null)
    {
        throw new BadMethodCallException('insert not supported');
    }

    public function addJoin($left, $right, $joinType = null)
    {
        throw new BadMethodCallException('addJoin not supported');
    }

    public function addMultipleJoin($conditions, $joinType = null)
    {
        throw new BadMethodCallException('addMultipleJoin not supported');
    }

    public function addJoinObject(Join $join)
    {
        throw new BadMethodCallException('addJoinObject not supported');
    }

    public function setIgnoreCase($b)
    {
        throw new BadMethodCallException('setIgnoreCase not supported');
    }

    public function setPrimaryTableName($tableName)
    {
        if ($tableName != self::TABLE_NAME) {
            throw new InvalidArgumentException('table only store');
        }
        parent::setPrimaryTableName($tableName);
    }

    public function setDbName($dbName = null)
    {
        throw new BadMethodCallException('setDbName not supported');
    }

    public function setIdentifierQuoting($identifierQuoting)
    {
        throw new BadMethodCallException('setIdentifierQuoting not supported');
    }

    public function setUseTransaction($v)
    {
        throw new BadMethodCallException('setUseTransaction not supported');
    }

    public function addSelectColumn($name)
    {
        if ($name != '*' && !$this->tableMap->hasColumn($name)) {
            throw new InvalidArgumentException($name.' column not exists');
        }
        return parent::addSelectColumn($name);
    }

    public function addAscendingOrderByColumn($name)
    {
        if (!$this->tableMap->hasColumn($name)) {
            throw new InvalidArgumentException($name.' column not exists');
        }
        return parent::addAscendingOrderByColumn($name);
    }

    public function addDescendingOrderByColumn($name)
    {
        if (!$this->tableMap->hasColumn($name)) {
            throw new InvalidArgumentException($name.' column not exists');
        }
        return parent::addDescendingOrderByColumn($name);
    }

    public function addGroupByColumn($groupBy)
    {
        if (!$this->tableMap->hasColumn($groupBy)) {
            throw new InvalidArgumentException($groupBy.' column not exists');
        }
        return parent::addGroupByColumn($groupBy);
    }

    public function addSelectQuery(StoreQuery $subQueryCriteria, $alias = null)
    {
        $subQueryCriteria->addAnd('id', $this->id);
        return parent::addSelectQuery($subQueryCriteria, $alias);
    }

}
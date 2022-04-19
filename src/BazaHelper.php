<?php

namespace Diagnolek\Db;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;

class BazaHelper
{
    private static $instance = null;

    private $aliasFilter = [];

    private $sortColumn = [];

    private $serializerSalt = "fgfdg54635sd";

    private function __construct()
    {
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new BazaHelper();
        }

        return self::$instance;
    }

    public function getPrimaryKeyOfMap(TableMap $map)
    {
        $primaryKeys = $map->getPrimaryKeys();

        return key($primaryKeys);
    }

    public function getSimpleNameColumn($name, $ucFirst = false, $alias = '')
    {
        $data = explode(".", $name);
        $str = $name;

        if (count($data) >= 2) {
            $str = $data[1];
        }

        $column = $ucFirst == true ? ucfirst($str) : $str;

        if($alias !== '') {
            $column = $alias . '.' . $column;
        }

        return $column;
    }

    public function translateColumnName($name)
    {
        $vals = ['tableName' => '', 'columnName' => ''];
        $data = explode(".", $name);

        if (count($data) >= 2) {
            $vals['tableName'] = $data[0];
            $vals['columnName'] = $data[1];
        } else {
            $vals['columnName'] = $name;
        }

        return $vals;
    }

    public function addAsColumnOfSelectColumns(Criteria $criteria)
    {
        $columns = $criteria->getSelectColumns();
        $columnsAs = $criteria->getAsColumns();
        $criteria->clearSelectColumns();

        foreach ($columns as $column) {
            $criteria->addAsColumn('"' . $column . '"', $column);
        }

        foreach ($columnsAs as $alias => $column) {
            $criteria->addAsColumn($alias, $column);
        }
    }

    public function setAliasFilter($alias, $tableName)
    {
        $this->aliasFilter[$alias] = $tableName;
    }

    public function clearAliasFilter()
    {
        $this->aliasFilter = [];
    }

    public function getColumnNameAtFilter($columnName)
    {
        $parse = $this->translateColumnName($columnName);

        if (!empty($parse['tableName']) && array_key_exists($parse['tableName'], $this->aliasFilter)) {
            return $this->aliasFilter[$parse['tableName']] . '.' . $parse['columnName'];
        }

        return $columnName;
    }

    public function setSortColumn($columnName, $numberColumn)
    {
        $this->sortColumn[$columnName] = $numberColumn;
    }

    public function clearSortColumn()
    {
        $this->sortColumn = [];
    }

    public function addTableMapOfSortColumn(Criteria $criteria)
    {
        $dbMap = Propel::getServiceContainer()->getDatabaseMap($criteria->getDbName());

        foreach ($this->aliasFilter as $alias => $tableName) {

            if (!$dbMap->hasTable($tableName)) {

                $table = new TableMap($tableName);
                $columnAdded = false;

                foreach ($this->sortColumn as $column => $number) {
                    if (strpos($column, $tableName) !== false) {
                        $table->addColumn($this->getSimpleNameColumn($column), $this->getSimpleNameColumn($column, true), "VARCHAR");
                        $columnAdded = true;
                    }
                }

                if ($columnAdded) {
                    $dbMap->addTableObject($table);
                }
            }
        }
    }

    public function renderSortColumn($sort, Criteria $criteria = null)
    {
        $data = explode(",", $sort);
        $vals = [];

        foreach ($data as $val) {
            preg_match('/([0-9]{1,}).(ASC|DESC)/', $val, $matches);
            if (count($matches) >= 3) {
                array_walk($this->sortColumn, function ($item, $key) use ($matches, &$vals) {
                    if ($matches[1] == $item) {
                        $vals[$key] = $matches[2];
                    }
                });
            }
        }

        if ($criteria) {
            foreach ($vals as $column => $dir) {
                if ($dir == "ASC") {
                    $criteria->addAscendingOrderByColumn($column);
                } else if ($dir == "DESC") {
                    $criteria->addDescendingOrderByColumn($column);
                }
            }
            $this->addTableMapOfSortColumn($criteria);
        }

        return $vals;
    }

    public function addSelectColumnFromArray(array $columns, Criteria $criteria)
    {
        $criteria->clearSelectColumns();
        foreach ($columns as $column) {
            $criteria->addSelectColumn($column);
        }
    }

    public function serializerQuery(Criteria $criteria)
    {
        $data['abc'] = serialize($criteria);
        $data['def'] = md5("({$this->serializerSalt}){$data['abc']}");//token
        return base64_encode(\json_encode($data));
    }

    public function unserializerQuery($string)
    {
        $obj = null;
        $json = base64_decode($string);
        $data = \json_decode($json, true);
        $error = \json_last_error();

        if (empty($error) && isset($data['abc']) && isset($data['def'])) {
            $token = md5("({$this->serializerSalt}){$data['abc']}");
            $obj = $token == $data['def'] ? unserialize($data['abc']) : null;
        }

        return $obj;
    }

    public function fillParams($sql, array $params, $position = 0)
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

    public function parseCriteriaToString(Criteria $criteria)
    {
        $params = [];
        $sql = $criteria->createSelectSql($params);
        return $this->fillParams($sql, $params);
    }

    public function getNameColumns(Criteria $criteria, $withTableName = true)
    {
        $columns = array_merge($criteria->getSelectColumns(), $criteria->getAsColumns());

        if ($criteria instanceof ModelCriteria && $criteria->getTableMap()) {
            /* @var $column \Propel\Generator\Model\Column */
            foreach ($criteria->getTableMap()->getColumns() as $column) {
                array_push($columns, ($withTableName ? $column->getTableName() . '.' . $column->getName() : $column->getName()));
            }
        }

        return $columns;
    }

    public function addCriterionWithFilter(Criteria $criteria, array $filter, array $columns, $like = 2)
    {
        $notAdded = [];
        foreach ($filter as $columnName => $val) {
            if (array_key_exists($columnName, $columns)) {
                if ($val === '' || $val === 'null') {
                    continue;
                }

                if ($columns[$columnName] == 'string') {
                    switch ($like)
                    {
                        case 1:
                            $criteria->addAnd($columnName, "%$val%", Criteria::LIKE);
                            break;
                        case 2:
                            $criteria->addCond('search1', $columnName, "%$val%", Criteria::LIKE);
                            $criteria->addCond('search2', $columnName, "%" . mb_convert_case($val, MB_CASE_TITLE, "ISO-8859-2") . "%", Criteria::LIKE);
                            $criteria->addCond('search3', $columnName, "%" . mb_strtolower($val, "ISO-8859-2") . "%", Criteria::LIKE);
                            $criteria->addCond('search4', $columnName, "%" . mb_strtoupper($val, "ISO-8859-2") . "%", Criteria::LIKE);
                            $criteria->combine(['search1', 'search2', 'search3', 'search4'], Criteria::LOGICAL_OR);
                            break;
                        default:
                            $criteria->addAnd($columnName, "%$val%", Criteria::ILIKE);
                    }
                } elseif ($columns[$columnName] == 'null') {
                    $criteria->addAnd($columnName, null, (strtolower($val) == 'isnotnull' ? Criteria::ISNOTNULL : Criteria::ISNULL));
                } elseif ($columns[$columnName] == 'greater') {
                    $criteria->addAnd($columnName, $val, Criteria::GREATER_EQUAL);
                } elseif ($columns[$columnName] == 'less') {
                    $criteria->addAnd($columnName, $val, Criteria::LESS_EQUAL);
                } else {
                    $criteria->addAnd($columnName, $val);
                }
            } else {
                $notAdded[] = $columnName;
            }
        }

        return $notAdded;
    }

    public function createArraySQL($withIndex = false, $encoding = null)
    {
        $path = is_dir('/tmp') ? '/tmp' : '.';
        $id = mt_rand();
        $db = new \SQLite3("$path/array_$id.db");
        register_shutdown_function(function () use ($db, $id, $path) {
            $db->close();
            unlink ("$path/array_$id.db");
        });

        ArraySQL::$encoding = is_null($encoding) ? Diagnolek\ArraySQL::ENCODING_DEFAULT : $encoding;

        return new ArraySQL($db, $id, 0, ['index' => $withIndex], false);
    }
}

<?php

namespace Diagnolek\Db;

use Propel\Generator\Model\PhpNameGenerator;
use Propel\Runtime\Map\ColumnMap;
use Propel\Runtime\Map\Exception\ColumnNotFoundException;
use Propel\Runtime\Map\TableMap;

trait TableViewTrait {

    protected $columns = [];

    private function camel2underscore($methodName) {
        return ltrim(preg_replace('/[A-Z0-9]([A-Z](?![a-z0-9]))*/', '_$0', $methodName), PhpNameGenerator::STD_SEPARATOR_CHAR);
    }

    public function __call($name, $arguments)
    {
        preg_match('/(get|set)(.{1,})/', $name, $matches);

        if (count($matches) >= 3) {
            $key =  ColumnMap::normalizeName($this->camel2underscore($matches[2]));
            if (array_key_exists($key, $this->columns)) {
                if ($matches[1] == "set") {
                    $this->$key = $arguments;
                } else if($matches[1] == "get") {
                    return $this->$key;
                }
            } else {
                throw new ColumnNotFoundException("column $key not exists");
            }
        }
    }

    public function __get($name)
    {
        $key = ColumnMap::normalizeName($name);
        return isset($this->columns[$key]) ? $this->columns[$key] : null;
    }

    public function __set($name, $value)
    {
        $key = ColumnMap::normalizeName($name);
        $this->columns[$key] = $value;
    }

    public function toArray()
    {
        return $this->columns;
    }

    public function fromArray(array $arr, TableMap $tableMap = null)
    {
        $keys = is_object($tableMap) ? $tableMap->getColumns() : null;

        foreach ($arr as $key => $value) {

            if ($keys !== null) {
                if(array_key_exists($key, $keys)) {
                    $this->$key = $value;
                }
            } else {
                $this->$key = $value;
            }
        }
    }


}

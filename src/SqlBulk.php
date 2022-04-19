<?php


namespace Diagnolek\Db;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;

class IteratorBulk implements \Iterator
{

    public function current()
    {
        return null;
    }

    public function valid()
    {
        return false;
    }

    public function next()
    {

    }

    public function key()
    {

    }

    public function rewind()
    {

    }
}

class StmtBulk extends \PDOStatement
{
    private $bulk;
    private $params;

    public function __construct($bulk)
    {
        $this->bulk = $bulk;
        $this->params = [];
    }

    public function execute($params=null)
    {
        if (is_array($params)) {
            $this->params = $params;
        }
        $insert = preg_match('/^INSERT INTO ([a-z_]+) (\(.*\)) VALUES/', $this->bulk->getName(), $matches);
        if ($this->bulk->hasCopy) {
            if ($insert) {
                $fields = [];
                foreach ($this->params as $param) {
                    $fields[] = $this->parseParam($param);
                }
                if (count($fields)) {
                    $this->bulk->copy[$matches[1]."#".preg_replace('/\s/', '', $matches[2])][] = implode(";", $fields);
                }
            }
        } else {
            $sql = BazaHelper::getInstance()->fillParams($this->bulk->getName(), $this->params, $insert ? -1 : 0);
            $this->bulk->content[] = $sql;
        }
        $this->params = [];
    }

    private function parseParam($param)
    {
        switch ($param['type'])
        {
            default:
                return $param['value'];
        }
    }

    public function bindValue($param, $value, $type = \PDO::PARAM_STR)
    {
        $this->params[$param] = ['value' => $value, 'type' => $type];
    }

    public function rowCount()
    {
        return count(($this->bulk->hasCopy ? $this->bulk->copy : $this->bulk->content));
    }

    public function getIterator(): IteratorBulk
    {
        return new IteratorBulk();
    }

}

/**
 * Class SqlBulk
 * - paczkuje wiele zapytañ w jedno duze i wysyla w jednej transakcji
 * @papram $options array example: ['db' => 'mops_trasy', 'copy' => true]
 * - db: ustawia nazwe bazy
 * - copy: ustawia metode paczkowania zapytan
 *         true = wykorzystuje pg_copy_from, mozna ja wykorzystac jedynie dla zaptyan typu insert
 *         false = laczy wiele zapytan w jeden string
 * @package Diagnolek\Db
 */

class SqlBulk implements ConnectionInterface
{
    private $name;
    private $stmt;
    private $propel;

    public $content;
    public $copy;
    public $hasCopy;

    public function __construct(array $options = [])
    {
        if (isset($options['db']) && $options['db'] instanceof ConnectionInterface) {
            $this->propel = $options['db'];
        } else {
            $this->propel = Propel::getConnection((empty($options['db']) ? 'mops_pociagi' : $options['db']));
        }

        $this->hasCopy = (!empty($options['copy']));

        $this->stmt = new StmtBulk($this);
        $this->init();
    }

    private function init()
    {
        $this->name = "";
        $this->content = [];
        $this->copy = [];
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function beginTransaction()
    {
        $this->propel->beginTransaction();
    }

    public function execAll()
    {
        $this->beginTransaction();
        try {
            $exec = false;
            if ($this->hasCopy) {
                foreach ($this->copy as $key => $rows) {
                    $table = explode('#', $key);
                    $fields = str_replace(['(',')'],['',''], $table[1]);
                    $this->propel->pgsqlCopyFromArray($table[0], $rows, ';', "null", $fields);
                    $exec = true;
                }
            } elseif (count($this->content)) {
                $query = implode(';', $this->content);
                $this->exec($query);
                $exec = true;
            }

            if ($exec) {
                $this->commit();
                $this->init();
                return true;
            }
            return false;

        } catch (PropelException $e) {
            $this->rollBack();
            $this->init();
            return $e;
        }
    }

    public function commit()
    {
        $this->propel->commit();
    }

    public function rollBack()
    {
        $this->propel->rollBack();
    }

    public function inTransaction()
    {
        return $this->propel->inTransaction();
    }

    public function getAttribute($attribute)
    {
        return $this->propel->getAttribute($attribute);
    }

    public function setAttribute($attribute, $value)
    {
        return $this->propel->setAttribute($attribute, $value);
    }

    public function lastInsertId($name = null)
    {
        return null;
    }

    public function getSingleDataFetcher($data)
    {
        return $this->propel->getSingleDataFetcher($data);
    }

    public function getDataFetcher($data)
    {
        return $this->propel->getDataFetcher($data);
    }

    public function transaction(callable $callable)
    {
        call_user_func($callable);
    }

    public function exec($statement)
    {
        return $this->propel->exec($statement);
    }

    public function prepare($statement, $driver_options = null)
    {
        $this->setName($statement);
        return $this->stmt;
    }

    public function query($statement)
    {
        return $this->propel->query($statement);
    }

    public function quote($string, $parameter_type = \PDO::PARAM_STR)
    {
        return $this->propel->quote($string, $parameter_type);
    }
}
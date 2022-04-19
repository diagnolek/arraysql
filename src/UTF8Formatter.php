<?php


namespace Diagnolek\Db;


use Propel\Runtime\ActiveQuery\BaseModelCriteria;
use Propel\Runtime\DataFetcher\DataFetcherInterface;
use Propel\Runtime\Exception\LogicException;
use Propel\Runtime\Formatter\AbstractFormatter;
use Propel\Runtime\Formatter\ObjectFormatter;
use Propel\Runtime\Formatter\SimpleArrayFormatter;
use Propel\Runtime\Map\TableMap;

/**
 * Class UTF8Formatter
 * @package Diagnolek\Db
 * Formatter zmienia kodowanie z ISO-8859-2 (latin2) na utf8
 */
class UTF8Formatter extends AbstractFormatter
{
    private $formatter = null;
    private $formatterId = null;
    private $objects = [];

    public function format(DataFetcherInterface $dataFetcher = null)
    {
        if ($this->formatter === null) {
            throw new LogicException('Cannot use parent formatter');
        }

        if ($dataFetcher) {
            $this->formatter->setDataFetcher($dataFetcher);
        } else {
            $this->formatter->setDataFetcher($this->getDataFetcher());
        }

        $collection = $this->formatter->getCollection();

        $isWithOneToMany = $this->formatter->isWithOneToMany();
        $hasLimit = $this->formatter->hasLimit();

        foreach ($dataFetcher as $row) {

            $rowArray = $this->formatToUTF8($row, $this->formatter);

            if ($this->formatterId == 0) {
                if ($isWithOneToMany && $hasLimit) {
                    throw new LogicException('Cannot use limit() in conjunction with with() on a one-to-many relationship. Please remove the with() call, or the limit() call.');
                }

                if ($rowArray !== false) {
                    $collection[] = $rowArray;
                }
            } elseif ($this->formatterId == 1) {
                if ($isWithOneToMany) {
                    if ($hasLimit) {
                        throw new LogicException('Cannot use limit() in conjunction with with() on a one-to-many relationship. Please remove the with() call, or the limit() call.');
                    }

                    $object = $this->formatter->getAllObjectsFromRow($row);
                    $pk     = $object->getPrimaryKey();
                    $serializedPk = serialize($pk);

                    if (!isset($this->objects[$serializedPk])) {
                        $this->objects[$serializedPk] = $object;
                        $collection[] = $object;
                    }
                } else {
                    // only many-to-one relationships
                    $collection[] = $this->formatter->getAllObjectsFromRow($row);
                }
            }
        }

        $dataFetcher->close();

        return $collection;
    }

    public function formatOne(DataFetcherInterface $dataFetcher = null)
    {
        /* @var $result \Propel\Runtime\Collection\Collection */
        $result = $this->format($dataFetcher);
        return $result->getLast();
    }

    public function isObjectFormatter()
    {
        return ($this->formatterId == 1);
    }

    public function init(BaseModelCriteria $criteria, DataFetcherInterface $dataFetcher = null)
    {
        if ($criteria->getSelect() !== null) {
            $this->formatter = new SimpleArrayFormatter($criteria);
            $this->formatterId = 0;
        } else {
            $this->formatter = new ObjectFormatter($criteria);
            $this->formatterId = 1;
        }

        return $this;
    }

    public function formatToUTF8(&$row, AbstractFormatter $formatter)
    {
        if ($formatter == null || $formatter->getDataFetcher()->getStyle() == \PDO::FETCH_ASSOC) {
            return false;
        }

        if ($formatter->isObjectFormatter() == false) {
            $columnNames = array_keys($formatter->getAsColumns());
        } elseif ($formatter->isObjectFormatter() == true) {
            $columnNames = TableMap::getFieldnamesForClass($formatter->getClass(), TableMap::TYPE_FIELDNAME);
            $columnAs = array_keys($formatter->getAsColumns());
            $columnNames = array_merge($columnNames, $columnAs);
        }

        if (count($columnNames) > 1 && count($row) > 1) {
            $finalRow = [];
            foreach ($row as $index => $value) {
                if (isset($columnNames[$index])) {
                    $columnName = str_replace('"', '', $columnNames[$index]);
                    $column = $formatter->getTableMap()->hasColumn($columnName)
                        ? $formatter->getTableMap()->getColumn($columnName)
                        : 'outside';

                    if ((is_object($column) && $column->getType() == 'VARCHAR') || ($column == 'outside' && self::convertToUTF8Check($value))) {
                        $value = self::convertToUTF8($value);
                        $row[$index] = $value;
                    }

                    $finalRow[$columnName] = $value;
                } else {
                    $finalRow[$index] = self::convertToUTF8Check($value) ? convertToUTF8($value) : $value;
                }
            }
        } else {
            if (self::convertToUTF8Check($row[0])) {
                $row[0] = self::convertToUTF8($row[0]);
            }
            $finalRow = $row[0];
        }
        return $finalRow;
    }

    public static function convertToUTF8($value)
    {
        return iconv("ISO-8859-2", "UTF-8//IGNORE", $value);
    }

    public static function convertToUTF8Check($value)
    {
        return (is_string($value) && strtotime($value) === false);
    }


    public static function createQueryPropel($modelName)
    {
        /* @var $queryPropel \Propel\Runtime\ActiveQuery\ModelCriteria */
        $className = $modelName."Query";
        $queryPropel = $className::create();

        if ($queryPropel) {
            $queryPropel->setFormatter(new UTF8Formatter());
        }

        return $queryPropel;
    }

}
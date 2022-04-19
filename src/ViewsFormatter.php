<?php


namespace Diagnolek\Db;


use Propel\Runtime\ActiveQuery\BaseModelCriteria;
use Propel\Runtime\DataFetcher\DataFetcherInterface;
use Propel\Runtime\Formatter\AbstractFormatter;
use Propel\Runtime\Map\TableMap;

class ViewsFormatter extends AbstractFormatter
{
    private $isFormatter = false;
    private $utf8;

    public function __construct(BaseModelCriteria $criteria = null, DataFetcherInterface $dataFetcher = null, $formatUTF8 = false)
    {
        parent::__construct($criteria, $dataFetcher);
        $this->utf8 = $formatUTF8;
    }

    public function getSingleObjectFromRow($row, $class, &$col = 0)
    {
        try {
            $obj = new $class();
            $col = $obj->hydrate($row, $col, false, TableMap::TYPE_COLNAME);
            $this->isFormatter = true;

            return $obj;
        } catch (\Exception $ex) {
            $this->isFormatter = false;
        }
    }

    public function getCollectionClassName()
    {
        return '\Propel\Runtime\Collection\ObjectCollection';
    }


    public function format(DataFetcherInterface $dataFetcher = null)
    {
        $dataFetcher->setStyle(\PDO::FETCH_ASSOC);

        $class = $this->getClass();

        $collection = $this->getCollection();

        foreach ($dataFetcher as $row) {
            if ($this->utf8) {
                $this->formatToUTF8($row);
            }
            $collection[] = $this->getSingleObjectFromRow($row, $class);
        }

        $dataFetcher->close();

        return $collection;
    }

    public function formatOne(DataFetcherInterface $dataFetcher = null)
    {
        $collection = $this->format($dataFetcher);
        return (count($collection) >= 1) ? $collection[0] : null;
    }

    public function isObjectFormatter()
    {
        return $this->isFormatter;
    }

    public function formatToUTF8(&$row)
    {
        foreach ($row as $index => $value) {
            if (UTF8Formatter::convertToUTF8Check($value)) {
                $row[$index] = UTF8Formatter::convertToUTF8($value);
            }
        }
    }
}
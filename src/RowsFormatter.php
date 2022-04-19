<?php


namespace Diagnolek\Db;


use Propel\Runtime\ActiveQuery\BaseModelCriteria;
use Propel\Runtime\DataFetcher\DataFetcherInterface;
use Propel\Runtime\Formatter\AbstractFormatter;
use SplFixedArray;

class RowsFormatter extends AbstractFormatter
{
    const ARRAY_TYPE_STD = 1;
    const ARRAY_TYPE_FIXED = 2;
    const ARRAY_TYPE_FIXED_NUM = 3;
    const ARRAY_TYPE_SQL = 4;

    private $utf8;
    private $arrayType;
    private static $defaultTypeArray = null;

    public function __construct(BaseModelCriteria $criteria = null, DataFetcherInterface $dataFetcher = null, $formatUTF8 = false, $arrayType = null)
    {
        parent::__construct($criteria, $dataFetcher);
        $this->utf8 = $formatUTF8;
        $this->arrayType = $arrayType;
    }

    public static function defaultArrayType($arrayType = self::ARRAY_TYPE_STD)
    {
        self::$defaultTypeArray = $arrayType;
    }

    public function format(DataFetcherInterface $dataFetcher = null, $arrayType = null)
    {

        if ($arrayType !== null) {
            $this->arrayType = $arrayType;
        }

        if (is_null($this->arrayType)) {
            $this->arrayType = self::$defaultTypeArray;
        }

        if ($dataFetcher === null) {
            $dataFetcher = $this->getDataFetcher();
        }

        switch($this->arrayType)
        {
            case self::ARRAY_TYPE_FIXED:
                $dataFetcher->setStyle(\PDO::FETCH_ASSOC);
                $rows = new SplFixedArray($dataFetcher->count());
                break;

            case self::ARRAY_TYPE_FIXED_NUM:
                $dataFetcher->setStyle(\PDO::FETCH_NUM);
                $rows = new SplFixedArray($dataFetcher->count());
                break;

            case self::ARRAY_TYPE_SQL:
                $dataFetcher->setStyle(\PDO::FETCH_ASSOC);
                $rows = BazaHelper::getInstance()->createArraySQL(true, ($this->utf8 ? "" : null));
                break;

            case self::ARRAY_TYPE_STD:
            default:
                $dataFetcher->setStyle(\PDO::FETCH_ASSOC);
                $rows = [];
        }

        foreach ($dataFetcher as $i => $row) {
            if ($this->utf8) {
                $this->formatToUTF8($row);
            }

            switch($this->arrayType)
            {
                case self::ARRAY_TYPE_FIXED:
                case self::ARRAY_TYPE_SQL:
                    $rows->offsetSet($i, $row);
                    break;

                case self::ARRAY_TYPE_FIXED_NUM:
                    $rows->offsetSet($i, SplFixedArray::fromArray($row));
                    break;

                case self::ARRAY_TYPE_STD:
                default:
                    array_push($rows, $row);
            }
        }

        return $rows;
    }

    public function formatOne(DataFetcherInterface $dataFetcher = null)
    {
        $rows = $this->format($dataFetcher);
        $count = count($rows);
        return ($count >= 1) ? $rows[$count-1] : null;
    }

    public function isObjectFormatter()
    {
        return false;
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
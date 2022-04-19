<?php


namespace Diagnolek\Db;

final class ArraySQLSearch
{
    const COL_KEY = 'key';
    const COL_VALUE = 'value';
    const EQUAL = '==';
    const NOT_EQUAL = '!=';
    const GREATER_THAN = '>';
    const GREATER_EQUAL = '>=';
    const LESS_THAN = '<';
    const LESS_EQUAL = '<=';
    const LIKE = ' LIKE ';
    const NOT_LIKE = ' NOT LIKE ';
    const ILIKE = ' ILIKE ';
    const NOT_ILIKE = ' NOT ILIKE ';
    const IN = ' IN ';
    const NOT_IN = ' NOT IN ';

    private $where = [];

    public function __construct($value = '', $column = self::COL_VALUE, $comparison = self::EQUAL)
    {
        if ($value !== '') {
            $this->addAnd($value, $column, $comparison);
        }
    }

    public function addAnd($value, $column = self::COL_VALUE, $comparison = self::EQUAL)
    {
        $this->where[] = ['and', $column, $value, $comparison];
        return $this;
    }

    public function addOr($value, $column = self::COL_VALUE, $comparison = self::EQUAL)
    {
        $this->where[] = ['or', $column, $value, $comparison];
        return $this;
    }

    public function whereSql()
    {
        $sql = "";
        $i = 0;
        foreach ($this->where as $condition) {
            if ($i > 0) {
                $sql .= $condition[0] == 'and' ? " AND" : " OR";
            }
            $sql .= " ";
            $val = $condition[2];
            $typ = ArraySQLTyp::convertTo($val);

            if (\is_array($condition[2])) {
                $typ = 100;
            }

            switch ($typ)
            {
                case ArraySQLTyp::TYP_INT:
                    $condition[1] = "CAST(".$condition[1]." as integer)";
                    break;
                case ArraySQLTyp::TYP_FLOAT:
                    $condition[1] = "CAST(".$condition[1]." as decimal)";
                    break;
                case ArraySQLTyp::TYP_BOOL:
                    $condition[3] = '==';
                    $condition[2] = "'".($condition[2] ? 'true' : 'false')."'";
                    break;
                case ArraySQLTyp::TYP_OBJ:
                    $condition[3] = '==';
                    $condition[2] = "'".$condition[2]."'";
                    break;
                case ArraySQLTyp::TYP_NULL:
                    $condition[3] = '==';
                    $condition[2] = "'null'";
                    break;
                case ArraySQLTyp::TYP_STR:
                    if (ArraySQL::$encoding) {
                        $condition[2] = \iconv(ArraySQL::$encoding, "UTF-8".ArraySQL::$encoding_ignore, $condition[2]);
                    }
                    $condition[2] = "'".$condition[2]."'";
                    break;
                case 100;
                    if (count($condition[2]) && in_array($condition[3],[self::IN, self::NOT_IN])) {
                        foreach ($condition[2] as $k => $v) {
                            if (is_string($v) && ArraySQL::$encoding) {
                                $v = \iconv(ArraySQL::$encoding, "UTF-8".ArraySQL::$encoding_ignore, $v);
                            }
                            $condition[2][$k] = "'".$v."'";
                        }
                        $condition[2] = "(".implode(',', $condition[2]).")";
                    } else {
                        $condition[3] = '';
                    }
                    break;
            }

            if (!empty($condition[3])) {
                $sql .= $condition[1].$condition[3].$condition[2];
                $i++;
            }
        }
        return $sql;
    }
}


final class ArraySQLTyp
{
    const TYP_STR = 1;
    const TYP_INT = 2;
    const TYP_BOOL = 3;
    const TYP_NULL = 4;
    const TYP_FLOAT = 5;
    const TYP_OBJ = 6;
    const TYP_ARR = 7;

    public static function convertFrom(&$value)
    {
        if ($value[1] == self::TYP_INT) {
            $value[0] = (int) $value[0];
        } else if ($value[1] == self::TYP_FLOAT) {
            $value[0] = (float) $value[0];
        } else if ($value[1] == self::TYP_NULL) {
            $value[0] = null;
        } else if ($value[1] == self::TYP_BOOL) {
            $value[0] = ($value[0] == 'true');
        } else if ($value[1] == self::TYP_OBJ) {
            $value[0] = \unserialize($value[0]);
        } else if ($value[1] == self::TYP_STR) {
            if (ArraySQL::$encoding) {
                $value[0] = \iconv("UTF-8", ArraySQL::$encoding.ArraySQL::$encoding_ignore, $value[0]);
            }
        }
    }

    public static function convertTo(&$value)
    {
        if (is_int($value)) {
            $typ = self::TYP_INT;
        } else if (is_float($value)) {
            $typ = self::TYP_FLOAT;
        } else if (is_null($value)) {
            $value = 'null';
            $typ = self::TYP_NULL;
        } else if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
            $typ = self::TYP_BOOL;
        } else if (is_object($value)) {
            $value = \serialize($value);
            $typ = self::TYP_OBJ;
        } else {
            if (ArraySQL::$encoding) {
              $value = \iconv(ArraySQL::$encoding, "UTF-8".ArraySQL::$encoding_ignore, $value);
            }
            $typ = self::TYP_STR;
        }

        return $typ;
    }
}

final class ArraySQLIterator implements \Iterator
{

    private $deep;
    private $id;
    /* @var $db \SQLite3 */
    private $db;
    private $offset;
    private $options;

    public function __construct($db, $id, $deep, $options)
    {
        $this->db = $db;
        $this->id = $id;
        $this->deep = $deep;
        $this->options = $options;
        $this->offset = null;
    }

    private function parseSql($select)
    {
        $sql = "SELECT";
        if (empty($select)) {
            $sql .= " * ";
        } else {
            $sql .= " " . implode(',', $select) . " ";
        }

        $sql .= "FROM store WHERE id={$this->id} AND deep={$this->deep}";

        if (isset($this->options['asort'])) {
            $sql .= $this->parseSort($this->options['asort'], 'value');
        } else if (isset($this->options['ksort'])) {
            $sql .= $this->parseSort($this->options['ksort'], 'key');
        }

        $sql .= " LIMIT 1 OFFSET {$this->offset}";
        return $sql;
    }

    private function parseSort($sort, $column)
    {
        $sql = "";
        switch($sort)
        {
            case SORT_ASC:
                $sql .= " ORDER BY $column ASC";
                break;
            case SORT_DESC:
                $sql .= " ORDER BY $column DESC";
                break;
            case SORT_REGULAR:
                $sql .= " ORDER BY $column";
                break;
        }
        return $sql;
    }

    public function current(): mixed
    {
        if (is_null($this->offset)) {
            return null;
        }

        $result = $this->db->query($this->parseSql(['value', 'typ']));
        $value = $result ? $result->fetchArray(SQLITE3_NUM) : [];

        if (isset($value[0]) && $value[1] == ArraySQLTyp::TYP_ARR) {
            return new ArraySQL($this->db, $this->id, $value[0], $this->options, !empty($this->options['call_shutdown']));
        }

        ArraySQLTyp::convertFrom($value);

        return $value[0];
    }

    public function next(): void
    {
        $this->offset++;
    }

    public function key(): mixed
    {
        if (is_null($this->offset)) {
            return null;
        }

        $result = $this->db->query($this->parseSql(['key', 'typ']));
        $value = $result ? $result->fetchArray(SQLITE3_NUM) : [];

        return isset($value[0]) ? $value[0] : null;
    }

    public function valid(): bool
    {
        if (is_null($this->offset)) {
            return false;
        }

        $typ = $this->db->querySingle($this->parseSql(['typ']));
        return !empty($typ);
    }

    public function rewind(): void
    {
        $this->offset = 0;
    }
}

final class ArraySQL implements \IteratorAggregate, \ArrayAccess, \Serializable, \Countable
{
    private $deep;
    private $id;
    private $db;
    private $options;
    private $call_shutdown;

    public static $encoding = "";
    public static $encoding_ignore = "//IGNORE";

    public function __construct($adapter = null, $id = null, $deep = null, $options = array(), $call_shutdown = true)
    {
        $this->call_shutdown = $call_shutdown;
        $this->id = (int) ($id ?: mt_rand());
        $this->db = $adapter ?: $this->getDbHandler();
        $this->options = [];

        if (is_null($deep)) {
            $this->deep = $this->id;
        } else if (is_numeric($deep)) {
            $this->deep = (int) $deep;
        } else {
            $this->deep = mt_rand();
        }

        self::correctAdapter($this->db);

        $this->db->exec("CREATE TABLE IF NOT EXISTS store (id INTEGER, deep INTEGER, key VARCHAR(1024), value TEXT, typ TINYINT)");

        if (!empty($options['index'])) {
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_key_value ON store (id, key, value) WHERE typ != ".ArraySQLTyp::TYP_ARR);
        }

        $this->init();
        $this->initOptions($options);
    }

    public function __toString()
    {
        return "ArraySQL";
    }

    private function getDbHandler()
    {
        $path = is_dir('/tmp') ? '/tmp' : '.';
        return new \SQLite3("$path/array.db");
    }

    public static function correctAdapter($adapter)
    {
        if (!(
            is_object($adapter)
            && method_exists($adapter, 'exec')
            && method_exists($adapter, 'query')
            && method_exists($adapter, 'querySingle')
        ))  {
            throw new \RuntimeException("adapter incorrect type");
        }
    }

    private function init()
    {
        if ($this->call_shutdown) {
            register_shutdown_function(function () {
                $this->clear();
            });
        }
    }

    private function initOptions($options = array())
    {
        if (isset($options['asort']) && is_int($options['asort'])) {
            $this->options['asort'] = $options['asort'];
        }
        if (isset($options['ksort']) && is_int($options['ksort'])) {
            $this->options['ksort'] = $options['ksort'];
        }
    }

    private function insertSQL($row): string
    {
        $sql = "INSERT INTO store (id,deep,key,value,typ) 
                    SELECT * FROM (VALUES (".$row[0].",".$row[1].",'".$row[2]."','".$row[3]."',".$row[4]."))
                    WHERE NOT EXISTS (SELECT * FROM store WHERE id=".$row[0]." AND deep=".$row[1]." AND key='".$row[2]."')";
       return $sql;
    }

    public function offsetExists($offset, $deep = null, $arr = false): bool
    {
        if(is_null($deep)) {
            $deep = $this->deep;
        }

        $result = $this->db->query("SELECT value, typ FROM store WHERE key='$offset' AND id={$this->id} AND deep=$deep");
        $value = $result ? $result->fetchArray(SQLITE3_NUM) : [];

        return $arr ? (isset($value[0]) && $value[1] == ArraySQLTyp::TYP_ARR)
                    : (isset($value[0]) && $value[1] != ArraySQLTyp::TYP_ARR);
    }

    public function offsetGet($offset): mixed
    {
        $result = $this->db->query("SELECT value, typ FROM store WHERE key='$offset' AND id={$this->id} AND deep={$this->deep}");
        $value = $result->fetchArray(SQLITE3_NUM);

        if (!isset($value[0])) {
            return null;
        } else if (isset($value[0]) && $value[1] == ArraySQLTyp::TYP_ARR) {
            return new self($this->db, $this->id, $value[0], [], $this->call_shutdown);
        }

        ArraySQLTyp::convertFrom($value);

        return $value[0];
    }

    public function offsetSet($offset, $value, $deep = null): void
    {
        if (is_null($deep)) {
            $deep = $this->deep;
        }

        if (self::is_array($value)) {
            $deep = mt_rand();
            if(!$this->offsetExists($offset, $deep, true)) {
                $this->db->exec($this->insertSQL([$this->id,$this->deep,$offset,$deep,ArraySQLTyp::TYP_ARR]));
            }
            $arr = new self($this->db, $this->id, $deep, [], $this->call_shutdown);
            foreach ($value as $d_key => $d_value) {
                $arr->offsetSet($d_key, $d_value, $deep);
            }
        } else {

            $typ = ArraySQLTyp::convertTo($value);

            if ($this->offsetExists($offset, $deep)) {
                $this->db->exec("UPDATE store SET value='$value', typ=$typ WHERE id={$this->id} AND deep=$deep AND key='$offset'");
            } else {
                $this->db->exec($this->insertSQL([$this->id,$deep,$offset,$value,$typ]));
            }
        }
    }

    public function offsetUnset($offset): void
    {
        $this->db->exec("DELETE FROM store WHERE key='$offset' AND id={$this->id} AND deep={$this->deep}");
    }

    public function offsetUnsetOfDeep($offset)
    {
        $deep = $this->db->querySingle("SELECT value FROM store WHERE key='$offset' AND id={$this->id} AND deep={$this->deep}");
        $this->offsetUnset($offset);
        $this->db->exec("DELETE FROM store WHERE id={$this->id} AND deep=$deep");
    }

    public function count(ArraySQLSearch $find = null): int
    {
        if ($find) {
            $sql = "SELECT COUNT(*) FROM store WHERE id={$this->id} AND deep={$this->deep} AND (".$find->whereSql().")";
        } else {
            $sql = "SELECT COUNT(*) FROM store WHERE id={$this->id} AND deep={$this->deep}";
        }
        return $this->db->querySingle($sql);
    }

    public function append($value) {
        $result = $this->db->query("SELECT key FROM store WHERE id={$this->id} AND deep={$this->deep}");
        $last = 0;
        while ($row = $result->fetchArray(SQLITE3_NUM)) {
            if (preg_match('/^\d+$/', $row[0]) && $last < $row[0]) {
                $last = $row[0];
            }
        }
        $key = $last + 1;
        $this->offsetSet($key, $value);
    }

    /**
     * Usuwa wszystkie dane w tablicy
     */

    public function clear()
    {
        $this->db->exec("DELETE FROM store WHERE id = {$this->id} AND deep = {$this->deep}");
    }

    /**
     * sortuje tablice po value
     * @param int $flags SORT_REGULAR|SORT_ASC|SORT_DESC
     */

    public function asort($flags = SORT_REGULAR)
    {
        $this->initOptions(['asort' => $flags]);
    }

    /**
     * sortuje tablice po key
     * @param int $flags SORT_REGULAR|SORT_ASC|SORT_DESC
     */

    public function ksort($flags = SORT_REGULAR)
    {
        $this->initOptions(['ksort' => $flags]);
    }

    /**
     * Przszukiwanie danych za pomoca zapytav SQL
     * example used $where: "AND value IN ('abc', '30') AND key > '2021-03-10'"
     * $recursive = true - przeszukuje wielowymiarow� tablic�
     * @param string $where
     * @param false $recursive
     */

    public function search(ArraySQLSearch $where, $recursive = false)
    {
        $search = [];
        $sql = "SELECT key, value ";
        if ($recursive) {
            $sql .= "FROM store WHERE id={$this->id} AND (" . $where->whereSql() . ") AND typ!=".ArraySQLTyp::TYP_ARR;
        } else {
            $sql .= "FROM store WHERE id={$this->id} AND deep={$this->deep} AND (" . $where->whereSql() .") AND typ!=".ArraySQLTyp::TYP_ARR;
        }

        $result = $this->db->query($sql);
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_NUM)) {
                if (is_string($row[0]) && ArraySQL::$encoding) {
                    $row[0] = \iconv("UTF-8", ArraySQL::$encoding.ArraySQL::$encoding_ignore, $row[0]);
                }
                if (is_string($row[1]) && ArraySQL::$encoding) {
                    $row[1] = \iconv("UTF-8", ArraySQL::$encoding.ArraySQL::$encoding_ignore, $row[1]);
                }
                $search[$row[1]][] = $row[0];
            }
        }
        return $search;
    }

    public function getIterator(): ArraySQLIterator
    {
        return new ArraySQLIterator($this->db, $this->id, $this->deep, array_merge($this->options, ['call_shutdown' => $this->call_shutdown]));
    }

    /**
     * Zaraca zwraca dane dablicy jako string parsowany do zapisu na format csv
     * @param string $delimiter
     * @return string
     */
    public function serialize($delimiter = ';'): string
    {
        $buffer = "";
        $result = $this->db->query("SELECT * FROM store WHERE id={$this->id}");
        while ($row = $result->fetchArray(SQLITE3_NUM)) {
            $buffer .= $row[0].$delimiter.$row[1].$delimiter.$row[2].$delimiter.$row[3].$delimiter.$row[4].PHP_EOL;
        }
        return $buffer;
    }

    /**
     * Zapisuje dane do tablicy ze stringu parsowanym na format csv
     * @param string $serialized
     * @param string $delimiter
     */

    public function unserialize($serialized, $delimiter = ';'): void
    {
        $data = explode(PHP_EOL, $serialized);
        $count = count($data);
        $id = null;

        if ($this->db == null) {
            $this->db = $this->getDbHandler();
        }

        if ($count > 0 && $this->id) {
            $this->db->exec("DELETE FROM store WHERE id = {$this->id}");
        }

        for ($i = 0; $i < $count; $i++) {
            $row = explode($delimiter, $data[$i]);
            if (is_numeric($row[0]) && is_numeric($row[1]) && $row[2] !== '') {
                $id = $row[0];
                $this->db->exec($this->insertSQL($row));
            }
        }

        if ($id) {
            $this->id = $id;
            $this->deep = 0;
        }
    }

    public function toArray(): array
    {
        $arr = [];
        foreach ($this as $key => $val) {
            if (self::is_array($val)) {
                $arr[$key] = $val->toArray();
            } else {
                $arr[$key] = $val;
            }
        }
        return $arr;
    }

    public function fromArray($array)
    {
        if (!\is_array($array)) {
            throw new \InvalidArgumentException("type is not array");
        }
        foreach ($array as $key => $val) {
            $this->offsetSet($key, $val);
        }
    }

    /**
     * Zwraca ilo�� pasuj�cych por�wna� z warto�ci� z danymi z tablicy
     * $recursive = true - przeszukuje wielowymiarow� tablic�
     * @param $value
     * @param false $recursive
     * @param false $strict
     * @return int
     */

    public function in_array($value, $recursive = false, $strict = false)
    {
        $find = 0;
        $sql = "SELECT value, typ FROM store WHERE id={$this->id}";
        if ($recursive == false) {
            $sql .= " AND deep={$this->deep}";
        }
        $result = $this->db->query($sql);
        while ($row = $result->fetchArray(SQLITE3_NUM)) {
            if ($row[1] != ArraySQLTyp::TYP_ARR) {
                ArraySQLTyp::convertFrom($row);
                if ($strict && $row[0] === $value) {
                    $find++;
                } else if ($row[0] == $value) {
                    $find++;
                }
            }
        }
        return $find;
    }

    /**
     * Zwaraca wszystkie klusze w tablicy
     * @return array
     */
    public function array_keys()
    {
        $result = $this->db->query("SELECT key FROM store WHERE id={$this->id} AND deep={$this->deep}");
        $keys = [];
        while ($row = $result->fetchArray(SQLITE3_NUM)) {
            $keys[] = $row[0];
        }
        return $keys;
    }

    /**
     * Sprawdza czy warto�� jest tablic� lub obiektem tablicowym
     * @param $value
     * @return bool
     */

    public static function is_array($value) {
        return (\is_array($value) || $value instanceof \ArrayAccess);
    }

    public function getStoreQuery()
    {
        return new StoreQuery($this->db, $this->id, $this->deep);
    }
}
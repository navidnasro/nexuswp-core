<?php

namespace nexuswp\core\database\model;

use InvalidArgumentException;
use nexuswp\core\Config;

#[\AllowDynamicProperties]
abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';

    // query state lives on the instance, so two queries can never share conditions
    protected array $conditions = [];
    protected array $conditionValues = [];
    protected string $orderBy = '';
    protected string $limit = '';

    public static function all()
    {
        return static::query("SELECT * FROM " . static::getTable());
    }

    public static function latest(bool $beCreatedAt = false)
    {
        $column = $beCreatedAt ? 'created_at' : 'updated_at';
        return (new static())->orderBy($column, 'DESC');
    }

    public static function find($id)
    {
        return static::queryRow(
            "SELECT * FROM " . static::getTable() . " WHERE " . static::$primaryKey . " = %d",
            [$id]
        );
    }

    public static function create($data)
    {
        static::getDB()->insert(static::getTable(), $data);
        return static::getDB()->insert_id;
    }

    public static function update($id, $data)
    {
        return static::getDB()->update(static::getTable(), $data, [static::$primaryKey => $id]);
    }

    public static function delete($id)
    {
        return static::getDB()->delete(static::getTable(), [static::$primaryKey => $id]);
    }

    public static function where($column, $operator, $value = null)
    {
        return (new static())->andWhere($column, $operator, $value);
    }

    public function andWhere($column, $operator, $value = null)
    {
        // If $value is null, assume the operator is '=' and $value is the second argument
        if ($value === null)
        {
            $value = $operator;
            $operator = '=';
        }

        // Validate the operator
        if (!in_array($operator, ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'IN']))
            return null;

        $column = $this->assertColumn($column);

        if (!empty($this->conditions))
            $this->conditions[] = "AND $column $operator %s";
        else
            $this->conditions[] = "$column $operator %s";

        $this->conditionValues[] = $value;

        return $this;
    }

    public function orWhere($column, $operator, $value = null)
    {
        if ($value === null)
        {
            $value = $operator;
            $operator = '=';
        }

        if (!in_array($operator, ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'IN']))
            return null;

        $column = $this->assertColumn($column);

        if (!empty($this->conditions))
            $this->conditions[] = "OR $column $operator %s";
        else
            $this->conditions[] = "$column $operator %s";

        $this->conditionValues[] = $value;

        return $this;
    }

    public function orderBy($column, $direction = 'ASC')
    {
        $column = $this->assertColumn($column);
        $this->orderBy = " ORDER BY $column " . (strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC');

        return $this;
    }

    public function limit($limit, $offset = 0)
    {
        $this->limit = " LIMIT " . max(0,(int)$limit) . " OFFSET " . max(0,(int)$offset);

        return $this;
    }

    public function get()
    {
        return static::query(
            "SELECT * FROM " . static::getTable() . $this->applyWhereClause() . $this->orderBy . $this->limit,
            $this->conditionValues
        );
    }

    public function first()
    {
        return static::queryRow(
            "SELECT * FROM " . static::getTable() . $this->applyWhereClause() . $this->orderBy . " LIMIT 1",
            $this->conditionValues
        );
    }

    public function paginate($perPage, $page = 1)
    {
        $perPage = max(1,(int)$perPage);
        $page = max(1,(int)$page);
        $offset = ($page - 1) * $perPage;
        $whereClause = $this->applyWhereClause();

        $total = static::queryValue("SELECT COUNT(*) FROM " . static::getTable() . $whereClause,$this->conditionValues);
        $data = static::query("SELECT * FROM " . static::getTable() . $whereClause . $this->orderBy . " LIMIT {$perPage} OFFSET {$offset}",$this->conditionValues);

        return [
            'data' => $data,
            'pagination' => [
                'total' => (int)$total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => (int)ceil($total / $perPage),
            ],
        ];
    }

    public function toArray(): array
    {
        $data = get_object_vars($this);

        unset($data['conditions'],$data['conditionValues'],$data['orderBy'],$data['limit']);

        return $data;
    }

    protected function applyWhereClause(): string
    {
        if (empty($this->conditions))
            return '';

        return ' WHERE ' . implode(' ',$this->conditions);
    }

    /**
     * column names can not be bound, so they are checked against a strict pattern
     */
    private function assertColumn(string $column): string
    {
        if (!preg_match('/^[a-zA-Z0-9_.]+$/',$column))
            throw new InvalidArgumentException("Invalid column name: {$column}");

        return $column;
    }

    protected static function getTable(string $tableName = ''): string
    {
        $tableName = !empty($tableName) ? $tableName : static::$table;
        return static::getDB()->prefix . Config::tablePrefix() . $tableName;
    }

    protected static function getDB()
    {
        global $wpdb;
        return $wpdb;
    }

    protected static function query($sql, $params = [], $class = '')
    {
        $results = static::getDB()->get_results(empty($params) ? $sql : static::getDB()->prepare($sql, ...$params));

        return $results ? static::hydrate($results,true,$class) : null;
    }

    protected static function queryRow($sql, $params = [], $class = '')
    {
        $row = static::getDB()->get_row(empty($params) ? $sql : static::getDB()->prepare($sql, ...$params));

        return $row ? static::hydrate($row,false,$class) : null;
    }

    protected static function queryValue($sql, $params = [])
    {
        return static::getDB()->get_var(empty($params) ? $sql : static::getDB()->prepare($sql, ...$params));
    }

    public static function hydrate($data, bool $multiple = false, string $class = '')
    {
        $class = !empty($class) ? $class : static::class;

        if ($multiple)
        {
            $instances = [];

            foreach ($data as $object)
            {
                $instance = new $class();

                foreach ($object as $key => $value)
                    $instance->$key = $value;

                $instances[] = $instance;
            }

            return $instances;
        }

        else
        {
            $instance = new $class();

            foreach ($data as $key => $value)
                $instance->$key = $value;

            return $instance;
        }
    }

    public static function startTransaction(): void
    {
        self::getDB()->query('START TRANSACTION');
    }

    public static function commit(): void
    {
        self::getDB()->query('COMMIT');
    }

    public static function rollback(): void
    {
        self::getDB()->query('ROLLBACK');
    }
}

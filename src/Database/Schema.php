<?php

declare(strict_types = 1);

namespace DBLaci\Data\Database;

use DBLaci\Data\Database\mysql\SchemaMysql;
use DBLaci\Data\Database\pgsql\SchemaPgsql;

abstract class Schema
{
    public const DRIVER_MYSQL = 'mysql';
    public const DRIVER_PGSQL = 'pgsql';

    public const SCHEMA_MAP = [
        self::DRIVER_MYSQL => SchemaMysql::class,
        self::DRIVER_PGSQL => SchemaPgsql::class,
    ];

    /**
     * Character used to quote table names.
     *
     * @var string
     */
    protected string $tableQuoteCharacter = '"';

    /**
     * Character used to quote column names;
     *
     * @var string
     */
    protected string $columnQuoteCharacter = '"';

    /**
     * Return schema object by driver name.
     *
     * @param string $driverName
     * @return Schema
     */
    public static function getSchemaByDriverName(string $driverName): Schema
    {
        if (array_key_exists($driverName, static::SCHEMA_MAP)) {
            $class = static::SCHEMA_MAP[$driverName];
            return new $class;
        }

        throw new \LogicException('Not supported database: ' . $driverName);
    }

    /**
     * Quoting table name.
     *
     * @param string $tableName
     * @return string
     */
    public function quoteTableName(string $tableName): string
    {
        return $this->tableQuoteCharacter . $tableName . $this->tableQuoteCharacter;
    }

    /**
     * Quoting column name.
     *
     * @param string $columnName
     * @return string
     */
    public function quoteColumnName(string $columnName): string
    {
        return $this->columnQuoteCharacter . $columnName . $this->columnQuoteCharacter;
    }
}

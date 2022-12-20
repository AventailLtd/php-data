<?php

namespace DBLaci\Data;

use DBLaci\Data\Database\Schema;
use PDO;

/**
 * Generic model class
 */
abstract class Etalon2
{
    /**
     * this MUST be overridden
     */
    const TABLE = 'please.set';
    /**
     * can be overridden if needed - autoincrementation is assumed
     */
    const COL_ID = 'id';

    /**
     * table column properties
     *
     * autoamtically with php cli:
     * include_once('config.php');echo "    public \$".implode(" = '';\n    public \$", Site::getDB()->getTableFields('tools'))." = '';\n";
     */

    /**
     * mandatory table id column (not necessarily equals 'id' see COL_ID const)
     */
    public int $id;
    /**
     * add to dbColumns if you want this to be set automatically
     * mysql timestamp default null
     */
    protected ?string $created_at;
    /**
     * add to dbColumns if you want this to be set automatically
     * mysql timestamp default null
     */
    protected ?string $updated_at;
    /**
     * add to dbColumns if you want this to be used automatically
     *
     * mysql timestamp default null
     */
    protected ?string $deleted_at;

    /**
     * soft delete bool - optional
     * @deprecated please use $deleted_at instead
     */
    protected int $deleted = 0;

    /**
     * table columns
     *
     * autoamtically with php cli:
     * include_once('config.php');echo "    '".implode("',\n    '", Site::getDB()->getTableFields('tools'))."'\n";
     *
     * @var string[]
     */
    public static array $dbColumns = [
        'id',
    ];

    /**
     * you can set the needed id on insert. this is bad practice tough.
     */
    protected ?int $id_to_set = null;

    /**
     * the database state (as we know)
     */
    protected array $dbCache = [];

    /**
     * Contains the saved or to be saved columns and old/new data - can contain zero element.
     * [
     *   'column' => [old, new]
     *   ...
     * ],
     */
    public array $saveDiff = [];

    /**
     * true when insert occured on last save
     */
    protected bool $_newRecord = false;

    /**
     * updated_at / created_at date updated automatically if this is true
     */
    protected bool $dateTriggersEnabled = true;

    /**
     * the database connection
     *
     * @return PDO
     */
    abstract protected static function getDB(): PDO;

    /**
     * @param int $id
     * @return static
     * @throws EtalonInstantiationException
     */
    public static function getInstanceByID(int $id)
    {
        $schema = static::getDatabaseSchema();
        $sql = 'SELECT * FROM ' . $schema->quoteTableName(static::TABLE) . ' WHERE ' . $schema->quoteColumnName(static::COL_ID) . ' = :id';
        $stmt = static::getDB()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new EtalonInstantiationException('id = "' . $id . '"');
        }

        return static::getInstanceFromRow($row);
    }

    /**
     * create instance from database row (array)
     * you can override for differentiate your class
     *
     * @param array $row
     * @return static
     */
    public static function getInstanceFromRow(array $row)
    {
        return static::getInstanceFromRowBase($row);
    }

    /**
     * create instance from database row (array)
     *
     * @param array $row
     * @return static
     */
    protected static function getInstanceFromRowBase(array $row)
    {
        $_t = new static;
        foreach (static::$dbColumns as $col) {
            if (!array_key_exists($col, $row)) {
                // it is possible that in the db row the column is missing (yet).
                // in this case, we keep the property as is, it might have a sane default.
                // altough this is an inconsistency between the property and the column list!
                continue;
            }
            if ($col === static::COL_ID) {
                $_t->id = $row[$col];
            } else {
                $_t->$col = $row[$col];
            }
        }
        $_t->dbCache = $row; // raw
        $_t->onDBLoad();
        $_t->cacheStore();
        return $_t;
    }

    /**
     * on creating new instance from database
     *
     * @abstract
     */
    public function onDBLoad()
    {
    }

    /**
     * disables the automatic updated_at / created_at setters
     */
    public function disableDateTriggers()
    {
        $this->dateTriggersEnabled = false;
    }

    /**
     * you can reload the data from database if you know there are changes.
     *
     * @param bool $updateProperties update properties from database. You can disable this to keep current values
     * @return self
     * @throws EtalonInstantiationException
     */
    public function reloadDBCache(bool $updateProperties = true): self
    {
        $schema = static::getDatabaseSchema();
        $sql = 'SELECT * FROM ' . $schema->quoteTableName(static::TABLE) . ' WHERE ' . $schema->quoteColumnName(static::COL_ID) . ' = :id';
        $stmt = static::getDB()->prepare($sql);
        $stmt->execute(['id' => $this->id]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new EtalonInstantiationException('ID does not exist anymore: ' . $this->id);
        }
        if ($updateProperties) {
            foreach (static::$dbColumns as $col) {
                if ($col !== static::COL_ID) {
                    $this->$col = $row[$col];
                }
            }
        }
        $this->dbCache = $row;
        $this->onDBLoad();
        return $this;
    }

    /**
     * if the class uses the standard updated_at column, we update it on change
     *
     * @return bool
     */
    protected function hasUpdatedAtColumn(): bool
    {
        return in_array('updated_at', static::$dbColumns, true);
    }

    /**
     * if the class uses the standard created_at column, we set it on insert
     *
     * @return bool
     */
    protected function hasCreatedAtColumn(): bool
    {
        return in_array('created_at', static::$dbColumns, true);
    }

    /**
     * if the class uses the standard created_at column, we set it on insert
     *
     * @return bool
     */
    protected function hasDeletedAtColumn(): bool
    {
        return in_array('deleted_at', static::$dbColumns, true);
    }

    /**
     * you can code validation here if you want before inserting
     */
    protected function onBeforeInsert()
    {
        if ($this->dateTriggersEnabled && $this->hasCreatedAtColumn()) {
            $this->created_at = date('Y-m-d H:i:s');
        }
    }

    /**
     * returns the changes that would have been saved
     *
     * @return array [column -> [old, new], ...] can be empty
     */
    public function savePreview(): array
    {
        $this->saveDiff = [];
        foreach (static::$dbColumns as $col) {
            if ($col === static::COL_ID) {
                $data = $this->id ?? null;
            } else {
                $data = $this->$col ?? null; // php 7.4 uninitialized
            }
            if (!$this->exists() && $data !== null) {
                $this->saveDiff[$col] = [$this->dbCache[$col], $data];
            } elseif (array_key_exists($col, $this->dbCache) && $data !== $this->dbCache[$col]) {
                // Only changed if it exists in the cache and differs
                $this->saveDiff[$col] = [$this->dbCache[$col], $data];
            }
        }
        return $this->saveDiff;
    }

    /**
     * check if save would do anything
     *
     * @return bool
     */
    public function isChanged(): bool
    {
        return count($this->savePreview()) !== 0;
    }

    /**
     * make additional changes before save (on change)
     * please call the parent if you override and return true if the parent returns true.
     * but don't return false if the parent returns false - if other changes were made.
     *
     * @return bool must return true if changes were made!
     */
    protected function onChangeBeforeSave(): bool
    {
        if ($this->dateTriggersEnabled && $this->hasUpdatedAtColumn()) {
            $this->updated_at = date('Y-m-d H:i:s');
            return true;
        }
        return false;
    }

    /**
     * Any code after save (for example logging)
     *
     * @param array $changeList [column => [0 => oldvalue, 1 => newvalue]...]
     * @return void
     */
    protected function onChangeAfterSave(array $changeList)
    {
        // Detect HistoryAbstract trait - trait detection is hard when the class has extends, so we check the method for existence
        if (method_exists($this, 'logChangesToHistory')) {
            /** @var $this Etalon2|History */
            $this->logChangesToHistory($changeList);
        }
    }

    /**
     * save data to database if changed
     *
     * @param boolean $insert if you want to insert on not existing you have to set to true. this is to prevent accidental inserts
     *
     * @return void
     * @throws EtalonInsertNotAllowedException
     */
    public function save(bool $insert = false)
    {
        if (!$this->exists()) {
            if ($insert) {
                $this->insert();
                return;
            } else {
                throw new EtalonInsertNotAllowedException('insert not allowed');
            }
        }
        $this->_newRecord = false;
        $_changed = $this->savePreview(); // fill saveDiff (used by insert!)

        //van bármi változás? ha nincs, akkor kész (és siker)
        if (count($_changed) === 0) {
            return;
        }
        if ($this->onChangeBeforeSave()) {
            // recalculate changes
            $_changed = $this->savePreview();
            if (count($_changed) === 0) {
                // there is a chance changes were revoked.
                return;
            }
        }

        $update = [];
        foreach ($_changed as $col => $change0) {
            $update[$col] = $change0[1];
        }

        $db = static::getDB();
        $db->prepare(
            static::getUpdateSql(array_keys($update),
            static::getDatabaseSchema()->quoteColumnName(static::COL_ID) . ' = :id')
        )->execute(array_merge($update, ['id' => $this->id]));

        // update cache - we assume the changes were made.
        foreach ($update as $col => $val) {
            $this->dbCache[$col] = $val;
        }
        $this->onChangeAfterSave($_changed);
    }

    /**
     * inserts data to database
     *
     * @throws EtalonInsertNotAllowedException
     */
    public function insert()
    {
        if ($this->exists()) {
            throw new EtalonInsertNotAllowedException('Already exists. id: ' . $this->id);
        }

        // in the change callbacks, the developer assumes changeDiff to be initialized.
        $this->savePreview();

        $this->onBeforeInsert();
        $this->onChangeBeforeSave(); // every insert is an update also
        $insert = [];
        foreach (static::$dbColumns as $col) {
            if ($col === static::COL_ID) {
                continue; //ezt nem mentjük, vagy nem itt.
            }
            if (isset($this->$col)) {
                $insert[$col] = $this->$col;
            }
        }
        if (isset($this->id_to_set)) {
            $insert[static::COL_ID] = $this->id_to_set;
        }

        $db = static::getDB();
        $db->prepare(static::getInsertSql(array_keys($insert)))->execute($insert);
        $this->id = $db->lastInsertId();

        // every column is changed.
        $this->saveDiff = [];
        foreach (static::$dbColumns as $col) {
            if ($col === static::COL_ID) {
                $data = $this->id;
            } else {
                $data = isset($this->$col) ? $this->$col : null;
            }
            if (!is_null($data)) {
                $this->saveDiff[$col] = [null, $data];
            }
            $this->dbCache[$col] = $data;
        }
        $this->_newRecord = true;
        $this->onChangeAfterSave($this->saveDiff);
    }

    /**
     * is it inserted yet?
     *
     * @return boolean
     */
    public function exists(): bool
    {
        return isset($this->id);
    }

    /**
     * for debug purposes
     *
     * @return string
     */
    public function getDebugTitle(): string
    {
        if ($this->exists()) {
            return (string) $this->id;
        } else {
            return '[NEW]';
        }
    }

    /**
     * create an uninserted new instance from existing data
     *
     * @param static $old
     * @return static
     */
    public static function getInstanceNewFromExisting(self $old)
    {
        $t = new static;
        foreach (static::$dbColumns as $col) {
            if ($col === static::COL_ID) {
                continue;
            }
            $t->$col = $old->$col;
        }
        return $t;
    }

    /**
     * 0 or more cache criteria
     * [
     *   ['id'],
     *   ['filename', 'lang'],
     * ]
     *
     * We can use these keys (columns) when using cache.
     * This cache is only valid in the current run.
     * Use in these cases:
     * - you create instance multiple times during running
     * - you can preload the cache if you want to use instantination later
     *
     * We cache empty objects too:
     * - negative cache
     * - prevent duplicate inserts
     *
     * We assume the cache key is not changed!
     *
     * You have to override static::$cacheByCriteria - to prevent using shared cache with Etalon2
     *
     * @var array[]
     */
    protected static array $cacheCriteriaList;

    /**
     * cache content grouped by cache type
     *
     * [
     *   'user_by_email' => [
     *     'any_unique_identifier' => object
     *   ],
     * ]
     *
     * You have to override this with null default value because you don't want to use shared with this parent class.
     *
     * @var array[]
     */
    protected static array $cacheByCriteria;

    /**
     * disable caching (for this run)
     */
    public static function disableCache()
    {
        static::$cacheCriteriaList = null;
    }

    public static function debugCache()
    {
        var_export(static::$cacheByCriteria);
        echo "\n";
    }

    /**
     * called on creating new instance - this will maintain cache
     *
     * @return void
     */
    protected function cacheStore()
    {
        if (!isset(static::$cacheCriteriaList)) {
            return; // nemgond, csak kész.
        }
        foreach (static::$cacheCriteriaList as $criteria) {
            $criteria_key = implode('.', $criteria);
            $key = '';
            foreach ($criteria as $col) {
                if ($key !== '') {
                    $key .= '.';
                }
                $key .= $this->$col;
            }
            static::$cacheByCriteria[$criteria_key][$key] = $this;
        }
    }

    /**
     * exception means no cache. Not necessarily error!
     *
     * @param string $criteria_key
     * @param string $key
     * @return static
     * @throws EtalonInstantiationException
     */
    protected static function getInstanceFromCache(string $criteria_key, string $key)
    {
        if (!isset(static::$cacheByCriteria) || !array_key_exists($criteria_key, static::$cacheByCriteria)) {
            throw new EtalonInstantiationException('Empty cache on criteria key: ' . $criteria_key);
        }
        if (array_key_exists($key, static::$cacheByCriteria[$criteria_key])) {
            return static::$cacheByCriteria[$criteria_key][$key];
        } else {
            throw new EtalonInstantiationException('Not found in cache: ' . $criteria_key . ':' . $key);
        }
    }

    /**
     * @throws EtalonInsertNotAllowedException
     */
    public function delete()
    {
        if ($this->isDeleted()) {
            return;
        }
        if ($this->hasDeletedAtColumn()) {
            $this->deleted_at = date('Y-m-d H:i:s');
        } elseif (!in_array('deleted', static::$dbColumns, true)) {
            // if neither deleted* column is used, soft delete cannot work.
            throw new EtalonInvalidCallException('No deleted_at and deleted columnt. Soft delete is not available');
        }
        $this->deleted = 1;
        $this->save();
        $this->onDelete();
    }

    /**
     * event callback
     *
     * @abstract - not really, but you can override if needed
     */
    protected function onDelete()
    {
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
        if ($this->hasDeletedAtColumn()) {
            return $this->deleted_at !== null;
        }
        return $this->deleted !== 0;
    }

    /**
     * set properties to default values (after delete for example)
     * Note: you cannot use this with undefined default properties!
     */
    protected function resetValues()
    {
        $x = new static;
        foreach (static::$dbColumns as $col) {
            if (!isset($x->$col)) {
                unset($this->$col);
            } else {
                $this->$col = $x->$col;
            }
        }
    }

    /**
     * delete from table.
     * deleting non inserted is not error.
     *
     * @return void
     */
    public function deleteFromDB()
    {
        if (!$this->exists()) {
            return;
        }

        $schema = static::getDatabaseSchema();
        $sql = 'DELETE FROM ' . $schema->quoteTableName(static::TABLE) . ' WHERE ' . $schema->quoteColumnName(static::COL_ID) . ' = :id';
        $stmt = static::getDB()->prepare($sql);
        $stmt->execute(['id' => $this->id]);

        unset($this->id);
    }

    /**
     * lock table
     */
    public static function lockTable()
    {
        static::getDB()->exec('LOCK TABLE ' . static::TABLE . ' WRITE');
    }

    /**
     * unlock
     */
    public static function unlockTable()
    {
        static::getDB()->exec('UNLOCK TABLES');
    }

    /**
     * was the last save is an insert?
     * you don't want to use this without save - thus the exception is thrown.
     *
     * @return bool
     * @throws EtalonInvalidCallException
     */
    public function isInserted(): bool
    {
        if ($this->_newRecord) {
            return true;
        }
        if (!$this->exists()) {
            throw new EtalonInvalidCallException('no insert was called (or failed)!');
        }
        return false;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id_to_set = $id;
    }

    /**
     * Returns the name of the DB driver.
     *
     * @return string
     */
    private static function getDatabaseDriverName(): string
    {
        return static::getDB()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Returns the schema object.
     *
     * @return Schema
     */
    public static function getDatabaseSchema(): Schema
    {
        return Schema::getSchemaByDriverName(static::getDatabaseDriverName());
    }

    /**
     * Return "INSERT" sql (prepared) statement string
     *
     * @param string[] $columns - Column list, e.g.: ['title', 'type', ...]
     * @return string
     */
    private static function getInsertSql(array $columns): string
    {
        $schema = static::getDatabaseSchema();
        $quotedColumns = [];
        $placeholders = [];

        foreach ($columns as $column) {
            $quotedColumns[] = $schema->quoteColumnName($column);
            $placeholders[] = ':' . $column;
        }

        $sql = 'INSERT INTO ' . $schema->quoteTableName(static::TABLE);
        $sql .= ' (' . implode(', ', $quotedColumns) . ')';
        $sql .= ' VALUES (' . implode(', ', $placeholders) . ')';

        return $sql;
    }

    /**
     * Return "UPDATE" sql statement.
     *
     * @param array $columns - Column list, e.g.: ['title', 'type', ...]
     * @param string $where - WHERE statement without "WHERE" keyword.
     * @return string
     */
    private static function getUpdateSql(array $columns, string $where): string
    {
        $schema = static::getDatabaseSchema();
        $quotedColumnsWithPlaceholders = [];
        foreach ($columns as $column) {
            $quotedColumnsWithPlaceholders[] = $schema->quoteColumnName($column) . ' = :' . $column;
        }

        $sql = 'UPDATE ' . $schema->quoteTableName(static::TABLE);
        $sql .= ' SET ' . implode(', ', $quotedColumnsWithPlaceholders);
        $sql .= ' WHERE ' . $where;

        return $sql;
    }
}

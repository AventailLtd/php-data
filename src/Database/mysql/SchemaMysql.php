<?php

declare(strict_types = 1);

namespace DBLaci\Data\Database\mysql;

use DBLaci\Data\Database\Schema;

class SchemaMysql extends Schema
{
    /**
     * @inheritdoc
     */
    protected string $tableQuoteCharacter = '`';

    /**
     * @inheritdoc
     */
    protected string $columnQuoteCharacter = '`';
}

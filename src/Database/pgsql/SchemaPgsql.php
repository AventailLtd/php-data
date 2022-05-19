<?php

declare(strict_types = 1);

namespace DBLaci\Data\Database\pgsql;

use DBLaci\Data\Database\Schema;

class SchemaPgsql extends Schema
{
    /**
     * @inheritdoc
     */
    protected string $tableQuoteCharacter = '"';

    /**
     * @inheritdoc
     */
    protected string $columnQuoteCharacter = '"';
}

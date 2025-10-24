# PromptSQL

PromptSQL is a secure PHP 8.2+ library that converts natural language questions into read-only SQL queries using OpenAI, and executes them safely against MySQL or PostgreSQL databases via PDO.

- Only SELECT queries allowed
- Prepared statements with named parameters
- Strict SQL validation with allow-listed tables/columns
- Read-only database user recommended
- Modern PHP 8.2 features, PSR-12
- Easy integration as a Composer package
- Testable via clear interfaces

## Installation

```bash
composer require jona-odoh/promptsql
```

## Requirements

- PHP 8.2+
- ext-pdo, ext-json
- MySQL or PostgreSQL
- OpenAI API key

## Quick Start

```php
<?php

use PromptSQL\Config\PromptSQLConfig;
use PromptSQL\Database\PdoDatabaseAdapter;
use PromptSQL\Enums\DatabaseDriver;
use PromptSQL\OpenAI\GuzzleOpenAIClient;
use PromptSQL\OpenAI\OpenAIBasedSqlGenerator;
use PromptSQL\PromptSQL;
use PromptSQL\Validation\SqlValidator;

require __DIR__ . '/vendor/autoload.php';

// 1) Configure
$config = new PromptSQLConfig(
    driver: DatabaseDriver::PostgreSQL,
    dsn: 'pgsql:host=127.0.0.1;port=5432;dbname=app_db',
    username: 'readonly_user',
    password: 'readonly_password',
    openAiApiKey: getenv('OPENAI_API_KEY') ?: '',
    openAiModel: 'gpt-4o-mini',
    pdoOptions: [],
    allowList: [
        // Table => allowed columns (empty array means all columns allowed)
        'users' => ['id', 'email', 'name', 'created_at'],
        'orders' => ['id', 'user_id', 'total', 'status', 'created_at'],
    ],
    allowSelectStar: false, // enforce explicit columns
    tableDescriptions: [
        'users' => 'Application users.',
        'orders' => 'Purchase orders placed by users.',
    ],
    columnDescriptions: [
        'users' => [
            'email' => 'Unique user email',
            'created_at' => 'Signup datetime',
        ],
        'orders' => [
            'status' => 'Order status (e.g., paid, pending, shipped)',
        ],
    ],
);

// 2) Wire dependencies (constructor DI)
$openAI = new GuzzleOpenAIClient($config->openAiApiKey);
$generator = new OpenAIBasedSqlGenerator($openAI, $config->openAiModel);
$validator = new SqlValidator();
$db = new PdoDatabaseAdapter(
    driver: $config->driver,
    dsn: $config->dsn,
    username: $config->username,
    password: $config->password,
    options: $config->pdoOptions
);

// 3) Ask natural language questions
$promptSQL = new PromptSQL($config, $generator, $validator, $db);

$result = $promptSQL->ask('Show the top 10 most recent orders with user emails.');

if ($result->status->value === 'executed') {
    foreach ($result->rows as $row) {
        print_r($row);
    }
} else {
    echo "Error: {$result->message}\n";
}
```

## Security

PromptSQL enforces the following:
- SELECT-only: Non-read operations are rejected.
- Single statement: No statement chaining.
- Prepared statements: All values must be parameterized.
- Allow-list: Only configured tables (and optionally columns) are permitted.
- Read-only DB user: You must supply a DB user with read-only permissions.

Note: The library uses robust heuristics for SQL validation, but it is not a full SQL parser. Keep your allow-lists tight, avoid `SELECT *` (default), and ensure your DB user is read-only.

## Configuration

- `allowList`: `['table' => ['col1', 'col2'], 'table2' => []]` where an empty array means all columns allowed for that table.
- `allowSelectStar`: When `false`, queries using `SELECT *` are rejected.
- `tableDescriptions`, `columnDescriptions`: Optional context sent to the model to improve column/table selection.

## Extensibility

- Replace `OpenAIBasedSqlGenerator` with your own implementation of `NaturalLanguageToSQLInterface`.
- Swap out the DB adapter by implementing `DatabaseAdapterInterface`.
- Swap/extend validation by implementing `QueryValidatorInterface`.

## Testing

```bash
composer test
```

## License

MIT
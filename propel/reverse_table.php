<?php

require_once '../vendor/autoload.php';

/*
 * script which make reverse for single table
 * require parameters --table and --db or -t -d
 * example: reverse_table.php -t jezyki -d mops_pociagi
 */

use Propel\Generator\Config\GeneratorConfig;
use Propel\Generator\Model\Database;
use Propel\Generator\Model\IdMethod;
use Propel\Generator\Model\Table;
use Propel\Generator\Reverse\PgsqlSchemaParser;
use Propel\Generator\Schema\Dumper\XmlDumper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class PgsqlSchemaParserCustom extends PgsqlSchemaParser {

    public function parse(Database $database, array $additionalTables = [])
    {
        $tableWraps = [];
        foreach ($additionalTables as $table) {
            $this->parseTables($tableWraps, $database, $table);
        }

        // Now populate only columns.
        foreach ($tableWraps as $wrap) {
            $this->addColumns($wrap->table, $wrap->oid);
        }

        // Now add indexes and constraints.
        foreach ($tableWraps as $wrap) {
            $this->addForeignKeys($wrap->table, $wrap->oid);
            $this->addIndexes($wrap->table, $wrap->oid);
            $this->addPrimaryKey($wrap->table, $wrap->oid);
        }

        $this->addSequences($database);

        return count($tableWraps);
    }

}

$root = dirname(dirname(__DIR__));

try {

    $options = new InputDefinition();

    $options->addOption(new InputOption('table', '-t', InputOption::VALUE_REQUIRED, 'table name will be make reverse'));

    $options->addOption(new InputOption('db', '-d', InputOption::VALUE_REQUIRED, 'database name for model'));

    $input  = new ArgvInput(null, $options);

    if (!file_exists($root . DIRECTORY_SEPARATOR . 'propel.yml')) {
        throw new \RuntimeException('not exists ' . $root . DIRECTORY_SEPARATOR . 'propel.yml');
    }

    $propelConfig = new GeneratorConfig($root . DIRECTORY_SEPARATOR . 'propel.yml');

    $extraConf = $propelConfig->get();

    $databaseName = $input->getOption('db');
    $tableName = $input->getOption('table');

    if (empty($databaseName) || empty($tableName)) {
        throw new InvalidArgumentException("incorrect parameters or not exists");
    }

    $connection = $propelConfig->getConnection($databaseName);

    $database = new Database($databaseName);

    $database->setPlatform($propelConfig->getConfiguredPlatform($connection), $databaseName);

    $database->setDefaultIdMethod(IdMethod::NATIVE);

    $parser =  new PgsqlSchemaParserCustom($connection);

    $parser->setGeneratorConfig($propelConfig);

    $parseTable[] = new Table($tableName);

    $nbTables = $parser->parse($database, $parseTable);

    if ($nbTables > 0) {

        $schema = (new XmlDumper())->dump($database);

        $path = $propelConfig->getSection('paths');

        if (empty($path['outputDir'])) {
            throw new InvalidArgumentException("path outputDir in propel.yml not set");
        }

        if (!is_dir($path['outputDir'])) {
            throw new InvalidArgumentException("dictionary not exists: " . $path['outputDir']);
        }

        $file = $path['outputDir'] . DIRECTORY_SEPARATOR . $tableName . '.xml';

        file_put_contents($file, $schema);

        echo "created file $file \n";
    } else {
        echo "table $tableName not exists in database $databaseName \n";
    }

} catch (Exception $ex) {
    echo $ex->getMessage() . PHP_EOL;
}



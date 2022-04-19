<?php

require_once '../vendor/autoload.php';

/*
 * script which make class files by table
 * require parameters --table and --db or -t -d
 * example: make_model.php -t jezyki -d mops_pociagi
 */

use Propel\Generator\Command\ModelBuildCommand;
use Propel\Generator\Manager\ModelManager;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ModelManagerCustom extends ModelManager {

    private $renderTable;

    private $renderDb;

    /**
     * @return mixed
     */
    public function getRenderDb()
    {
        return $this->renderDb;
    }

    /**
     * @param mixed $renderDb
     */
    public function setRenderDb($renderDb)
    {
        $this->renderDb = $renderDb;
    }

    /**
     * @return mixed
     */
    public function getRenderTable()
    {
        return $this->renderTable;
    }

    /**
     * @param mixed $renderTable
     */
    public function setRenderTable($table)
    {
        $this->renderTable = $table;
    }

    public function getDataModels()
    {
        $dataModels = parent::getDataModels();

        foreach ($dataModels as $dataModel) {
            foreach ($dataModel->getDatabases() as $database) {
                foreach ($database->getTables() as $table) {
                    if ($table->getName() !== $this->getRenderTable() || $database->getName() !== $this->getRenderDb()){
                        $table->setForReferenceOnly();
                    }
                }
            }
        }

        return $dataModels;
    }
}

class ModelBuildCommandCustom extends ModelBuildCommand {

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configOptions = [];

        $generatorConfig = $this->getGeneratorConfig($configOptions, $input);
        $this->createDirectory($generatorConfig->getSection('paths')['phpDir']);

        $manager = new ModelManagerCustom();
        $manager->setFilesystem($this->getFilesystem());
        $manager->setGeneratorConfig($generatorConfig);
        $manager->setSchemas($this->getSchemas($generatorConfig->getSection('paths')['schemaDir'], $generatorConfig->getSection('generator')['recursive']));
        $manager->setLoggerClosure(function ($message) use ($input, $output) {
            echo $message . PHP_EOL;
        });
        $manager->setWorkingDirectory($generatorConfig->getSection('paths')['phpDir']);
        $manager->setRenderTable($input->getOption('table'));
        $manager->setRenderDb($input->getOption('db'));
        echo "Build: " .$manager->getRenderTable() . " from " . $manager->getRenderDb() . PHP_EOL;
        $manager->build();

        return static::CODE_SUCCESS;
    }
}

try {

    if (count($argv) < 1) {
        throw new \InvalidArgumentException('invalid arguments');
    }

    array_shift($argv);

    $root = dirname(dirname(__DIR__));

    if (!file_exists($root . DIRECTORY_SEPARATOR . 'propel.yml')) {
        throw new \RuntimeException('not exists ' . $root . DIRECTORY_SEPARATOR . 'propel.yml');
    }

    $arguments = array_merge(
        ['model:build', '--config-dir='.$root],
        $argv
    );

    $model = new ModelBuildCommandCustom();
    $model->addOption('verbose');
    $model->addOption('table', '-t', InputOption::VALUE_REQUIRED, 'table name will be make model');
    $model->addOption('db', '-d', InputOption::VALUE_REQUIRED, 'database name for model');
    $model->run(new ArgvInput($arguments), new ConsoleOutput());

} catch (\Exception $ex) {
    echo $ex->getMessage() . PHP_EOL;
}



#!/usr/bin/php
<?php
require __DIR__ . './vendor/autoload.php';

use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Options;

require __DIR__. './camila/cli/Exception.php';
require __DIR__. './camila/cli/TableFormatter.php';
require __DIR__. './camila/cli/Options.php';
require __DIR__. './camila/cli/Base.php';
require __DIR__. './camila/cli/Colors.php';
require __DIR__. './camila/cli/CLI.php';

class CamilaMasterCli extends CLI
{
    protected function setup(Options $options)
    {
		$this->registerDefaultCommands($options);
		$this->registerMasterCommands($options);
    }

    protected function main(Options $options)
    {
		$this->handleMasterCommands($options);
    }
}

$cli = new CamilaMasterCli();
$cli->run();
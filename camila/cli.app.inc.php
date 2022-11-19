<?php
require_once 'cli/Exception.php';
require_once 'cli/TableFormatter.php';
require_once 'cli/Options.php';
require_once 'cli/Base.php';
require_once 'cli/Colors.php';
require_once 'cli/CLI.php';

use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Options;
//use splitbrain\phpcli\Exception;

class CamilaAppCli extends CLI
{
	protected function setup(Options $options)
    {
		$this->registerDefaultCommands($options);
		$this->registerAppCommands($options);
    }

    protected function main(Options $options)
    {
		$this->handleAppCommands($options);
    }
}

?>
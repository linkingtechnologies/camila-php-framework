<?php
/*  This File is part of Camila PHP Framework
    Copyright (C) 2006-2024 Umberto Bresciani

    Camila PHP Framework is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Camila PHP Framework is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Camila PHP Framework. If not, see <http://www.gnu.org/licenses/>. */
	
require_once 'cli/Exception.php';
require_once 'cli/TableFormatter.php';
require_once 'cli/Options.php';
require_once 'cli/Base.php';
require_once 'cli/Colors.php';
require_once 'cli/CLI.php';

use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Options;
//use splitbrain\phpcli\Exception;

class CamilaRunnerCli extends CamilaAppCli
{

	public function __construct($autocatch = true) {
		parent::__construct($autocatch);
	}
	
	protected function setup(Options $options)
    {
		$methods = get_class_methods($this);
		foreach ($methods as $method) {
			if (strlen($method)>3 && strpos($method, 'run') === 0) {
				$options->registerCommand(substr($method,3), 'Execute method ' . $method);
			}
		}

		parent::setup($options);
    }

    protected function main(Options $options)
    {
		$methods = get_class_methods($this);
		foreach ($methods as $method) {
			if (strlen($method)>3 && strpos($method, 'run') === 0) {
				if ('run'.$options->getCmd() == $method) {
					$this->$method();
					exit;
				}
			}
		}

		parent::main($options);
    }

}


?>
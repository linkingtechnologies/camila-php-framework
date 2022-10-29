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

		$options->registerCommand('create-app', 'Create new App');
        $options->registerArgument('slug', 'App slug', true, 'create-app');
		$options->registerArgument('template', 'App template', true, 'create-app');
		$options->registerArgument('lang', 'App language', true, 'create-app');
    }

    protected function main(Options $options)
    {
        switch ($options->getCmd()) {
            case 'create-app':
				$this->createApp($options);
                break;
			case 'exe-remote-cmd':
				$this->executeRemoteCommand($options);
                break;
            default:
                $this->error('No known command was called, we show the default help instead:');
                echo $options->help();
                exit;
        }
    }
	
	protected function createApp(Options $options) {
		$slug = $options->getArgs()[0];
		$template = $options->getArgs()[1];
		$lang = $options->getArgs()[2];
		if (is_dir('app/'.$slug)) {
			$this->error('Slug already in use!');
		} else {
			$zipFile = bin2hex(random_bytes(10)).'.zip';
			$templateSrc = 'https://github.com/linkingtechnologies/camila-php-framework-app-template-'.$template.'/archive/refs/heads/main.zip';

			/*$handle = fopen($templateSrc, "rb");
			$contents = fread($handle, filesize($templateSrc));
			fclose($handle);*/
			/*if (!file_get_contents($templateSrc)){
				echo ":-(";
			}*/
			if (file_put_contents('app/'.$zipFile, file_get_contents($templateSrc))) {
				$zip = new ZipArchive;
				if ($zip->open('app/'.$zipFile) === TRUE) {
					$zip->extractTo('app/');
					$zip->close();
					rename('app/camila-php-framework-app-template-'.$template.'-main', 'app/'.$slug);
					unlink('app/'.$zipFile);
					$this->success('App ' . $options->getArgs()[0] . ' created!');
					//echo shell_exec('cd app && cd ' . $slug . ' && php cli.php init-app ' . $lang);
				} else {
					$this->error('Error extracting template zip file');
				}
			} else {
					$this->error('Error downloading template zip file ' . $templateSrc);
				}
			
		}
	}
}

$cli = new CamilaMasterCli();
$cli->run();
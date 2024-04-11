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

use Wizaplace\Etl\Pipeline;

class CamilaEtlPipeline extends Pipeline
{
    private $dsn;
	private $id;
	private $name;
	private $startDate;
	private $endDate;

    public function __construct($dsn, $name)
    {
        $this->id = dechex((int)(microtime(true)*1000)).bin2hex(random_bytes(8));
		$this->dsn = $dsn;
		$this->name = $name;	
    }

    public function rewind(): void
    {
		$this->startDate = date("Y-m-d H:i:s", time());
        parent::rewind();
    }

    protected function finalize(): void
    {
        parent::finalize();
		$this->endDate = date("Y-m-d H:i:s", time());
		$this->logFinalize();
    }

	protected function logFinalize() {
		$path = CAMILA_LOG_DIR.'/etl-'.date('Y-m-d').'.csv';
		$fp = fopen($path, 'a');
		fputcsv($fp, array($this->id,$this->name,$this->startDate,$this->endDate));
		fclose($fp);
	}
}

?>
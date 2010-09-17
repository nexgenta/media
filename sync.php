<?php

/*
 * media: The media metadata model
 *
 * Copyright 2010 Mo McRoberts.
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

require_once(dirname(__FILE__) . '/model.php');

class MediaSync extends CommandLine
{
	protected $modelClass = 'Media';
	protected $continuous = false;
	protected $reset = false;

	protected function checkArgs(&$args)
	{
		foreach($args as $arg)
		{
			if($arg == 'reset')
			{
				$this->reset = true;
			}
			else if($arg == 'continuous')
			{
				$this->continuous = true;
			}
			else
			{
				return $this->error(Error::BAD_REQUEST, null, null, 'Usage: media sync [reset | continuous]');
			}
		}
		return true;
	}

	public function main($args)
	{
		if($this->reset)
		{
			$this->model->markAllAsDirty();
		}
		$shown = false;
		$tcount = 0;
		while(true)
		{
			$rs = $this->model->pendingObjectsSet(100);
			$count = 0;
			while(($row = $rs->next()))
			{
				$count++;
/*				echo "Synchronising " . $row['uuid'] . "\n"; */
				$this->model->updateObjectWithUUID($row['uuid']);
			}
			if($count)
			{
				$tcount += $count;
				echo "Synchronised $tcount objects...\n";
				$shown = false;
				sleep(2);
			}
			else if($this->continuous)
			{
				if(!$shown)
				{
					echo "All updates completed, going to sleep.\n";
				}
				$shown = true;
				$tcount = 0;
				sleep(10);
			}
			else
			{
				echo "All updates completed.\n";
				return 0;
			}
		}
	}
}


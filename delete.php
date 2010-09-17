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

class MediaDelete extends CommandLine
{
	protected $modelClass = 'Media';

	protected function checkArgs(&$args)
	{
		if(count($args) != 1)
		{
			return $this->error(Error::NO_OBJECT, null, null, 'Usage: media delete UUID');
		}
		$this->object = $this->model->dataForUUID($args[0]);
		if(!$this->object)
		{
			return $this->error(Error::OBJECT_NOT_FOUND);
		}
		return true;
	}

	public function main($args)
	{
		$this->model->deleteObjectWithUUID($args[0]);
	}
}


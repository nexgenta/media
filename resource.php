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

uses('uuid');

require_once(dirname(__FILE__) . '/asset.php');

class Resource extends Asset
{
	public function verify()
	{
		$model = self::$models[get_class($this)];
		if(isset($this->version))
		{
			if((null !== ($uuid = UUID::isUUID($this->version))) || (null !== ($uuid = $model->uuidForCurie($this->version))))
			{
				$this->referenceObject('version', $uuid);
			}
			else
			{
				return "Referenced version '" . $this->version . "' does not exist yet.";
			}
		}
		return parent::verify();
	}

	public function dimensions($defW = 640, $defH = 360)
	{
		if(isset($this->videoHorizontalSize) && isset($this->videoVerticalSize))
		{
			return array($this->videoHorizontalSize, $this->videoVerticalSize);
		}
	}
}



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

/* Strictly speaking, a 'classification' shouldn't be considered any sort
 * of asset, but it's close enough for our purposes.
 */

class Classification extends Asset
{
	public function verify()
	{
		$model = self::$models[get_class($this)];
		if(!isset($this->parent)) $this->parent = null;
		switch($this->kind)
		{
		case 'genre':
			if(!strncmp($this->parent, '/genres', 7)) $this->parent = substr($this->parent, 7);
			break;
		}
		if($this->parent == '/') $this->parent = null;
		if(null === ($uuid = UUID::isUUID($this->parent)))
		{		
			$uuid = $model->createClassificationPath($this->kind, $this->parent);
		}
		if(null === $uuid && strlen($this->parent))
		{
			return 'Referenced parent path "' . $this->parent . '" is invalid';
		}
		if(null !== $uuid)
		{
			$this->referenceObject('parent', $uuid);
		}
		return true;
	}
}

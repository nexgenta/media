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

class Asset extends Storable
{
	public static function objectForData($data, $model = null, $className = null)
	{
		if(!$model)
		{
			$model = Media::getInstance();
		}
		if(!strlen($className) || $className == 'Asset')
		{
			if(!isset($data['kind']))
			{
				$data['kind'] = 'asset';
			}
			switch($data['kind'])
			{
			case 'asset':
				$className = 'Asset';
				break;
			case 'version':
				require_once(dirname(__FILE__) . '/version.php');
				$className = 'Version';
				break;
			case 'episode':
				require_once(dirname(__FILE__) . '/episode.php');
				$className = 'Episode';
				break;
			default:
				trigger_error('Asset::objectForData(): No suitable class for a "' . $data['kind'] . '" asset is available', E_USER_NOTICE);
				return null;
			}
		}
		return parent::objectForData($data, $model, $className);
	}
	
	protected function loaded($reloaded = false)
	{
		parent::loaded($reloaded);
		/* This is quite ugly, but works around some silly syntax issues
		 * in import formats.
		 */
		$this->transformProperty('genre', 'genres');
		$this->transformProperty('format', 'formats');
		$this->transformProperty('topic', 'topics');
		$this->transformProperty('tag', 'tags');
		$this->transformProperty('person', 'people');
		$this->transformProperty('broadcast', 'broadcasts');
		$this->transformProperty('location', 'locations');
		$this->transformProperty('aliases', 'aliases');
		$this->ensurePropertyIsAnArray('containedIn');
	}
	
	protected function transformProperty($singular, $plural)
	{
		if(isset($this->{$singular}))
		{
			if(is_array($this->{$singular}) && isset($this->{$singular}[0]))
			{
				$this->{$plural} = $this->{$singular};
			}
			else
			{
				$this->{$plural} = array($this->{$singular});
			}
			unset($this->{$singular});
		}
	}

	protected function ensurePropertyIsAnArray($name)
	{
		if(isset($this->{$name}) && (!is_array($this->{$name}) || !isset($this->{$name}[0])))
		{
			$this->{$name} = array($this->{$name});
		}
	}
	
	public function verify()
	{
		return true;
	}
}

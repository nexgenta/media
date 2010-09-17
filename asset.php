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
			case 'show':
				require_once(dirname(__FILE__) . '/show.php');
				$className = 'Show';
				break;
			case 'genre':
			case 'format':
			case 'person':
			case 'place':
			case 'topic':
			case 'license':
				require_once(dirname(__FILE__) . '/classification.php');
				$className = 'Classification';
				break;
			default:
				trigger_error('Asset::objectForData(): No suitable class for a "' . $data['kind'] . '" asset is available', E_USER_NOTICE);
				return null;
			}
		}
		return parent::objectForData($data, $model, $className);
	}

	public function merge()
	{
	}
	
	protected function mergeReplace($parent, $key)
	{
		if(!isset($this->{$key}) && isset($parent->{$key}))
		{
			$this->{$key} = $parent->{$key};
		}
	}

	protected function mergeArrays($parent, $key)
	{
		if(!isset($this->{$key}))
		{
			$this->{$key} = array();
		}
		if(isset($parent->{$key}))
		{
			foreach($parent->{$key} as $value)
			{
				if(!in_array($value, $this->{$key}))
				{
					$this->{$key}[] = $value;
				}
			}
		}
	}

	protected function loaded($reloaded = false)
	{
		parent::loaded($reloaded);
		/* This is quite ugly, but works around some silly syntax issues
		 * in import formats.
		 */
		$this->transformProperty('genre', 'genres', true);
		$this->transformProperty('format', 'formats', true);
		$this->transformProperty('topic', 'topics', true);
		$this->transformProperty('tag', 'tags', true);
		$this->transformProperty('person', 'people', true);
		$this->transformProperty('place', 'places', true);
		$this->transformProperty('license', 'licenses', true);
		$this->transformProperty('broadcast', 'broadcasts', true);
		$this->transformProperty('location', 'locations');
		$this->transformProperty('aliases', 'aliases');
		$this->ensurePropertyIsAnArray('sameAs');
		$this->ensurePropertyIsAnArray('containedIn');
	}	
	
	protected function transformProperty($singular, $plural, $isRef = false)
	{
		if(isset($this->{$singular}))
		{
			if(is_array($this->{$singular}) && (!count($this->{$singular}) || isset($this->{$singular}[0])))
			{
				$this->{$plural} = $this->{$singular};
			}
			else if(count($this->{$singular}))
			{
				$this->{$plural} = array($this->{$singular});
			}
			unset($this->{$singular});
		}
		if(isset($this->{$plural}) && $isRef)
		{
			$this->referenceObject($plural, $this->{$plural});
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
		if(true !== ($r = $this->verifyClassificationProperty('genres', 'genre', '/genres/')))
		{
			return $r;
		}
		if(true !== ($r = $this->verifyClassificationProperty('formats', 'format', '/formats/')))
		{
			return $r;
		}
		if(true !== ($r = $this->verifyClassificationProperty('people', 'person', '/people/')))
		{
			return $r;
		}
		if(true !== ($r = $this->verifyClassificationProperty('places', 'place', '/places/')))
		{
			return $r;
		}
		if(true !== ($r = $this->verifyClassificationProperty('topics', 'topic', '/topics/')))
		{
			return $r;
		}
		if(true !== ($r = $this->verifyClassificationProperty('licenses', 'license', '/licenses/')))
		{
			return $r;
		}
		return true;
	}

	protected function verifyClassificationProperty($name, $kind, $root)
	{
		if(isset($this->{$name}))
		{
			$list = is_array($this->{$name}) ? $this->{$name} : array($this->{$name});
		}
		else
		{
			$list = array();
		}
		$r = $this->verifyClassificationList($list, $kind, $root);
		$this->{$name} = $list;
		return $r;
	}

	protected function verifyClassificationList(&$list, $kind, $root)
	{
		if(!is_array($list))
		{
			if($list === null)
			{
				$list = array();
				return;
			}
			$list = array($list);
		}
		$model = self::$models[get_class($this)];
		foreach($list as $k => $item)
		{
			if(strncmp($item, $root, strlen($root)) && strpos($item, ':') === false)
			{
				while(substr($item, 0, 1) == '/') $item = substr($item, 1);
				$item = $root . $item;
			}
			if(null == ($uuid = UUID::isUUID($item)))
			{
				$rs = $model->query(array('kind' => $kind, 'iri' => $item));
				if(($obj = $rs->next()))
				{
					$uuid = $obj->uuid;
				}
			} 
			if($uuid === null)
			{
				if(strpos($item, ':') !== false)
				{
					$data = array(
						'uuid' => UUID::generate(),
						'kind' => $kind,
						'uri' => $item,
						'title' => $item,
						'sameAs' => array($item),
						);
					$model->setData($data);
					$uuid = $data['uuid'];
				}
				else
				{
					return $kind . ' "' . $item . '" does not exist';
				}
			}
			$list[$k] = $uuid;
		}
		return true;
	}
}

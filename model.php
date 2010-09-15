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

uses('store');

if(!defined('MEDIA_IRI')) define('MEDIA_IRI', null);

require_once(dirname(__FILE__) . '/asset.php');

class Media extends Store
{
	protected $storableClass = 'Asset';

	public static function getInstance($args = null)
	{
		if(!isset($args['class'])) $args['class'] = 'Media';
		if(!isset($args['db'])) $args['db'] = MEDIA_IRI;
		return parent::getInstance($args);
	}
	
	public function uuidForCurie($curie)
	{
		if(($uuid = $this->db->value('SELECT "uuid" FROM {object_iri} WHERE "iri" = ?', '[' . $curie . ']')))
		{
			return $uuid;
		}
		return null;
	}
	
	public /*callback*/ function storedTransaction($db, $args)
	{
		$uuid = $args['uuid'];
		$json = $args['json'];
		$lazy = $args['lazy'];
		$data = $args['data'];
	
		$this->db->exec('DELETE FROM {media_core} WHERE "uuid" = ?', $uuid);
		if(!isset($data['iri']))
		{
			$data['iri'] = array();
		}
		if(!isset($data['tags']))
		{
			$data['tags'] = array();
		}
		if(isset($data['formats']))
		{
			$data['tags'] = array_merge($data['tags'], $data['formats']);
		}
		if(isset($data['genres']))
		{
			$data['tags'] = array_merge($data['tags'], $data['genres']);
		}
		if(isset($data['people']))
		{
			$data['tags'] = array_merge($data['tags'], $data['people']);
		}
		if(isset($data['topics']))
		{
			$data['tags'] = array_merge($data['tags'], $data['topics']);
		}
		if(isset($data['slug']))
		{
			$data['tag'] = $data['slug'];
		}
		if(isset($data['curie']))
		{
			if(is_array($data['curie']))
			{
				foreach($data['curie'] as $curie)
				{
					$data['iri'][] = '[' . $curie . ']';
				}
			}
			else
			{		  
				$data['iri'][] = '[' . $data['curie'] . ']';
			}
		}
		$coreinfo = array();
		if(isset($data['title']))
		{
			$title = preg_replace('![^a-z0-9]!i', '-', strtolower(trim($data['title'])));
			while(substr($title, 0, 1) == '-') $title = substr($title, 1);
			while(substr($title, -1) == '-') $title = substr($title, 0, -1);
			while(strstr($title, '--') !== false) $title = str_replace('--', '-', $title);
			if(strlen($title))
			{
				$coreinfo['title'] = $title;
				if(ctype_alpha($title[0]))
				{
					$coreinfo['title_firstchar'] = $title[0];
				}
				else
				{
					$coreinfo['title_firstchar'] = '*';
				}
			}
		}
		switch($data['kind'])
		{
		case 'version':
			if(isset($data['episode']))
			{
				$coreinfo['parent'] = $data['episode'];
			}
		case 'episode':
			if(isset($data['series']))
			{
				$coreinfo['parent'] = $data['series'];
			}
			else if(isset($data['show']))
			{
				$coreinfo['parent'] = $data['show'];
			}
			break;
		case 'series':
			if(isset($data['show']))
			{
				$coreinfo['parent'] = $data['show'];
			}
			break;
		}
		if(!isset($coreinfo['parent']) && array_key_exists('parent', $data) && isset($data['_refs']) && in_array('parent', $data['_refs']))
		{
			$coreinfo['parent'] = $data['parent'];
		}
		if(!isset($coreinfo['parent']))
		{
			/* Always set create a media_core row, so that parent IS NULL queries work */
			$coreinfo['parent'] = null;
		}
		if(count($coreinfo))
		{
			$coreinfo['uuid'] = $uuid;
			$this->db->insert('media_core', $coreinfo);
		}
		$args['data'] = $data;
		return parent::storedTransaction($db, $args);
	}

	protected function buildQuery(&$qlist, &$tables, &$query)
	{
		if(!isset($tables['media_core'])) $tables['media_core'] = 'media_core';

		foreach($query as $k => $v)
		{
			$value = $v;
			switch($k)
			{
			case 'parent':
				unset($query[$k]);
				if($v === null)
				{
					$qlist['media_core'][] = '"media_core"."parent" IS NULL';
				}
				else
				{
					$qlist['media_core'][] = '"media_core"."parent" = ' . $this->db->quote($v);
				}
				break;
			case 'title_firstchar':
				unset($query[$k]);
				$qlist['media_core'][] = '"media_core"."title_firstchar" = ' . $this->db->quote($v);
				break;
			}
		}
		return parent::buildQuery($qlist, $tables, $query);
	}

	public function createClassificationPath($kind, $path)
	{
		$parent = null;
		$path = explode('/', $path);
		foreach($path as $p)
		{
			if(!strlen($p))
			{
				continue;
			}
			$rs = $this->query(array('kind' => $kind, 'parent' => $parent, 'tag' => $p, 'limit' => 1));
			$data = $rs->next();
			if($data)
			{
				if(!isset($data->title) && isset($data->slug))
				{
					$data->title = ucwords($data->slug);
					$data->store();
				}
				$parent = $data->uuid;
				continue;
			}
			$data = array('uuid' => UUID::generate(), 'kind' => $kind, 'parent' => $parent, 'slug' => $p);
			if($parent !== null)
			{
				$data['_refs'][] = 'parent';
			}
			$this->setData($data);
			$parent = $data['uuid'];
		}
		return $parent;
	}
}
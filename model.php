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
		$rels = array();
		if(isset($data['episode']))
		{
			$rels[] = array('type' => 'episode', 'target' => $data['episode']);
		}
		if(isset($data['series']))
		{
			$rels[] = array('type' => 'series', 'target' => $data['series']);
		}
		if(isset($data['show']))
		{
			$rels[] = array('type' => 'show', 'target' => $data['show']);
		}
		print_r($rels);
		$args['data'] = $data;
		return parent::storedTransaction($db, $args);
	}
}
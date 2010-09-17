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
			$root = '/genres/';
			break;
		case 'format':
			$root = '/formats/';
			break;
		case 'person':
			$root = '/people/';
			break;
		case 'places':
			$root = '/places/';
			break;
		case 'topic':
			$root = '/topics/';
			break;
		case 'license':
			$root = '/licenses/';
			break;
		}
		$parent = $this->parent;
		if(strlen($root))
		{			
			if(!strncmp($parent, $root, strlen($root))) $parent = substr($parent, strlen($root));
		}
		if(!strlen($parent)) $this->parent = $parent = null;
		if(null === ($uuid = UUID::isUUID($parent)))
		{		
			$uuid = $model->createClassificationPath($this->kind, $parent);
		}
		if(null === $uuid && strlen($parent))
		{
			return 'Referenced parent path "' . $this->parent . '" is invalid';
		}
		if(null !== $uuid)
		{
			$this->referenceObject('parent', $uuid);
		}
		if(!isset($this->slug) || !strlen($this->slug))
		{
			$this->slug = $this->uuid;
		}
		$p = $this->parent;
		$uri = array($this->slug);
		$ancestors = array();
		while($p !== null)
		{
			$ancestors[] = $p;
			$data = $model->dataForUUID($p);
			array_unshift($uri, $data['slug']);
			$p = isset($data['parent']) ? $data['parent'] : null;
		}
		$this->relativeURI = implode('/', $uri);
		$this->iri[] = $root . implode('/', $uri);
		$this->ancestors = $ancestors;
		return true;
	}
}

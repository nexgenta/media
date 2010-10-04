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
	public function rdfDocument($doc, $request)
	{
		parent::rdfDocument($doc, $request);
		$model = self::$models[get_class($this)];
		$scheme = $this->offsetGet('scheme');
		$g = $doc->graph($doc->fileURI);
		$g->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = new RDFURI('http://www.w3.org/2008/05/skos#Concept');
		$g->{'http://www.w3.org/2008/05/skos#inScheme'}[] = new RDFURI($request->root . $scheme->__get('instanceRelativeURI'));
		if(isset($this->instanceClass))
		{
			$g->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = new RDFURI($this->instanceClass);
		}
		else if(isset($scheme->instanceClass))
		{
			$g->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = new RDFURI($scheme->instanceClass);
		}
		else
		{
			$g->{'http://www.w3.org/1999/02/22-rdf-syntax-ns#type'}[] = new RDFURI('http://purl.org/ontology/po/Category');
		}
		$g->{'http://www.w3.org/2000/01/rdf-schema#label'}[] = $this->title;
		$g->{'http://www.w3.org/2008/05/skos#prefLabel'}[] = $this->title;
		if(isset($this->description) && strlen($this->description))
		{
			$g->{'http://purl.org/dc/elements/1.1/description'}[] = $this->description;
		}
		if(isset($this->sameAs))
		{
			foreach($this->sameAs as $sameAs)
			{
				$g->{'http://www.w3.org/2002/07/owl#sameAs'}[] = new RDFURI($sameAs);
			}
		}
		$parent = $this->offsetGet('parent');
		if($parent && $parent instanceof Classification)
		{
			$g->{'http://www.w3.org/2008/05/skos#broader'}[] = new RDFURI($request->root . $parent->__get('instanceRelativeURI'));
		}
		$children = $model->query(array('parent' => $this->uuid, 'kind' => $this->kind));
		foreach($children as $child)
		{
			$g->{'http://www.w3.org/2008/05/skos#narrower'}[] = new RDFURI($request->root . $child->__get('instanceRelativeURI'));			
		}
		$rs = $model->query(
			array(
				'kind' => array('clip', 'episode', 'show', 'series'),
				'tags' => $this->uuid,
				'parent' => null,
				)
			);
		$me = $request->root . $this->__get('instanceRelativeURI');
		if(isset($this->property))
		{
			$prop = $this->property;
		}
		else if(isset($scheme->property))
		{
			$prop = $scheme->property;
		}
		else
		{
			$prop = 'http://purl.org/ontology/po/category';
		}
		foreach($rs as $obj)
		{
			if(!isset($obj->instanceClass))
			{
				continue;
			}
			$g = $doc->graph($request->root . $obj->__get('instanceRelativeURI'), $obj->instanceClass);
			$g->{$prop}[] = $doc->primaryTopic;
		}
	}
	
	public function verify()
	{
		if(true !== ($r = parent::verify()))
		{
			return $r;
		}
		$model = self::$models[get_class($this)];
		if(!isset($this->parent)) $this->parent = null;
		if(!isset($this->iri)) $this->iri = null;
		$cs = $model->locateObject('[scheme:' . $this->kind . ']', null, 'scheme');
		$this->referenceObject('scheme', $cs);
		if(!isset($this->fragment))
		{
			$this->fragment = $cs->singular;
		}
		$root = '/' . $cs->relativeURI;
		$parent = $this->parent;
		if(substr($parent, 1, 0) == '/') $parent = substr($parent, 1);
		if(!strncmp($parent, $root, strlen($root))) $parent = substr($parent, strlen($root));
		if(strlen($parent))
		{
			if(null === ($uuid = UUID::isUUID($parent)))
			{
				$uuid = $model->createClassificationPath($cs, $parent);
			}
			if(null === $uuid && strlen($parent))
			{
				return 'Referenced parent path "' . $this->parent . '" is invalid';
			}
		}
		else
		{
			$this->parent = $parent = null;
			$uuid = $cs->uuid;
		}
		$this->referenceObject('parent', $uuid);
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

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

uses('date');

require_once(dirname(__FILE__) . '/model.php');

abstract class MediaBrowseClasses extends Page
{
	protected $modelClass = 'Media';
	protected $templateName = 'classes.phtml';
	protected $supportedTypes = array('text/html', 'application/json', 'application/rdf+xml', 'text/xml');
	protected $kind = 'thing';
	protected $title = 'Things';
	protected $kindTitle;
	protected $children;

	protected function getObject()
	{
		$parent = ($this->object ? $this->object->uuid : null);
		if(null !== ($tag = $this->request->consume()))
		{
			$obj = null;
			if(null !== ($uuid = UUID::isUUID($tag)))
			{
				$rs = $this->model->query(array('uuid' => $uuid, 'parent' => $parent, 'kind' => $this->kind));
				$obj = $rs->next();
			}
			else
			{
				$rs = $this->model->query(array('tag' => $tag, 'parent' => $parent, 'kind' => $this->kind));
				$obj = $rs->next();
			}
			if(!$obj)
			{				
				return $this->error(Error::OBJECT_NOT_FOUND);
			}
			$me = get_class($this);
			$inst = new $me();
			$inst->object = $obj;
			$inst->process($this->request);
			return false;
		}
		$this->kindTitle = $this->title;
		if($this->object)
		{		   
			$this->title = $this->object->title;
		}
		$this->children = $this->model->query(array('parent' => $parent, 'kind' => $this->kind));
		if($this->object)
		{
			$this->objects = $this->model->query(array('parent' => null, 'kind' => array('episode', 'show'), 'tags' => $this->object->uuid));
		}
		return true;
	}

	protected function assignTemplate()
	{
		parent::assignTemplate();
		$this->vars['root'] = $this->request->base . $this->base;
		$this->vars['kindTitle'] = $this->kindTitle;
		$this->vars['children'] = $this->children;
		$uri = $this->request->pageUri;
		if(strlen($uri) > 1 && substr($uri, -1) == '/') $uri = substr($uri, 0, -1);
		$this->links[] = array('rel' => 'alternate', 'href' => $uri . '.rdf', 'type' => 'application/rdf+xml');
	}

	protected function perform_GET_RDF()
	{
		$this->request->header('Content-type', 'application/rdf+xml');
		$this->request->flush();
		$uri = $this->request->pageUri;
		if(strlen($uri) > 1 && substr($uri, -1) == '/') $uri = substr($uri, 0, -1);		
		writeLn('<?xml version="1.0" encoding="utf-8" ?>');
		writeLn('<rdf:RDF ' .
				'xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" ' .
				'xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#" ' .
				'xmlns:owl="http://www.w3.org/2002/07/owl#" ' .
				'xmlns:foaf="http://xmlns.com/foaf/0.1/" ' .
				'xmlns:po="http://purl.org/ontology/po/" ' .
				'xmlns:mo="http://purl.org/ontology/mo/" ' .
				'xmlns:skos="http://www.w3.org/2008/05/skos#" ' .
				'xmlns:time="http://www.w3.org/2006/time#" ' .
				'xmlns:dc="http://purl.org/dc/elements/1.1/" ' .
				'xmlns:dcterms="http://purl.org/dc/terms/" ' .
				'xmlns:wgs84_pos="http://www.w3.org/2003/01/geo/wgs84_pos#" ' .
				'xmlns:timeline="http://purl.org/NET/c4dm/timeline.owl#" ' .
				'xmlns:event="http://purl.org/NET/c4dm/event.owl#">');
		
		writeLn();
		writeLn('<rdf:Description rdf:about="' . _e($uri) . '.rdf">');
		writeLn('<rdf:type rdf:resource="http://www.w3.org/2008/05/skos#Concept" />');
		writeLn('<skos:inScheme rdf:resource="' . _e($this->request->root . $this->base . '#scheme') . '" />');
		if($this->kind == 'genre')
		{
			writeLn('<rdf:type rdf:resource="http://purl.org/ontology/po/Genre/" />');
		}
		else if($this->kind == 'format')
		{
			writeLn('<rdf:type rdf:resource="http://purl.org/ontology/po/Format/" />');
		}
		else if($this->kind == 'place')
		{
			writeLn('<rdf:type rdf:resource="http://purl.org/ontology/po/Place/" />');
		}
		writeLn('<rdfs:label>' . _e($this->title) . '</rdfs:label>');
		writeLn('<skos:prefLabel>' . _e($this->title) . '</skos:prefLabel>');
		$parent = '/' . implode('/', array_slice($this->request->page, 0, -1));
		if(strlen($parent) > strlen($this->request->root . $this->base))
		{
			writeLn('<skos:broader rdf:resource="' . _e($parent . '#' . $this->kind) . '" />');
		}
		foreach($this->children as $child)
		{
			writeLn('<skos:narrower rdf:resource="' . _e($uri . '/' . $child->slug . '#' . $this->kind) . '" />');
		}
		if(isset($this->object->sameAs))
		{
			$sameAs = is_array($this->object->sameAs) ? $this->object->sameAs : array($this->object->sameAs);
			foreach($sameAs as $xuri)
			{
				writeLn('<owl:sameAs rdf:resource="' . _e($xuri) . '" />');
			}
		}
		writeLn('</rdf:Description>');
		writeLn();
		foreach($this->objects as $obj)
		{
			if($obj instanceOf Show)
			{
				writeLn('<po:Brand rdf:about="' . _e($this->request->root . $obj->relativeURI . '#show') . '">');
				writeLn('<po:category rdf:resource="' . _e($uri . '#' . $this->kind) . '" />');
				writeLn('</po:Brand>');
			}
		}
		writeLn();
		writeLn('</rdf:RDF>');
	}

	protected function perform_GET_XML()
	{
		$this->request->header('Content-type', 'text/xml');
		$this->request->flush();
		writeLn('<?xml version="1.0" encoding="UTF-8" ?>');
		writeLn('<ClassificationScheme uri="' . _e($this->tvaNamespace) . '" ' . 
				'xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" ' .
				'xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#" ' .
                'xmlns:owl="http://www.w3.org/2002/07/owl#">');
		foreach($this->children as $child)
		{
			$this->writeTVATerm($child);
		}
		writeLn('</ClassificationScheme>');
	}

	protected function writeTVATerm($node, $depth = "\t")
	{
		if(!isset($node->sameAs)) return;
		$termId = null;
		foreach($node->sameAs as $uri)
		{
			if(!strncmp($uri, $this->tvaNamespace, strlen($this->tvaNamespace)))
			{
				$termId = substr($uri, strlen($this->tvaNamespace));
				break;
			}
		}
		if($termId === null) return;
		writeLn($depth . '<Term termID="' . _e($termId) . '">');
		$ndepth = "\t" . $depth;
		writeLn($ndepth . '<Name>' . _e($node->title) . '</Name>');
		writeLn($ndepth . '<Definition>' . _e($node->title) . '</Definition>');
		foreach($node->sameAs as $same)
		{
			if(!strcmp($same, $uri)) continue;
			writeLn($ndepth . '<owl:sameAs rdf:resource="' . _e($same) . '" />');
		}
		$children = $this->model->query(array('kind' => $this->kind, 'parent' => $node->uuid));
		while(($child = $children->next()))
		{
			$this->writeTVATerm($child, $ndepth);
		}
		writeLn($depth . '</Term>');
	}
}

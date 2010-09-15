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

class MediaBrowseEpisode extends Page
{	
	protected $templateName = 'episode.phtml';
	protected $supportedTypes = array('text/html', 'application/json', 'application/rdf+xml', 'application/atom+xml');

	protected function getObject()
	{
		$this->object->merge();
		$this->title = $this->object->title;
		return true;
	}
	
	protected function assignTemplate()
	{
		parent::assignTemplate();
		$uri = $this->request->pageUri;
		if(strlen($uri) > 1 && substr($uri, -1) == '/') $uri = substr($uri, 0, -1);		
		$this->links[] = array('rel' => 'alternate', 'type' => 'application/rdf+xml', 'href' => $uri . '.rdf');
		$this->links[] = array('rel' => 'alternate', 'type' => 'application/json', 'href' => $uri . '.json');
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
		writeLn('<rdfs:label>Description of the episode ' . _e($this->title) . '</rdfs:label>');
		writeLn('<dcterms:created rdf:datatype="http://www.w3.org/2001/XMLSchema#dateTime">' . strftime('%Y-%m-%dT%H:%M:%SZ', parse_datetime($this->object->created)) . '</dcterms:created>');
		writeLn('<dcterms:modified rdf:datatype="http://www.w3.org/2001/XMLSchema#dateTime">' . strftime('%Y-%m-%dT%H:%M:%SZ', parse_datetime($this->object->modified)) . '</dcterms:modified>');
		writeLn('<foaf:primaryTopic rdf:resource="' . _e($uri . '#episode') . '" />');
		writeLn('</rdf:Description>');
		writeLn();
		
		writeLn('<po:Episode rdf:about="' . _e($uri . '#episode') . '">');
		writeLn();

		if(isset($this->object->curie))
		{
			writeLn('<po:pid>' . _e($this->object->curie) . '</po:pid>');
		}
		writeLn('<dc:title>' . _e($this->object->title) . '</dc:title>');
		if(isset($this->object->shortDescription))
		{
			writeLn('<po:short_synopsis>' . _e($this->object->shortDescription) . '</po:short_synopsis>');
		}
		if(isset($this->object->mediumDescription))
		{
			writeLn('<po:medium_synopsis>' . _e($this->object->mediumDescription) . '</po:medium_synopsis>');
		}
		if(isset($this->object->description))
		{
			writeLn('<po:long_synopsis>' . _e($this->object->description) . '</po:long_synopsis>');
		}
		if(isset($this->object->image))
		{
			writeLn('<foaf:depiction rdf:resource="' . _e($this->object->image) . '" />');
		}
		writeLn();

		if(isset($this->object->genres))
		{
			foreach($this->object->genres as $genre)
			{
				$this->writeRDFResource('po:genre', $genre, 'genre');
			}
		}
		if(isset($this->object->formats))
		{
			foreach($this->object->formats as $format)
			{
				$this->writeRDFResource('po:format', $format, 'format');
			}
		}
		if(isset($this->object->topics))
		{
			foreach($this->object->topics as $topic)
			{
				$this->writeRDFResource('po:subject', $topic, 'topic');
			}
		}
		if(isset($this->object->people))
		{
			foreach($this->object->people as $person)
			{
				$this->writeRDFResource('po:person', $person, 'person');
			}
		}		
		writeLn();
		writeLn('</po:Episode>');

		writeLn();
		writeLn('</rdf:RDF>');
	}

	protected function writeRDFResource($tag, $uri, $fragment)
	{
		if(UUID::isUUID($uri))
		{
			/* Fetch target */
		}
		else if(substr($uri, 0, 1) == '/')
		{
			writeLn('<' . $tag . ' rdf:resource="' . _e($uri . '#' . $fragment) . '" />');
		}
		else
		{
			writeLn('<' . $tag . ' rdf:resource="' . _e($uri) . '" />');
		}
	}
}
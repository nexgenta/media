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

class Version extends Asset
{
	protected $resources;
	protected $relativeURI;
	public $instanceClass = 'http://purl.org/ontology/po/Version';
	public $property = 'http://purl.org/ontology/po/version';
	
	public function verify()
	{
		$model = self::$models[get_class($this)];
		if(isset($this->episode))
		{
			if((null !== ($uuid = UUID::isUUID($this->episode))) || (null !== ($uuid = $model->uuidForCurie($this->episode))))
			{
				$this->referenceObject('episode', $uuid);
			}
			else
			{
				return "Referenced episode '" . $this->episode . "' does not exist yet.";
			}
		}
		if(true !== ($r = $this->verifyCredits()))
		{
			return $r;
		}
		return parent::verify();
	}

	protected function loaded($reloaded = false)
	{
		parent::loaded($reloaded);
		$this->associateParents('episode');
	}

	protected function rdfDocument($doc, $request)
	{
		parent::rdfDocument($doc, $request);
		$g = $doc->graph($doc->fileURI);
		if(!isset($this->title))
		{
			if(isset($this->pid))
			{
				$this->title = $this->pid;
			}
			else if(isset($this->slug))
			{
				$this->title = $this->slug;
			}
			else if(isset($this->curie))
			{
				$this->title = $this->curie;
			}
			else
			{
				$this->title = $this->uuid;
			}
		}
		$g->{'http://www.w3.org/2000/01/rdf-schema#label'}[] = 'Description of the ' . $this->kind . ' ' . $this->title;
		$g->{'http://xmlns.com/foaf/0.1/primaryTopic'}[] = new RDFURI($doc->primaryTopic);
	}

	protected function rdfResource($doc, $request)
	{
		parent::rdfResource($doc, $request);
		$model = self::$models[get_class($this)];
		$g = $doc->graph($doc->primaryTopic, $this->instanceClass);
		if(isset($this->sameAs))
		{
			foreach($this->sameAs as $same)
			{
				$g->{'http://www.w3.org/2002/07/owl#sameAs'}[] = new RDFURI($same);
			}
		}
		$po = 'http://purl.org/ontology/po/';
		if(isset($this->pid))
		{
			$g->{$po.'pid'}[] = $this->pid;
		}
		if(isset($this->credits))
		{
			foreach($this->credits as $cred)
			{
				if(isset($cred['character']) || isset($cred['characterRef']))
				{
					$uri = null;
					if(isset($cred['characterRef']))
					{
						$obj = $model->objectForUUID($cred['characterRef'][0]);
						if(!isset($cred['character']))
						{
							$cred['character'] = $obj->title;
						}
						$uri = $request->root . $obj->__get('instanceRelativeURI');
					}					
					$cc = new RDFGraph($uri, $po.'Character');
					$cc->{'http://xmlns.com/foaf/0.1/name'}[] = $cred['character'];
					$cg = new RDFGraph(null, $po.'Credit');
					$cg->{$po.'role'}[] = $cc;
					$obj = $model->objectForUUID($cred['person'][0]);					
					$pp = new RDFGraph($request->root . $obj->__get('instanceRelativeURI'), $po.'Alias');
					$pp->{'http://www.w3.org/2000/01/rdf-schema#label'}[] = $obj->title;
					$cg->{$po.'participant'}[] = $pp;
					$g->{$po.'credit'}[] = $cg;
				}
			}
		}
	}	
	
	public function __get($name)
	{
		if($name == 'resources')
		{
			return $this->getResources();
		}
		if($name == 'hasVideo')
		{
			$this->getResources();
			foreach($this->resources as $res)
			{
				if(!strncmp($res->dataContainerFormat, 'video/', 6))
				{
					return true;
				}
			}
			return false;
		}
		if($name == 'hasAudio')
		{
			$this->getResources();
			foreach($this->resources as $res)
			{
				if(!strncmp($res->dataContainerFormat, 'audio/', 6))
				{
					return true;
				}
			}
			return false;
		}
		if($name == 'hasTTML')
		{
			$this->getResources();
			foreach($this->resources as $res)
			{
				if(!strncmp($res->dataContainerFormat, 'application/ttml+xml'))
				{
					return true;
				}
			}
			return false;
		}
		if($name == 'dimensions')
		{
			return $this->dimensions();
		}
		return parent::__get($name);
	}
	
	protected function getResources()
	{
		if(!isset($this->resources))
		{
			$model = self::$models[get_class($this)];
			$this->resources = array();
			$rs = $model->query(array('kind' => 'resource', 'parent' => $this->uuid));
			foreach($rs as $loc)
			{
				$this->resources[$loc->uuid] = $loc;
			}
		}
		return $this->resources;
	}

	public function dimensions($defW = 640, $defH = 360)
	{
		$this->getResources();
		foreach($this->resources as $res)
		{
			if(empty($res->available))
			{
				continue;
			}
			if(isset($res->videoHorizontalSize) && isset($res->videoVerticalSize))
			{
				return array($res->videoHorizontalSize, $res->videoVerticalSize);
			}
		}
		return array($defW, $defH);
	}
}

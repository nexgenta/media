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

require_once(dirname(__FILE__) . '/asset.php');

class Episode extends Asset
{
	protected $relativeURI;
	protected $versions = null;
	public $instanceClass = 'http://purl.org/ontology/po/Episode';
	public $property = 'http://purl.org/ontology/po/episode';

	public function verify()
	{
		$model = self::$models[get_class($this)];
		if(isset($this->show))
		{
			if((null !== ($uuid = UUID::isUUID($this->show))) || (null !== ($uuid = $model->uuidForCurie($this->show))))
			{
				$this->referenceObject('show', $uuid);
			}
			else
			{
				return "Referenced show '" . $this->show . "' does not exist yet.";
			}
		}
		if(isset($this->series))
		{
			if((null !== ($uuid = UUID::isUUID($this->series))) || (null !== ($uuid = $model->uuidForCurie($this->series))))
			{
				$this->referenceObject('series', $uuid);
			}
			else
			{
				return "Referenced series '" . $this->series . "' does not exist yet.";
			}
		}
		$this->verifyCredits();
		return parent::verify();
	}

	protected function loaded($reloaded = false)
	{
		parent::loaded($reloaded);
		$this->associateParents('series', 'show');
	}

	public function merge()
	{
		if(($obj = $this->offsetGet('parent')))
		{
			$obj->merge();
			$this->mergeReplace($obj, 'publisher');
			$this->mergeArrays($obj, 'formats');
			$this->mergeArrays($obj, 'topics');
			$this->mergeArrays($obj, 'genres');
			$this->mergeArrays($obj, 'tags');
			$this->mergeArrays($obj, 'people');
			$this->mergeArrays($obj, 'places');
			$this->mergeArrays($obj, 'licenses');
			$this->mergeReplace($obj, 'template');
		}
	}
	  
	public function __get($name)
	{
		if($name == 'versions')
		{
			return $this->getVersions();
		}
		if($name == 'defaultVersion')
		{
			$this->getVersions();
			foreach($this->versions as $ver)
			{
				return $ver;
			}
			return null;
		}
		return parent::__get($name);
	}
	
	protected function getVersions()
	{
		if(!isset($this->versions))
		{
			$model = self::$models[get_class($this)];
			$this->versions = array();
			$rs = $model->query(array('kind' => 'version', 'parent' => $this->uuid));
			foreach($rs as $ver)
			{
				$this->versions[$ver->uuid] = $ver;
			}
		}
		return $this->versions;
	}

	protected function rdfDocument($doc, $request)
	{
		parent::rdfDocument($doc, $request);
		$g = $doc->graph($doc->fileURI);
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
		if(isset($this->seeAlso))
		{
			foreach($this->seeAlso as $subj)
			{
				$g->{RDF::foaf.'seeAlso'}[] = new RDFURI($same);
			}
		}
		if(isset($this->subjects))
		{
			foreach($this->subjects as $subj)
			{
				$g->{RDF::dcterms.'subject'}[] = new RDFURI($subj);
			}
		}
		$po = 'http://purl.org/ontology/po/';
		if(isset($this->pid))
		{
			$g->{$po.'pid'}[] = $this->pid;
		}
		$g->{'http://purl.org/dc/elements/1.1/title'}[] = $this->title;
		if(isset($this->shortDescription))
		{
			$g->{$po.'short_synopsis'}[] = $this->shortDescription;
		}
		if(isset($this->mediumDescription))
		{
			$g->{$po.'medium_synopsis'}[] = $this->shortDescription;
		}
		if(isset($this->description))
		{
			$g->{$po.'long_synopsis'}[] = $this->description;
		}
		if(isset($this->image))
		{
			$g->{'http://xmlns.com/foaf/0.1/depiction'}[] = new RDFURI($this->image);
		}
		if(isset($this->uri))
		{
			$g->{$po.'microsite'}[] = new RDFURI($this->uri);
		}
		$this->getVersions();
		foreach($this->versions as $ver)
		{
			$g->{$po.'version'}[] = new RDFURI($request->root . $ver->__get('instanceRelativeURI'));
		}
		$schemes = $model->schemes();
		foreach($schemes as $scheme)
		{
			if(!isset($scheme->property))
			{
				continue;
			}
			if(isset($this->{$scheme->plural}))
			{
				if(!isset($g->{$scheme->property}))
				{
					$g->{$scheme->property} = array();
				}
				foreach($this->{$scheme->plural} as $ref)
				{					
					$this->rdfReferenceInto($g->{$scheme->property}, $ref, $request, $scheme->singular);
				}
			}
		}
	}
}


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

class Show extends Asset
{
	protected $relativeURI;

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
		return parent::verify();
	}

	protected function loaded($reloaded = false)
	{
		parent::loaded($reloaded);
		$this->associateParents('show');
		$model = self::$models[get_class($this)];
		if(!isset($this->instanceClass) || !isset($this->property))
		{
			$rs = $model->query(array('kind' => 'series', 'parent' => $this->uuid));
			if($rs->next())
			{
				$ic = 'http://purl.org/ontology/po/Brand';
				$pr = 'http://purl.org/ontology/po/brand';
			}
			else
			{
				$ic = 'http://purl.org/ontology/po/Series';
				$pr = 'http://purl.org/ontology/po/series';
			}
			if(!isset($this->instanceClass))
			{
				$this->instanceClass = $ic;
			}
			if(!isset($this->property))
			{
				$this->property = $pr;
			}
		}
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
		$schemes = $model->schemes();
		foreach($schemes as $scheme)
		{
			if(!isset($scheme->property))
			{
				continue;
			}
			if(isset($this->{$scheme->plural}))
			{
				foreach($this->{$scheme->plural} as $ref)
				{
					$g->{$scheme->property}[] = $this->rdfReference($ref, $request, $scheme->singular);
				}
			}
		}
		$rs = $model->query(array('kind' => array('series', 'episode', 'clip'), 'parent' => $this->uuid));
		foreach($rs as $child)
		{
			if(!isset($child->property))
			{
				continue;
			}
			$g->{$child->property}[] = new RDFURI($request->root . $child->__get('instanceRelativeURI'));
		}
	}

	protected function rdfLinks($doc, $request)
	{
		parent::rdfLinks($doc, $request);
		if(($obj = $this->offsetGet('show')))
		{
			$g = $doc->graph($request->root . $obj->__get('instanceRelativeURI'), $obj->instanceClass);
			$g->{$this->property}[] = new RDFURI($request->root . $this->__get('instanceRelativeURI'));
		}
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
			$this->mergeArrays($obj, 'licenses');
			$this->mergeReplace($obj, 'template');
		}
	}
}


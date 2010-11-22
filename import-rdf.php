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

uses('rdf');

require_once(MODULES_ROOT . 'po/ontology.php');
require_once(dirname(__FILE__) . '/import.php');

class MediaImportRDF extends MediaImportBase
{
	public function importFile($pathname)
	{
		$doc = RDF::documentFromURL($pathname);
		$info = null;
		if(is_object($doc))
		{
			$topic = ProgrammesOntology::instanceFromDocument($doc);
			if(is_object($topic))
			{
				if($topic instanceof POBrand)
				{
					$info = $this->importBrand($topic);
					$info['_recurse'] = array();
					foreach($topic->episodes as $ep)
					{
						$info['_recurse'][] = strval($ep);
					}
					foreach($topic->series as $ep)
					{
						$info['_recurse'][] = strval($ep);
					}
					foreach($topic->clips as $ep)
					{
						$info['_recurse'][] = strval($ep);
					}
				}
				else if($topic instanceof POSeries)
				{
					$info = $this->importSeries($topic);
					$info['_recurse'] = array();
					foreach($topic->episodes as $ep)
					{
						if($ep->isA(ProgrammesOntology::po . 'episode'))
						{
							$ep = $ep->first(ProgrammesOntology::po . 'Episode');
						}
						$url = strval($ep);
						if(!strlen($url))
						{
							print_r($ep);
							die();
						}
						$info['_recurse'][] = $url;
					}
					foreach($topic->clips as $ep)
					{
						$info['_recurse'][] = strval($ep);
					}
				}
				else if($topic instanceof POEpisode)
				{
					$info = $this->importEpisode($topic);
				}
				else
				{
					echo "Unsupported class " . get_class($topic) . "\n";
				}
			}
			else
			{
				echo "No primary topic found.\n";
			}
		}
		else
		{
			echo "No document found.\n";
		}
		return $info;
	}	

	protected function clean($thing)
	{
		foreach($thing as $k => $v)
		{
			if($v === null)
			{
				unset($thing[$k]);
			}
		}
		return $thing;
	}

	protected function importBrand($topic)
	{
		$info = array(
			'kind' => 'show',
			'title' => $topic->title,
			'pid' => $topic->pid,
			'shortDescription' => $topic->shortSynopsis,
			'mediumDescription' => $topic->mediumSynopsis,
			'description' => $topic->longSynopsis,
			'image' => strval($topic->depiction),
			'sameAs' => strval($topic->first(RDF::rdf . 'about')),
			'uri' => strval($topic->first(ProgrammesOntology::po . 'microsite')),
			);
		if(!strlen($info['image'])) unset($info['image']);
		if(!strlen($info['sameAs'])) unset($info['sameAs']);
		if(!strlen($info['uri'])) unset($info['uri']);
		if(!isset($info['uri']) && isset($info['sameAs']))
		{
			$info['uri'] = $info['sameAs'];
		}
		if(isset($info['pid']))
		{
			$info['curie'] = 'bbc:' . $info['pid'];
			$info['slug'] = $info['pid'];
		}
		return $this->clean($info);
	}

	protected function importSeries($topic)
	{
		$info = array(
			'kind' => 'series',
			'title' => $topic->title,
			'pid' => $topic->pid,
			'shortDescription' => $topic->shortSynopsis,
			'mediumDescription' => $topic->mediumSynopsis,
			'description' => $topic->longSynopsis,
			'image' => strval($topic->depiction),
			'sameAs' => strval($topic->first(RDF::rdf . 'about')),
			'uri' => strval($topic->first(ProgrammesOntology::po . 'microsite')),
			'show' => strval($topic->brand),
			);
		if(!strlen($info['image'])) unset($info['image']);
		if(!strlen($info['sameAs'])) unset($info['sameAs']);
		if(!strlen($info['uri'])) unset($info['uri']);
		if(!strlen($info['show'])) unset($info['show']);
		if(!isset($info['uri']) && isset($info['sameAs']))
		{
			$info['uri'] = $info['sameAs'];
		}
		if(isset($info['pid']))
		{
			$info['curie'] = 'bbc:' . $info['pid'];
			$info['slug'] = $info['pid'];
		}
		return $this->clean($info);
	}

	protected function importEpisode($topic)
	{
		$info = array(
			'kind' => 'episode',
			'title' => $topic->title,
			'pid' => $topic->pid,
			'shortDescription' => $topic->shortSynopsis,
			'mediumDescription' => $topic->mediumSynopsis,
			'description' => $topic->longSynopsis,
			'image' => strval($topic->depiction),
			'show' => strval($topic->brand),
			'series' => strval($topic->series),
			);
		if(!strlen($info['image'])) unset($info['image']);
		if(!strlen($info['show'])) unset($info['show']);
		if(!strlen($info['series'])) unset($info['series']);
		if(isset($info['pid']))
		{
			$info['curie'] = 'bbc:' . $info['pid'];
			$info['slug'] = $info['pid'];
		}
		return $this->clean($info);
	}
}


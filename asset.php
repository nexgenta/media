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

uses('date', 'rdf');

require_once(dirname(__FILE__) . '/model.php');

class Asset extends Storable
{
	protected $relativeURI = null;
	protected $storableClass = 'Asset';

	public static function objectForData($data, $model = null, $className = null)
	{
		if(!$model)
		{
			$model = Media::getInstance();
		}
		if(!strlen($className) || $className == 'Asset')
		{
			if(!isset($data['kind']))
			{
				$data['kind'] = 'asset';
			}
			switch($data['kind'])
			{
			case 'asset':
				$className = 'Asset';
				break;
			case 'scheme':
				require_once(dirname(__FILE__) . '/scheme.php');
				$className = 'Scheme';
				break;
			case 'resource':
				require_once(dirname(__FILE__) . '/resource.php');
				$className = 'Resource';
				break;
			case 'version':
				require_once(dirname(__FILE__) . '/version.php');
				$className = 'Version';
				break;
			case 'episode':
			case 'clip':
				require_once(dirname(__FILE__) . '/episode.php');
				$className = 'Episode';
				break;
			case 'show':
			case 'series':
				require_once(dirname(__FILE__) . '/show.php');
				$className = 'Show';
				break;
			default:
				if(($cs = $model->locateObject('[scheme:' . $data['kind'] . ']', null, 'scheme')))
				{
					require_once(dirname(__FILE__) . '/classification.php');
					$className = 'Classification';
					break;
				}
				trigger_error('Asset::objectForData(): No suitable class for a "' . $data['kind'] . '" asset is available', E_USER_NOTICE);
				return null;
			}
		}
		return parent::objectForData($data, $model, $className);
	}

	public function __get($name)
	{
		if($name == 'relativeURI')
		{
			if(null === $this->relativeURI)
			{
				$this->parentRelativeURI();
				$this->relativeURI();
			}
			return $this->relativeURI;
		}
		if($name == 'instanceRelativeURI')
		{
			return $this->instanceRelativeURI();
		}
	}

	protected function parentRelativeURI()
	{
		if(isset($this->parent) && ($obj = $this->offsetGet('parent')) && is_object($obj))
		{
			/* Use __get() because depending on the class of 'obj',
			 * PHP may not invoke it magically...
			 */
			$this->relativeURI = $obj->__get('relativeURI');
		}		
	}

	protected function relativeURI()
	{
		$slug = null;
		if(isset($this->slug))
		{
			$slug = $this->slug;
		}
		else if(isset($this->pid))
		{
			$slug = $this->pid;
		}
		else if(isset($this->uuid))
		{
			$slug = $this->uuid;
		}
		if(strlen($this->relativeURI))
		{
			$this->relativeURI .= '/' . $slug;
		}
		else
		{
			$this->relativeURI = $slug;
		}
	}

	protected function instanceRelativeURI()
	{
		return $this->__get('relativeURI') . '#' . (isset($this->fragment) ? $this->fragment : $this->kind);
	}
	
	public function merge()
	{
	}

	public function verify()
	{
		$model = self::$models[get_class($this)];
		$rs = $model->query(array('kind' => 'scheme'));
		foreach($rs as $scheme)
		{
			$this->transformProperty($scheme->singular, $scheme->plural, true);
			if(true !== ($r = $this->verifyClassificationProperty($scheme->plural, $scheme->singular, '/' . $scheme->slug . '/')))
			{
				return $r;
			}
		}
		$this->transformProperty('tag', 'tags');
		$this->transformProperty('alias', 'aliases');
		$this->transformProperty('link', 'links');
		$this->transformProperty('credit', 'credits');
		$this->transformProperty('subject', 'subjects');
		$this->ensurePropertyIsAnArray('sameAs');
		$this->ensurePropertyIsAnArray('seeAlso');
		$this->ensurePropertyIsAnArray('containedIn');
		return true;
	}

	protected function associateParents($keys)
	{
		$keys = func_get_args();
		if(!isset($this->parent))
		{
			foreach($keys as $k)
			{
				if(($s = $this->offsetGet($k)) && is_object($s))
				{
					$this->referenceObject('parent', $s->uuid);
					return;
				}
			}
		}
	}		

	protected function verifyCredits()
	{
		$this->transformProperty('credit', 'credits');
		if(isset($this->credits))
		{
			foreach($this->credits as $k => $credit)
			{
				if(isset($credit['person']))
				{
					$r = $this->verifyClassificationList($this->credits[$k]['person'], 'person', '/people/');
					if($r !== true)
					{
						return $r;
					}
				}
				if(isset($credit['characterRef']))
				{
					$r = $this->verifyClassificationList($this->credits[$k]['characterRef'], 'character', '/characters/');
					if($r !== true)
					{
						return $r;
					}
				}
			}
		}
		return true;
	}
	
	protected function mergeReplace($parent, $key)
	{
		if(!isset($this->{$key}) && isset($parent->{$key}))
		{
			$this->{$key} = $parent->{$key};
		}
	}

	protected function mergeArrays($parent, $key)
	{
		if(!isset($this->{$key}))
		{
			$this->{$key} = array();
		}
		if(isset($parent->{$key}))
		{
			if(isset($parent->_refs) && in_array($key, $parent->_refs))
			{
				if(!in_array($key, $this->_refs))
				{
					$this->_refs[] = $key;
				}
			}				
			foreach($parent->{$key} as $value)
			{
				if(!in_array($value, $this->{$key}))
				{
					$this->{$key}[] = $value;
				}
			}
		}
	}
	
	protected function transformProperty($singular, $plural, $isRef = false)
	{
		if(isset($this->{$singular}))
		{
			if(is_array($this->{$singular}) && (!count($this->{$singular}) || isset($this->{$singular}[0])))
			{
				$this->{$plural} = $this->{$singular};
			}
			else if(count($this->{$singular}))
			{
				$this->{$plural} = array($this->{$singular});
			}
			unset($this->{$singular});
		}
		if(isset($this->{$plural}) && $isRef)
		{
			$this->referenceObject($plural, $this->{$plural});
		}
	}

	protected function ensurePropertyIsAnArray($name)
	{
		if(isset($this->{$name}) && (!is_array($this->{$name}) || !isset($this->{$name}[0])))
		{
			$this->{$name} = array($this->{$name});
		}
	}

	protected function verifyClassificationProperty($name, $kind, $root)
	{
		if(isset($this->{$name}))
		{
			$list = is_array($this->{$name}) ? $this->{$name} : array($this->{$name});
		}
		else
		{
			$list = array();
		}
		$r = $this->verifyClassificationList($list, $kind, $root);
		$this->{$name} = $list;
		return $r;
	}

	protected function verifyClassificationList(&$list, $kind, $root)
	{
		if(!is_array($list))
		{
			if($list === null)
			{
				$list = array();
				return;
			}
			$list = array($list);
		}
		$model = self::$models[get_class($this)];
		foreach($list as $k => $item)
		{
			if(null == ($uuid = UUID::isUUID($item)))
			{
				echo "[kind=$kind, iri=$item]\n";
				$rs = $model->query(array('kind' => $kind, 'iri' => $item));
				if(($obj = $rs->next()))
				{
					$uuid = $obj->uuid;
				}
			} 
			if($uuid === null)
			{
				if(strpos($item, ':') !== false)
				{
					$data = array(
						'uuid' => UUID::generate(),
						'kind' => $kind,
						'uri' => $item,
						'title' => $item,
						'sameAs' => array($item),
						);
					$model->setData($data);
					$uuid = $data['uuid'];
				}
				else
				{
					return $kind . ' "' . $item . '" does not exist';
				}
			}
			$list[$k] = $uuid;
		}
		return true;
	}

	public function rdf($doc, $request)
	{	   
		$doc->namespace('http://purl.org/ontology/po/', 'po');
		$doc->namespace('http://purl.org/ontology/mo/', 'mo');
		$this->rdfDocument($doc, $request);
		$this->rdfResource($doc, $request);
		$this->rdfLinks($doc, $request);
	}

	protected function rdfDocument($doc, $request)
	{
		$resourceGraph = $doc->graph($doc->fileURI);
		$resourceGraph->{'http://purl.org/dc/terms/created'}[] = new RDFDateTime($this->created);
		$resourceGraph->{'http://purl.org/dc/terms/modified'}[] = new RDFDateTime($this->modified);
	}

	protected function rdfResource($doc, $request)
	{
	}

	protected function rdfLinks($doc, $request)	   
	{
		if(isset($this->links))
		{
			foreach($this->links as $link)
			{
				$g = $doc->graph($link['href'], 'http://xmlns.com/foaf/0.1/Document');
				if(isset($link['title']))
				{
					$g->{'http://purl.org/dc/elements/1.1/title'}[] = $link['title'];
				}
				if(isset($link['description']))
				{
					$g->{'http://purl.org/dc/elements/1.1/description'}[] = $link['description'];
				}
				$g->{'http://xmlns.com/foaf/0.1/primaryTopic'}[] = new RDFURI($doc->primaryTopic);
			}
		}
	}

	protected function rdfReferenceInto(&$list, $uri, $request, $fragment = null)
	{
		$r = $this->rdfReference($uri, $request, $fragment, true);
		if(is_array($r))
		{
			foreach($r as $e)
			{
				$list[] = $e;
			}
		}
		else if($r)
		{
			$list[] = $r;
		}
	}

	protected function rdfReference($uri, $request, $fragment = null, $all = false)
	{
		if(strlen($fragment))
		{
			$fragment = '#' . $fragment;
		}
		if(null !== ($uuid = UUID::isUUID($uri)))
		{
			/* Fetch target */
			$obj = self::$models[get_class($this)]->objectForUUID($uuid);
			if($all)
			{
				$list = array();
				while($obj && $obj->kind != 'scheme')
				{
					$list[] = new RDFURI($request->root . $obj->__get('instanceRelativeURI'));
					$obj = $obj['parent'];
				}
				return $list;
			}
			return new RDFURI($request->root . $obj->__get('instanceRelativeURI'));
		}
	    if(substr($uri, 0, 1) == '/')
		{
			return new RDFURI($uri . $fragment);
		}
		return new RDFURI($uri);
	}
}

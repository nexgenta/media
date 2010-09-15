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

/* Convert TV-Anytime classification scheme data to a format
 * we can easily import.
 */

class MediaConvertTVA extends CommandLine
{
	protected $source;
	protected $destDir;
	protected $kind;
	protected $baseURI;
	protected $overrides = null;
	protected $overrideList = array();

	public function checkArgs(&$args)
	{
		if(count($args) != 3 && count($args) != 4)
		{
			return $this->error(Error::NO_OBJECT, null, null, "Usage: convert-tva SOURCE DESTDIR KIND [OVERRIDES]");
		}
		$this->source = $args[0];
		$this->destDir = $args[1];
		$this->kind = $args[2];
		if(count($args) == 4)
		{
			$this->overrides = $args[3];
		}
		if(file_exists($this->destDir))
		{
			$this->destDir = realpath($this->destDir);
			if(!is_dir($this->destDir))
			{
				return $this->error(Error::BAD_REQUEST, null, null, $this->destDir . ' exists but is not a directory');
			}
		}
		else
		{
			if(!mkdir($this->destDir, 0777, true))
			{
				return $this->error(Error::INTERNAL);
			}
			$this->destDir = realpath($this->destDir);
		}
		if(substr($this->destDir, -1) != '/') $this->destDir .= '/';
		if(isset($this->overrides))
		{
			if(!$this->loadOverrides())
			{
				return false;
			}
		}
		return true;
	}

	public function main($args)
	{
		$root = simplexml_load_file($this->source);
		if(!is_object($root))
		{
			return 1;
		}
		$source = basename($this->source);
		if($root->getName() != 'ClassificationScheme')
		{
			echo "$source: Not a TV-Anytime classification scheme XML file\n";
			return 1;
		}
		$a = $root->attributes();
		$uri = trim($a->uri);
		if(!strlen($uri))
		{
			echo "$source: Classification scheme has no base URI\n";
			return 1;
		}
		echo "$source: Importing scheme with URI $uri\n";
		if(substr($uri, -1) != ':') $uri .= ':';
		$this->baseURI = $uri;
		$this->iterateGroup($root);
	}

	protected function iterateGroup($rootNode, $prefix = '', $parentname = null)
	{
		foreach($rootNode->Term as $termNode)
		{
			$this->generateTerm($termNode, $prefix, $parentname);
		}
	}
	
	protected function generateTerm($termNode, $prefix, $parentname)
	{
		$a = $termNode->attributes();
		$termId = trim($a->termID);
		if(!strlen($termId))
		{
			echo "Warning: skipping term with no termID\n";
			return;
		}
		$uri = $this->baseURI . $termId;
		$names = array();
		$definitions = array();
		foreach($termNode->Name as $name)
		{
			$this->addTextWithLanguageToArray($names, $name);
		}
		foreach($termNode->Definition as $def)
		{
			$this->addTextWithLanguageToArray($definitions, $def);
		}
		$defName = $this->defaultLanguage($names, array('en-GB', 'en-US', 'en'));
		$defDesc = $this->defaultLanguage($names, array('en-GB', 'en-US', 'en'));
		$slug = $this->generateSlug($defName);
		if(isset($this->overrideList[$uri]))
		{
			if(isset($this->overrideList[$uri]['title']))
			{
				$defName = $this->overrideList[$uri]['title'];
			}
			if(isset($this->overrideList[$uri]['description']))
			{
				$desc = $this->overrideList[$uri]['description'];
			}
			if(isset($this->overrideList[$uri]['slug']))
			{
				$slug = $this->overrideList[$uri]['slug'];
			}
		}
		$filename = (strlen($parentname) ? $parentname . '-' : null) . str_replace('-', '', $slug);
		echo $this->baseURI . $termId . " => " . $prefix . '/' . $slug . " [" . $filename . ".xml]\n";
		$f = fopen($this->destDir . $filename . '.xml', 'w');
		fwrite($f, '<?xml version="1.0" encoding="UTF-8" ?>' . "\n");
		fwrite($f, '<' . $this->kind . '>' . "\n");
		fwrite($f, "\t" . '<title>' . _e($defName) . '</title>' . "\n");
		fwrite($f, "\t" . '<description>' . _e($defDesc) . '</description>' . "\n");
		fwrite($f, "\t" . '<sameAs>' . _e($this->baseURI . $termId) . '</sameAs>' . "\n");
		fwrite($f, "\t" . '<slug>' . _e($slug) . '</slug>' . "\n");
		if(strlen($prefix))
		{
			fwrite($f, "\t" . '<parent>' . _e($prefix) . '</parent>' . "\n");
		}
		fwrite($f, '</' . $this->kind . '>' . "\n");
		$this->iterateGroup($termNode, $prefix . '/' . $slug, $filename);
	}
	
	protected function addTextWithLanguageToArray(&$array, $node)
	{
		$x = $node->attributes('http://www.w3.org/XML/1998/namespace');
		$lang = trim($x->lang);
		if(!$lang)
		{
			$lang = '__none__';
		}
		$array[$lang] = trim($node);
	}
	
	protected function defaultLanguage($list, $prefList)
	{
		$prefList[] = '__none__';
		foreach($prefList as $lang)
		{
			if(isset($list[$lang]))
			{
				return $list[$lang];
			}
		}
		foreach($list as $l)
		{
			return $l;
		}
		return null;
	}
	
	protected function generateSlug($source)
	{
		$slug = preg_replace('![^a-z0-9]!i', '-', strtolower(trim($source)));
		while(substr($slug, 0, 1) == '-') $slug = substr($slug, 1);
		while(substr($slug, -1) == '-') $slug = substr($slug, 0, -1);
		while(strstr($slug, '--') !== false) $slug = str_replace('--', '-', $slug);		
		return $slug;
	}

	protected function loadOverrides()
	{
		$obj = simplexml_load_file($this->overrides);
		if(!is_object($obj)) return false;
		foreach($obj->term as $term)
		{
			$uri = trim($term->uri);
			$slug = trim($term->slug);
			$title = trim($term->title);
			$description = trim($term->description);
			if(!strlen($uri))
			{
				echo basename($this->overrides) . ": Warning: skipping term with no URI\n";
				continue;
			}
			$info = array();
			if(strlen($slug)) $info['slug'] = $slug;
			if(strlen($title)) $info['title'] = $title;
			if(strlen($description)) $info['description'] = $description;
			if(!count($info))
			{
				echo basename($this->overrides) . ": Warning: skipping term with no overrides\n";
				continue;
			}			
			$this->overrideList[$uri] = $info;
		}
		return true;
	}
}

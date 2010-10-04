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

require_once(dirname(__FILE__) . '/model.php');

class MediaImport extends CommandLine
{
	protected $modelClass = 'Media';

	protected function checkArgs(&$args)
	{
		if(!count($args))
		{
			return $this->error(Error::NO_OBJECT, null, null, 'Usage: media import FILE [FILE ...]');
		}
		return true;
	}

	public function main($args)
	{
		$r = true;
		foreach($args as $pathname)
		{
			if(!$this->importFile($pathname))
			{
				$r = false;
			}
		}
		return ($r ? 0 : 1);
	}

	protected function importFile($pathname)
	{
		$info = pathinfo($pathname);
		$base = basename($pathname);
		if(!isset($info['extension']))
		{
			$info['extension'] = null;
		}
		switch($info['extension'])
		{
		case 'xml':
			require_once(dirname(__FILE__) . '/xmlreader.php');
			$data = XMLMediaReader::read($pathname);
			break;
		case 'rdf':
			uses('redland');
			$model = new RedlandModel();
			$parser = new RedlandParser();
			$parser->parseFileIntoModel($pathname, 'http://www.bbc.co.uk/programmes/b00ty6b0', $model);
			print_r($model);
			break;
		default:
			echo $base . ": Error: Unsupported file type\n";
			return false;
		}
		if(!$data)
		{
			echo $base . ": Error: Unable to import\n";
			return false;
		}
		if(!($asset = Asset::objectForData($data)))
		{
			echo $base . ": Error: Unable to import\n";
			return false;
		}
		if(true !== ($r = $asset->verify()))
		{
			echo $pathname . ": Error: Unable to import: " . $r . "\n";
			return false;
		}
		if(!isset($asset->kind))
		{
			$asset->kind = null;
		}
		if(!isset($asset->uuid))
		{
			if(isset($asset->curie))
			{
				if(null !== ($uuid = $this->model->uuidForCurie($asset->curie)))
				{
					echo $base . ": Note: Matched CURIE [" . $asset->curie . "] to existing UUID " . $uuid . "\n";
					$asset->uuid = $uuid;
				}
			}
			if($asset instanceof Classification)
			{
				if(!isset($asset->uuid) && strlen($asset->slug))
				{
					if(($obj = $this->model->locateObject($asset->slug, $asset->parent, $asset->kind)))
					{	
						$asset->uuid = $obj->uuid;
					}
				}
				if(!isset($asset->uuid) && isset($asset->sameAs))
				{
					foreach($asset->sameAs as $sameAs)
					{
						if(($obj = $this->model->locateObject($sameAs, false, $asset->kind)))
						{	
							$asset->uuid = $obj->uuid;
							break;
						}
					}
				}
				if(!isset($asset->slug) && !isset($asset->sameAs))
				{
					echo $base . ": Refusing to import a " . $asset->kind . " with no useful information. Sorry.\n";
					return 1;
				}
			}
		}
		if(isset($asset->uuid))
		{			
			/* Check to see whether asset already exists with that UUID,
			 * and if so whether it's the same kind.
			 */
			if(($old = $this->model->dataForUuid($asset->uuid)))
			{
				if(!isset($old['kind']))
				{
					$old['kind'] = null;
				}
				if(strcmp($asset->kind, $old['kind']))
				{
					echo $base . ": Warning: Updating asset " . $asset->uuid . " from being a '" . $old['kind'] . "' to being a '" . $asset->kind . "'\n";
				}
				else
				{
					echo $base . ": Updating " . $asset->uuid . "\n";
				}
			}
		}
		if(isset($asset->uuid))
		{
			$created = false;
		}
		else
		{
			$created = true;
		}
		$asset->store();
		if($created)
		{
			echo $base . ": Created with UUID ". $asset->uuid . "\n";
		}
		return true;
	}
	
}


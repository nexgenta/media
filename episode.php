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
				return "Referenced episode '" . $this->series . "' does not exist yet.";
			}
		}
		return parent::verify();
	}

	public function merge()
	{
		if(($obj = $this->offsetGet('series')) || ($obj = $this->offsetGet('show')))
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
}


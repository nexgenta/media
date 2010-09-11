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

require_once(dirname(__FILE__) . '/model.php');

class MediaBrowse extends Page
{
	protected $modelClass = 'Media';
	protected $templateName = 'browse.phtml';
	protected $title = 'Programmes';

	public function __construct()
	{
		parent::__construct();
		$this->routes['a-z'] = array('file' => 'browse-a-z.php', 'class' => 'MediaBrowseAZ');
		$this->routes['genres'] = array('file' => 'browse-genres.php', 'class' => 'MediaBrowseGenres');
		$this->routes['formats'] = array('file' => 'browse-formats.php', 'class' => 'MediaBrowseFormats');
		$this->routes['people'] = array('file' => 'browse-people.php', 'class' => 'MediaBrowsePeople');
	}
	
	protected function getObject()
	{
		if(null === ($tag = $this->request->consume()))
		{
			return true;
		}
		if(($uuid = UUID::isUUID($tag)))
		{
			$this->object = $this->model->objectForUUID($uuid);
		}
		else
		{
			$rs = $this->model->query(array('tag' => $tag, 'limit' => 10));
			$this->object = $rs->next();
		}
		if($this->object)
		{
			switch($this->object->kind)
			{
			case 'episode':
				if(isset($this->object->series) || isset($this->object->show))
				{
					return $this->canonicalEpisodeRedirect();
				}
				require_once(dirname(__FILE__) . '/browse-episode.php');
				$inst = new MediaBrowseEpisode();
				$inst->object = $this->object;
				$inst->process($this->request);
				return false;
			}
			print_r($row);
			die();
		}
		return $this->error(Error::OBJECT_NOT_FOUND);
	}
}

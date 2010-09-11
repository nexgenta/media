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

uses('module');

if(!defined('MEDIA_IRI')) define('MEDIA_IRI', null);

class MediaModule extends Module
{
	public $moduleId = 'com.nexgenta.media';

	public static function getInstance($args = null)
	{
		if(!isset($args['class'])) $args['class'] = 'MediaModule';
		if(!isset($args['db'])) $args['db'] = MEDIA_IRI;
		return parent::getInstance($args);
	}
	
	protected function dependencies()
	{
		$this->depend('com.nexgenta.eregansu.store');
	}
}


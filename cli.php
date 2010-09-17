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

class MediaCommands extends App
{
	public function __construct()
	{
		parent::__construct();
		$this->sapi['cli']['import'] = array('file' => 'import.php', 'class' => 'MediaImport', 'description' => 'Import media data from a file');
		$this->sapi['cli']['convert-tva'] = array('file' => 'convert-tva.php', 'class' => 'MediaConvertTVA', 'description' => 'Convert TV-Anytime classification scheme XML');
		$this->sapi['cli']['sync'] = array('file' => 'sync.php', 'class' => 'MediaSync', 'description' => 'Synchronise dirty objects');
		$this->sapi['cli']['delete'] = array('file' => 'delete.php', 'class' => 'MediaDelete', 'description' => 'Delete an object');
	}
}

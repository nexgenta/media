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

/* Simple XML reader designed for parsing a very basic structure into an
 * associative array.
 */

abstract class XMLMediaReader
{
	public static function read($path)
	{
		$root = simplexml_load_file($path);
		if(!is_object($root))
		{
			return null;
		}
		$data = array();
		self::parseList($root->children(), $data);
		$data['kind'] = $root->getName();
		return $data;
	}
	
	protected static function parseList($nodes, &$data)
	{
		foreach($nodes as $node)
		{
			$key = $node->getName();
			if(isset($data[$key]))
			{
				if(!is_array($data[$key]) || !isset($data[$key][0]))
				{
					$data[$key] = array($data[$key]);
				}
			}
			$children = $node->children();
			$hasChildren = false;
			foreach($children as $c)
			{
				$hasChildren = true;
				break;
			}
			if($hasChildren)
			{
				$value = array();
				self::parseList($children, $value);
			}
			else
			{
				$value = trim($node);
			}
			if(isset($data[$key]))
			{
				$data[$key][] = $value;
			}
			else
			{
				$data[$key] = $value;
			}
		}
	}
}
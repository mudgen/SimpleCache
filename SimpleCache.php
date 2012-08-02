<?php
/* SimpleCache 1.0
Please see installation instructions and documentation below.
You don't have to, but I'd like to hear of anyone using this 
or if any bugs have been found, or any trouble with it, or
if any improvements have been made to it. - Nick

It's announcement: http://www.nickmudge.info/index.php?post=109

BSD license:
--
Copyright (c) 2009 Nick Mudge (mudgen@gmail.com, http://nickmudge.info)
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
   derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
--

Requirements: SimpleCache requires PHP 5 or higher

How to install and use SimpleCache:
1. Create a directory where you want to store your cache files. 
   Make this directory writable by your web server
   e.g. chown nobody:nobody /web/guestbook/cache/ or chmod 0777 /web/guestbook/cache/
2. Assign the relative or full file path of the cache directory you created to the $cache_dir 
   variable in SimpleCache.php file.
3. Include SimpleCache.php in files you want to use caching with.

Example of use in PHP file:
	require('SimpleCache.php');
	if(SimpleCache::isCached('months.cache'))
		$months =  SimpleCache::getCache('months.cache');
	else {	
		$query = "SELECT month FROM posts ORDER BY month DESC";
		$months = query($query);
		SimpleCache::cache($months); //$months is saved in the months.cache file as specified by isCached
	}

Using cache subdirectories:
You can make subdirectories within the main cache directory to store cache of different kinds 
in different places. For instance you could have the cache directories: cache/news/ and cache/features/
to hold the news cache and the features cache. Then, for example, to access those caches you could use paths 
like: 'news/news1.cache' and 'features/features1.cache' in the isCached method call. And you could then 
delete one kind of cache without deleting the other kind of cache by using deleteCache('news/') or
deleteCache('features/'). To delete both caches at the same time, you could use
deleteCache(array('news/', 'features/')).

How to delete the cache:
After you have made some change to data that you have been caching you will probably want to delete the cache
for the data so that a new, updated cache can be created for that data. Here is an example of deleting the 
cache:
	require('SimpleCache.php');
	mysql_query("UPDATE posts SET title='$post_title', post='$post_post', date='$post_date' WHERE id=$post_id");
	SimpleCache::deleteCache();
If the post was in a cache subdirectory called cache/posts/ then deleteCache would be called with this path: 
SimpleCache::deleteCache('posts/') If you don't want to delete all the cache files in a directory, then 
name the file you do want to delete in the deleteCache call, like so: deleteCache('post21.cache') or
deleteCache('posts/post21.cache').

How to turn caching off for files that are using it:
Simply put this above any code that is using SimpleCache function/method calls: SimpleCache::$cache_on = false;
To disable caching for all files using SimpleCache set $cache_on to false in SimpleCache.php 

How to detect errors with caching:
To detect any errors with the caching view the contents of the $errors array, like so: print_r(SimpleCache::$errors);

Note:
You must make the isCached method call before you can use the cache or getCache method calls.
The reason is because isCached saves the path given to it and that path is automatically reused within cache and
getCache method calls.

Note: 
You never make an instance of the SimpleCache class. You just use the static variables and static methods
of the class.
*/

class SimpleCache {
	public  static $cache_on = true;       //Set this to turn caching on or off
	private static $cache_dir = 'cache/';  //Root cache directory where cache files are stored
	
	private static $path = '';             //Used internally, probably don't want to touch.
	public static $errors = array();       //View the contents of this array to view any errors
	
	//Checks to see if a cache file exists
	//Always call this method before cache and getCache methods
	public static function isCached($file) {
		if(empty($file)) return self::error("isCached: file not found: $file");
		self::$path .= self::$cache_dir.$file;
		if(self::$cache_on)
			return is_file(self::$path);
		else
			return false;
	}
	
	//Saves data to cache file specified in isCached call
	public static function cache($data) {
		if(empty($data)) return self::error("cache: no data give to cache.");
		if(self::$path === '') return self::error("cache: isCached must be called before cache method.");
		$bytes = file_put_contents(self::$path, serialize($data), LOCK_EX);
		if($bytes === false)
			self::error("cache: writing error. possible permissions error of cache directory.");
		self::$path = '';
		return $bytes;
	}
	
	//Retrieves data from cache file specified in isCached call
	public static function getCache($file) {
		if(empty($file)) return self::error("getCache: file argument is empty.");
		if(self::$path === '') return self::error("getCache: isCached must be called before getCached");
		$data = unserialize(file_get_contents(self::$path));
		self::$path = '';
		return $data;
	}
	
	//Deletes cache files
	//Receives a single string or array of relative file paths to cache directories and/or files
	//The file paths are relative to the root cache directory set in $cache_dir
	//If a file path is to a file then the file is deleted, 
	//if the file path is to a directory, then the files (not directories) in the directory are deleted
	//If no argument is given, then all files (not directories) are deleted in the $cache_dir directory
	public static function deleteCache($paths=array('')) {
		if(!is_array($paths)) $paths = array($paths);
		foreach($paths as $path) {			
			$shell_path = escapeshellarg(rtrim(self::$cache_dir.$path, '/'));
			if(is_dir(self::$cache_dir.$path))
				exec("rm $shell_path/*");
			else if(is_file(self::$cache_dir.$path))
				exec("rm $shell_path");
			else
				self::error("deleteCache: file or directory does not exist: ".self::$cache_dir.$path);
		}
	}
	
	//Collects any errors that occur with caching, such as file write error etc.
	protected static function error($message) {
		self::$errors[] = $message;
		return false;
	}	
}
?>
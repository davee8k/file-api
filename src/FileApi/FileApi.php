<?php
namespace FileApi;

use InvalidArgumentException,
	finfo;

/**
 * File APIs for browsering and interaction with files
 *
 * @author DaVee8k
 * @license https://unlicense.org/
 * @version 0.86.1
 */
class FileApi {
	protected $location;
	protected $path = '';
	protected $error = '';

	public static $msgs = [
		'FILE' => 'File',
		'DIR' => 'Directory',
		'NO_UPLOAD' => 'No file to upload was specified.',
		'UPLOAD_MAX_SIZE' => 'File %s has exceeded the maximum size.',
		'UPLOAD_STOPPED' => 'Upload of file %s has been stopped.',
		'UPLOAD_ERROR' => 'Unable to upload file %1$s (error %2$d).',
		'NO_RIGHTS' => 'You do not have write rights to: %s.',
		'NO_NAME' => 'No new name was entered.',
		'EXIST' => ' %s already exists.',
		'NOT_EMPTY' => ' %s is not empty.',
		'NOT_FOUND' => ' %s not found.',
		'NOT_MOVE' => ' %s cannot be copied.',
		'NOT_COPY' => ' %s cannot be moved.',
		'NOT_RENAME' => ' %s cannot be renamed.',
		'NOT_DELETE' => ' %s cannot be deleted.',
		'CURL_ERROR' => 'Curl error: %d',
		'GENERAL_ERROR' => 'General error: %s'
	];

	/**
	 * Escape filename and add quotes
	 * @param string $value
	 * @param string $quote
	 * @return string
	 */
	public static function escape ($value, $quote = "'") {
		return htmlspecialchars($quote.$value.$quote, ENT_QUOTES);
	}

	/**
	 * @param string $loc
	 * @throws InvalidArgumentException
	 */
	public function __construct ($loc) {
		$this->location = $loc;
		$this->path = $loc;
		if (!$this->setPath('')) throw new InvalidArgumentException($this->getMsg('NOT_FOUND', self::escape($loc), 'DIR'));
	}

	/**
	 * Remove /../ up directory from url
	 * @param string $dir
	 * @return string
	 */
	public function filterPath ($dir) {
		return preg_replace('/(^|\/){1}\.\.\//', '/', $dir);
	}

	/**
	 * Return last error message
	 * @return string
	 */
	public function getError () {
		return $this->error;
	}

	/**
	 * Return current path
	 * @return string
	 */
	public function getPath () {
		return $this->path;
	}

	/**
	 * Remove current $path from inserted path
	 * @param string $dir
	 * @return string
	 */
	public function cropPath ($dir) {
		return preg_replace('/^'.preg_quote($this->path, '/').'/', '', $dir);
	}

	/**
	 * Add DIRECTORY_SEPARATOR
	 * @param string $dir
	 * @param bool $isDir
	 * @return string
	 */
	public function fixPath ($dir, $isDir = true) {
		if ($dir !== '') {
			if (strpos($dir, './') === 0) $dir = substr($dir, 2);
			if ($isDir && substr($dir, -1) !== DIRECTORY_SEPARATOR) $dir .= DIRECTORY_SEPARATOR;
		}
		return $dir;
	}

	/**
	 * Return full formated path with $dir
	 * @param string $dir
	 * @param bool $isDir
	 * @return string
	 */
	public function makePath ($dir, $isDir = true) {
		$dir = $this->fixPath($dir, $isDir);
		if ($dir === '') return $this->path;
		if (strpos($dir, $this->path) === 0) return $dir;
		return $this->path.$dir;
	}

	/**
	 * Set current path if exists
	 * @param string $dir
	 * @return bool
	 */
	public function setPath ($dir) {
		$currentPath = $this->path;
		$this->path = $this->location;

		$dir = $this->fixPath($dir);
		if ($this->exist($dir)) {
			$this->path = $this->location.$dir;
			return true;
		}
		$this->path = $currentPath;
		return false;
	}

	/**
	 * Get info form $_FILES
	 * @param string $input
	 * @param string $type
	 * @param int|null $id
	 * @return mixed
	 */
	public function getUpload ($input, $type, $id = null) {
		if ($id === null) return $_FILES[$input][$type];
		return $_FILES[$input][$type][$id];
	}

	/**
	 * Set value in $_FILES
	 * @param string $input
	 * @param string $type
	 * @param mixed $val
	 * @param int|null $id
	 */
	public function setUpload ($input, $type, $val, $id = null) {
		if (!isset($_FILES[$input])) $_FILES[$input] = [];
		if ($id === null) $_FILES[$input][$type] = $val;
		else {
			if (!isset($_FILES[$input][$type])) $_FILES[$input][$type] = [];
			$_FILES[$input][$type][$id] = $val;
		}
	}

	/**
	 * Get file MIME type
	 * @param string $url
	 * @return string
	 */
	public function getMime ($url) {
		if (class_exists('finfo')) {
			$info = new finfo(FILEINFO_MIME_TYPE);
			return $info->file($this->makePath($url, false));
		}
		if (is_callable('mime_content_type')) mime_content_type($this->makePath($url, false));
		return 'application/octet-stream';
	}

	/**
	 * Get file size
	 * @param string $url
	 * @return int
	 */
	public function getSize ($url) {
		return filesize($this->makePath($url, false));
	}

	/**
	 * File exists
	 * @param string $dir
	 * @param string|null $file
	 * @param bool $caseSensitive
	 * @return bool
	 */
	public function exist ($dir, $file = null, $caseSensitive = false) {
		if ($caseSensitive) {
			if ($this->exist($dir, $file, false)) {
				return true;
			}
			// check directory
			$flags = GLOB_NOSORT;
			if ($file === null) {
				$flags = GLOB_NOSORT|GLOB_ONLYDIR;

				$lastPos = strrpos($dir, DIRECTORY_SEPARATOR);
				if ($lastPos) {
					$file = substr($dir, $lastPos + 1);
					$dir = substr($dir, 0, $lastPos + 1);
				}
				else {
					$file = $dir;
					$dir = '';
				}
			}
			// make regex for glob
			$path = $this->makePath($dir);
			$chars = [mb_strtolower($file[0]), mb_strtoupper($file[0])];
			if ($chars[0] === $chars[1]) array_pop($chars);
			$regex = $path.'['.implode('', $chars).']'.(mb_strlen($file) > 1 ? '*' : '');
			$preLength = strlen($path);
			$file = mb_strtolower($file);

			foreach (glob($regex, $flags) as $item) {
				if (mb_strtolower(substr($item, $preLength)) === $file) return true;
			}
			return false;
		}

		if ($file === null) return is_dir($this->makePath($dir));
		return is_file($this->makePath($dir).$file);
	}

	/**
	 * Get list of files in directory
	 * @param string $dir
	 * @return array
	 */
	public function loadFiles ($dir = '') {
		$files = [];
		$path = $this->makePath($dir);
		$dh = opendir($path);
		if ($dh) {
			while (false !== ($name = readdir($dh))) {
				if (!in_array($name, ['.','..'])) {
					if (!is_dir($path.$name)) {
						$files[$name] = ['NAME'=>$name, 'DATE'=>filectime($path.$name),
							'SIZE'=>filesize($path.$name), 'MIME'=>$this->getMime($path.$name)];
					}
				}
			}
			closedir($dh);
		}
		return $files;
	}

	/**
	 * Check if uploaded file is ok
	 * @param string $input
	 * @param int|null $id
	 * @param bool $emptyIsOk
	 * @return bool
	 */
	public function isUpload ($input, $id = null, $emptyIsOk = false) {
		$errNum = $this->getUpload($input, 'error', $id);
		if ($errNum === 0) return true;

		if ($errNum === 4) {
			if ($emptyIsOk) return true;
			$this->error = $this->getMsg('NO_UPLOAD');
		}
		else {
			$escName = self::escape($this->getUpload($input, 'name', $id));
			if ($errNum === 1 || $errNum == 2) $this->error = $this->getMsg('UPLOAD_MAX_SIZE', $escName);
			else if ($errNum === 3) $this->error = $this->getMsg('UPLOAD_STOPPED', $escName);
			else $this->error = $this->getMsg('UPLOAD_ERROR', $escName, null, $errNum);
		}
		return false;
	}

	/**
	 * Setup $_FILE input
	 * @param string $input
	 * @param int|null $id
	 * @param string $filePath
	 * @param string $name
	 * @throws InvalidArgumentException
	 */
	public function fakeUpload ($input, $id, $filePath, $name) {
		if ($id === null ? isset($_FILES[$input]['name']) : isset($_FILES[$input]['name'][$id])) {
			throw new InvalidArgumentException('File input already exists.');
		}
		$this->setUpload($input, 'name', $name, $id);
		$this->setUpload($input, 'type', $this->getMime($filePath), $id);
		$this->setUpload($input, 'tmp_name', $filePath, $id);
		$this->setUpload($input, 'error', 0, $id);
		$this->setUpload($input, 'size', $this->getSize($filePath), $id);
	}

	/**
	 * Copy file to specific array space
	 * @param string $fromMark
	 * @param array|int|null $fromLevels
	 * @param string $toMark
	 * @param array|int|null $toLevels
	 * @return bool
	 */
	public function clonePost ($fromMark, $fromLevels, $toMark, $toLevels) {
		foreach ($_FILES[$fromMark] as $mark=>$none) {
			$from = $_FILES[$fromMark][$mark];
			if ($fromLevels !== null) {
				if (!is_array($fromLevels)) $fromLevels = [$fromLevels];
				foreach ($fromLevels as $num) {
					if (!isset($from[$num])) return false;
					$from = $from[$num];
				}
			}
			if ($toLevels === null) $_FILES[$toMark][$mark] = $from;
			else if (!is_array($toLevels)) $_FILES[$toMark][$mark][$toLevels] = $from;
			else {
				switch (count($toLevels)) {
					case 1: $_FILES[$toMark][$mark] = $from; break;
					case 2: $_FILES[$toMark][$mark][$toLevels[0]][$toLevels[1]] = $from; break;
					case 3: $_FILES[$toMark][$mark][$toLevels[0]][$toLevels[1]][$toLevels[2]] = $from; break;
				}
			}
		}
		return true;
	}

	/**
	 * Get file with CURL
	 * @param string $url
	 * @return mixed
	 */
	public function getUrlData ($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HEADER, 0); // vypne hlavicku
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; U; Linux x86_64; en-US; rv:1.9.1)');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // vypne vypis
		curl_setopt($ch, CURLOPT_URL, $url);
		$data = curl_exec($ch);
		// check return content
		$returnCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if (preg_match('/(^40)|(^50)/', $returnCode)) {
			$this->error = $this->getMsg('CURL_ERROR', $returnCode);
			return false;
		}
		return $data;
	}

	/**
	 * Download file to client
	 * @param string $dir
	 * @param string $file
	 * @param bool $stream
	 * @return bool
	 */
	public function downloadFile ($dir, $file, $stream = false) {
		if (!$this->exist($dir, $file)) return false;

		$range = false;
		if (isset($_SERVER['HTTP_RANGE'])) {
			list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
			if ($size_unit == 'bytes') {
				// only one range - http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
				list($range, $extra_ranges) = explode(',', $range_orig, 2);
				$range = explode('-', $range, 2);
			}
			else {
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				$this->error = $this->getMsg('CURL_ERROR', 416);
				return false;
			}
		}

		// try to turn off compression
		@apache_setenv('no-gzip', 1);
		@ini_set('zlib.output_compression', 'Off');

		// open file
		$fileSize = filesize($this->makePath($dir).$file);
		$fp = @fopen($this->makePath($dir).$file, 'rb');

		// setup headers
		header('Pragma: public');
		header('Expires: -1');
		header('Cache-Control: public, must-revalidate, post-check=0, pre-check=0');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="'.$file.'"');
		if ($stream) header('Content-Disposition: inline;');
		// allow partial downloads
		header('Accept-Ranges: bytes');

		// figure out download piece from range (if set)
		$seekStart = 0;
		$seekEnd = $fileSize - 1;
		if (isset($range[1])) $seekEnd = min(abs(intval($range[1])), $seekEnd);
		if ($range && $seekEnd > abs(intval($range[0]))) $seekStart = max(abs(intval($range[0])), 0);

		// send only partial content header if downloading a piece of the file (IE workaround)
		if ($seekStart > 0 || $seekEnd < ($fileSize - 1)) {
			header('HTTP/1.1 206 Partial Content');
			header('Content-Range: bytes '.$seekStart.'-'.$seekEnd.'/'.$fileSize);
			header('Content-Length: '.($seekEnd - $seekStart + 1));
		}
		else header('Content-Length: '.$fileSize);

		// download file
		fseek($fp, $seekStart);
		while (!feof($fp)) {
			echo @fread($fp, 1024 * 8);
			// client disconnected
			if (connection_status() !== 0) {
				@fclose($fp);
				exit;
			}
		}
		@fclose($fp);
		return true;
	}

	/**
	 * Get message text
	 * @param string $msg
	 * @param string|int|null $param
	 * @param string $type
	 * @param int|null $num
	 * @return string
	 */
	protected function getMsg ($msg, $param = null, $type = null, $num = null) {
		if (isset(static::$msgs[$msg])) {
			return ($type ? static::$msgs[$type] : '').sprintf(static::$msgs[$msg], $param, $num);
		}
		return $type.$msg;
	}
}
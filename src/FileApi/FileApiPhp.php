<?php
namespace FileApi;

/**
 * File interaction throught PHP
 */
class FileApiPhp extends FileApi {
	static $mode = 0777;	// for directories

	/**
	 * Is it possible to write into filename
	 * @param string $url
	 * @return bool
	 */
	public function isWritable ($url) {
		return is_writable($this->makePath($url, false));
	}

	/**
	 * Rename file
	 * @param string $dir
	 * @param string $oldFile
	 * @param string $newFile
	 * @return bool
	 */
	public function rename ($dir, $oldFile, $newFile) {
		if (!$newFile) {
			$this->error = $this->getMsg('NO_NAME');
		}
		else if (!$this->exist($dir, $oldFile)) {
			$this->error = $this->getMsg('NOT_FOUND', self::escape($oldFile), 'FILE');
		}
		else {
			if ($this->uniRename($dir, $dir, $oldFile, $newFile)) return true;
			$this->error = $this->getMsg('NOT_RENAME', self::escape($oldFile), 'FILE');
		}
		return false;
	}

	/**
	 * Copy file or directory
	 * @param string $dirOld
	 * @param string $dirNew
	 * @param string|null $fileOld
	 * @param string|null $fileNew
	 * @return bool
	 */
	public function copy ($dirOld, $dirNew, $fileOld = null, $fileNew = null) {
		return $this->processAction($dirOld, $dirNew, $fileOld, $fileNew ? $fileNew : $fileOld, true);
	}

	/**
	 * Move (rename) file or directory
	 * @param string $dirOld
	 * @param string $dirNew
	 * @param string|null $fileOld
	 * @param string|null $fileNew
	 * @return bool
	 */
	public function move ($dirOld, $dirNew, $fileOld = null, $fileNew = null) {
		return $this->processAction($dirOld, $dirNew, $fileOld, $fileNew ? $fileNew : $fileOld, false);
	}

	/**
	 * Delete file or directory
	 * @param string $dir
	 * @param string|null $file
	 * @return bool
	 */
	public function delete ($dir, $file = null) {
		$this->error = $this->getMsg('NOT_FOUND', self::escape($file ? $file : $dir), $file ? 'FILE' : 'DIR');
		if ($this->exist($dir, $file)) {
			if ($file === null) {
				$dh = opendir($this->makePath($dir));
				while ($cont = readdir($dh)) {
					if ($cont != '.' && $cont != '..') {
						closedir($dh);
						$this->error = $this->getMsg('NOT_EMPTY', self::escape($dir), 'DIR');
						return false;
					}
				}
				closedir($dh);
				if ($this->dirDelete($dir)) return true;
				$this->error = $this->getMsg('NOT_DELETE', self::escape($dir), 'DIR');
			}
			else if ($this->fileDelete($dir, $file)) return true;
			$this->error = $this->getMsg('NOT_DELETE', self::escape($file), 'FILE');
		}
		return false;
	}

	/**
	 * File upload. Error message is from isUpload
	 * @param string $input
	 * @param integer $id
	 * @param string $dir
	 * @param string|null $file
	 * @param bool $copy
	 * @return bool
	 */
	public function upload ($input, $id, $dir, $file = null, $copy = false) {
		if ($this->isUpload($input, $id)) {
			if ($file === null) $file = $this->getUpload($input, 'name', $id);
			if (!$this->isWritable($dir)) $this->error = $this->getMsg('NO_RIGHTS', self::escape($this->path.$dir));
			else {
				$tmpName = $this->getUpload($input, 'tmp_name', $id);
				if ($copy && $this->fileCopy(false, $dir, $tmpName, $file)) return true;
				if (!$copy && move_uploaded_file($tmpName, $this->makePath($dir).$file)) return true;
				$this->error = $this->getMsg('GENERAL_ERROR', self::escape($tmpName.', '.$dir.$file));
			}
		}
		return false;
	}

	/**
	 * Create directory
	 * @param string $dir
	 * @return bool
	 */
	public function createDir ($dir) {
		$path = $this->cropPath($dir);
		if (!is_dir($this->makePath($path))) {
			$way = "";
			$dirs = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
			for ($i = 0; $i < count($dirs); $i++) {
				if (!$this->exist($this->makePath($way.$dirs[$i]))) {
					if (!$this->isWritable($way)) {
						$this->error = $this->getMsg('NO_RIGHTS', self::escape($this->path.$way));
						return false;
					}
					else if (!$this->dirMake($way.$dirs[$i])) {
						$this->error = $this->getMsg('GENERAL_ERROR', self::escape($dirs[$i]));
						return false;
					}
				}
				$way .= $dirs[$i].DIRECTORY_SEPARATOR;
			}
		}
		return true;
	}

	/**
	 * Create file
	 * @param string $dir
	 * @param string $file
	 * @param string $data
	 * @param bool $replace
	 * @return bool
	 */
	public function createFile ($dir, $file, $data, $replace = false) {
		if ($file == '' || !$replace && $this->exist($dir, $file)) {
			$this->error = $this->getMsg('EXIST', self::escape($file), 'FILE');
		}
		else if ($this->createDir($dir)) {
			if (!$this->isWritable($dir)) {
				$this->error = $this->getMsg('NO_RIGHTS', self::escape($this->path.$dir));
				return false;
			}
			if (file_put_contents($this->makePath($dir).$file, $data, LOCK_EX) !== false) return true;
			$this->error = $this->getMsg('GENERAL_ERROR', self::escape($file));
		}
		return false;
	}

	/**
	 * Process action form Copy and Move
	 * @param string $dirOld
	 * @param string $dirNew
	 * @param string $fileOld
	 * @param string $fileNew
	 * @param bool $copy
	 * @return bool
	 */
	protected function processAction ($dirOld, $dirNew, $fileOld, $fileNew, $copy = false) {
		if ($this->exist($dirOld, $fileOld)) {
			if (!$this->exist($dirNew, $fileNew)) {
				if (!$this->createDir($dirNew)) return false;
				if ($copy) {
					if (!$fileNew) return true;
					if ($this->fileCopy($dirOld, $dirNew, $fileOld, $fileNew)) return true;
					$this->error = $this->getMsg('NO_COPY', self::escape($fileOld ? $fileOld : $dirOld), $fileOld ? 'FILE' : 'DIR');
				}
				else {
					if ($this->uniRename($dirOld, $dirNew, $fileOld, $fileNew)) return true;
					$this->error = $this->getMsg('NO_MOVE', self::escape($fileOld ? $fileOld : $dirOld), $fileOld ? 'FILE' : 'DIR');
				}
				return false;
			}
			$this->error = $this->getMsg('EXIST', self::escape($fileNew ? $fileNew : $dirNew), $fileNew ? 'FILE' : 'DIR');
			return false;
		}
		$this->error = $this->getMsg('NOT_FOUND', self::escape($fileOld ? $fileOld : $dirOld), $fileOld ? 'FILE' : 'DIR');
		return false;
	}

	/**
	 * Create directory
	 * @param string $dir
	 * @return bool
	 */
	protected function dirMake ($dir) {
		return @mkdir($this->makePath($dir), self::$mode);
	}

	/**
	 * Delete directory
	 * @param string $dir
	 * @return bool
	 */
	protected function dirDelete ($dir) {
		return @rmdir($this->makePath($dir));
	}

	/**
	 * DirOld for TMP file
	 * @param mixed $dirOld
	 * @param string $dirNew
	 * @param string $fileOld
	 * @param string $fileNew
	 * @return bool
	 */
	protected function fileCopy ($dirOld, $dirNew, $fileOld, $fileNew) {
		return @copy($dirOld === false ? $fileOld : $this->makePath($dirOld).$fileOld, $this->makePath($dirNew).$fileNew);
	}

	/**
	 * Delete file
	 * @param string $file
	 * @return bool
	 */
	protected function fileDelete ($dir, $file) {
		return @unlink($this->makePath($dir).$file);
	}

	/**
	 * Rename file or directory
	 * @param string $dirOld
	 * @param string $dirNew
	 * @param string $fileOld
	 * @param string $fileNew
	 * @return bool
	 */
	protected function uniRename ($dirOld, $dirNew, $fileOld, $fileNew) {
		return @rename($this->makePath($dirOld).$fileOld, $this->makePath($dirNew).$fileNew);
	}
}
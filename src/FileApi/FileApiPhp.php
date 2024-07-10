<?php declare(strict_types=1);

namespace FileApi;

/**
 * File interaction throughout PHP
 */
class FileApiPhp extends FileApi {
	/** @var int	Linux rights for directories */
	public static $mode = 0777;

	/**
	 * Is it possible to write into filename
	 * @param string $url
	 * @return bool
	 */
	public function isWritable (string $url): bool {
		return is_writable($this->makePath($url, false));
	}

	/**
	 * Rename file
	 * @param string $dir
	 * @param string $oldFile
	 * @param string $newFile
	 * @return bool
	 */
	public function rename (string $dir, string $oldFile, string $newFile): bool {
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
	public function copy (string $dirOld, string $dirNew, string $fileOld = null, string $fileNew = null): bool {
		return $this->processAction($dirOld, $dirNew, $fileOld, $fileNew ?: $fileOld, true);
	}

	/**
	 * Move (rename) file or directory
	 * @param string $dirOld
	 * @param string $dirNew
	 * @param string|null $fileOld
	 * @param string|null $fileNew
	 * @return bool
	 */
	public function move (string $dirOld, string $dirNew, string $fileOld = null, string $fileNew = null): bool {
		return $this->processAction($dirOld, $dirNew, $fileOld, $fileNew ?: $fileOld, false);
	}

	/**
	 * Delete file or directory
	 * @param string $dir
	 * @param string|null $file
	 * @return bool
	 */
	public function delete (string $dir, string $file = null): bool {
		$this->error = $this->getMsg('NOT_FOUND', self::escape($file ?: $dir), $file ? 'FILE' : 'DIR');
		if ($this->exist($dir, $file)) {
			if ($file === null) {
				$handle = opendir($this->makePath($dir));
				if ($handle === false) {
					$this->error = $this->getMsg('GENERAL_ERROR', self::escape($dir), 'DIR');
					return false;
				}

				while ($cont = readdir($handle)) {
					if ($cont != '.' && $cont != '..') {
						closedir($handle);
						$this->error = $this->getMsg('NOT_EMPTY', self::escape($dir), 'DIR');
						return false;
					}
				}
				closedir($handle);
				if ($this->dirDelete($dir)) return true;
				$this->error = $this->getMsg('NOT_DELETE', self::escape($dir), 'DIR');
			}
			else {
				if ($this->fileDelete($dir, $file)) return true;
				$this->error = $this->getMsg('NOT_DELETE', self::escape($file), 'FILE');
			}
		}
		return false;
	}

	/**
	 * File upload. Error message is from isUpload
	 * @param string $input
	 * @param int|null $num
	 * @param string $dir
	 * @param string|null $file
	 * @param bool $copy
	 * @return bool
	 */
	public function upload (string $input, ?int $num, string $dir, string $file = null, bool $copy = false): bool {
		if ($this->isUpload($input, $num)) {
			if ($file === null) $file = $this->getUpload($input, 'name', $num);
			if (!$this->isWritable($dir)) $this->error = $this->getMsg('NO_RIGHTS', self::escape($this->path.$dir));
			else {
				$tmpName = $this->getUpload($input, 'tmp_name', $num);
				if ($copy) {
					if ($this->fileCopy(null, $dir, $tmpName, $file)) return true;
				}
				else {
					if (move_uploaded_file($tmpName, $this->makePath($dir).$file)) return true;
				}
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
	public function createDir (string $dir): bool {
		$path = $this->cropPath($dir);
		if (!is_dir($this->makePath($path))) {
			$way = "";
			$dirs = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
			foreach ($dirs as $dir) {
				if (!$this->exist($this->makePath($way.$dir))) {
					if (!$this->isWritable($way)) {
						$this->error = $this->getMsg('NO_RIGHTS', self::escape($this->path.$way));
						return false;
					}
					else if (!$this->dirMake($way.$dir)) {
						$this->error = $this->getMsg('GENERAL_ERROR', self::escape($dir));
						return false;
					}
				}
				$way .= $dir.DIRECTORY_SEPARATOR;
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
	public function createFile (string $dir, string $file, string $data, bool $replace = false): bool {
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
	 * @param string|null $fileOld
	 * @param string|null $fileNew
	 * @param bool $copy
	 * @return bool
	 */
	protected function processAction (string $dirOld, string $dirNew, ?string $fileOld,  ?string $fileNew, bool $copy = false): bool {
		if ($this->exist($dirOld, $fileOld)) {
			if (!$this->exist($dirNew, $fileNew)) {
				if (!$this->createDir($dirNew)) return false;
				if ($copy) {
					if ($fileOld === null || $fileNew === null) return true;
					if ($this->fileCopy($dirOld, $dirNew, $fileOld, $fileNew)) return true;
					$this->error = $this->getMsg('NOT_COPY', self::escape($fileOld ?: $dirOld), $fileOld ? 'FILE' : 'DIR');
				}
				else {
					if ($this->uniRename($dirOld, $dirNew, $fileOld, $fileNew)) return true;
					$this->error = $this->getMsg('NOT_MOVE', self::escape($fileOld ?: $dirOld), $fileOld ? 'FILE' : 'DIR');
				}
				return false;
			}
			$this->error = $this->getMsg('EXIST', self::escape($fileNew ?: $dirNew), $fileNew ? 'FILE' : 'DIR');
			return false;
		}
		$this->error = $this->getMsg('NOT_FOUND', self::escape($fileOld ?: $dirOld), $fileOld ? 'FILE' : 'DIR');
		return false;
	}

	/**
	 * Create directory
	 * @param string $dir
	 * @return bool
	 */
	protected function dirMake (string $dir): bool {
		return @mkdir($this->makePath($dir), self::$mode);
	}

	/**
	 * Delete directory
	 * @param string $dir
	 * @return bool
	 */
	protected function dirDelete (string $dir): bool {
		return @rmdir($this->makePath($dir));
	}

	/**
	 * DirOld for TMP file
	 * @param string|null $dirOld
	 * @param string $dirNew
	 * @param string $fileOld
	 * @param string $fileNew
	 * @return bool
	 */
	protected function fileCopy (?string $dirOld, string  $dirNew, string $fileOld, string $fileNew): bool {
		return @copy($dirOld === null ? $fileOld : $this->makePath($dirOld).$fileOld, $this->makePath($dirNew).$fileNew);
	}

	/**
	 * Delete file
	 * @param string $dir
	 * @param string $file
	 * @return bool
	 */
	protected function fileDelete (string $dir, string $file): bool {
		return @unlink($this->makePath($dir).$file);
	}

	/**
	 * Rename file or directory
	 * @param string $dirOld
	 * @param string $dirNew
	 * @param string|null $fileOld
	 * @param string|null $fileNew
	 * @return bool
	 */
	protected function uniRename (string $dirOld, string $dirNew, ?string $fileOld, ?string $fileNew): bool {
		return @rename($this->makePath($dirOld).$fileOld, $this->makePath($dirNew).$fileNew);
	}
}

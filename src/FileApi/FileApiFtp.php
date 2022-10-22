<?php
namespace FileApi;

use InvalidArgumentException;

/**
 * File interaction throught FTP
 * !!! Do NOT use it - NOT SAFE !!!
 */
class FileApiFtp extends FileApiPhp {
	private $ftp;
	protected $connect = null;
	protected $pathFTP;

	/**
	 * @param string $loc
	 * @param array $ftp
	 */
	public function __construct ($loc, array $ftp) {
		$this->ftp = $ftp;
		$this->pathFTP = $ftp['DIR'];
		parent::__construct($loc);
	}

	/**
	 * Close FTP connection
	 */
	public function __destruct () {
		if ($this->connect) ftp_close($this->connect);
	}

	/**
	 * Create path for FTP connection
	 * @param string $dir
	 * @return string
	 */
	function makeFtpPath ($dir) {
		if ($dir === '') return $this->pathFTP;
		if ($dir !== '' && strpos($dir, $this->path) === 0) $dir = $this->cropPath($dir);
		return $this->pathFTP.(strrpos($dir, DIRECTORY_SEPARATOR) === 0 ? $dir.DIRECTORY_SEPARATOR : $dir);
	}

	/**
	 * Set current path if exists
	 * @param string $dir
	 * @return bool
	 */
	function setPath ($dir) {
		if (parent::setPath($dir)) {
			$this->pathFTP = $this->ftp['DIR'].$dir;
			return true;
		}
		return false;
	}

	/**
	 * Is it possible to write into filename
	 * @param string $url
	 * @return bool
	 */
	function isWritable ($url) {
		return true;
	}

	/**
	 * Error message is from isUpload
	 * @param string $input
	 * @param integer $id
	 * @param string|null $dir
	 * @param mixed $file
	 * @param bool $copy
	 * @return bool
	 */
	public function upload ($input, $id, $dir, $file = null, $copy = false) {
		$ftpFile = $this->makeFtpPath($dir).$file;
		if ($this->isUpload($input, $id)) {
			$this->error = 'Soubor nelze nahrát.';
			if ($file === null) $file = $this->getUpload($input, 'name', $id);
			if ($this->getUpload($input, 'error', $id)) $this->error = $this->getMsg('UPLOAD_MAX_SIZE', self::escape($file));
			if (!$this->isWritable($this->makePath($dir))) $this->error = $this->getMsg('NO_RIGHTS', self::escape($file));
			else {
				$mode = eregi('^text', $this->getUpload($input, 'type', $id)) ? FTP_ASCII : FTP_BINARY;
				$tmpName = $this->getUpload($input, 'tmp_name', $id);
				if ($copy) {
					$handle = fopen($ftpFile, 'w');
					if (ftp_get($this->connection(), $handle, $tmpName, $mode)) return true;
				}
				else {
					if (ftp_put($this->connection(), $ftpFile, $tmpName, $mode)) return true;
				}
				$this->error = $this->getMsg('GENERAL_ERROR', self::escape($tmpName.', '.$dir.$file));
			}
		}
		return false;
	}

	/**
	 * Initialize ftp connection
	 * @return FTP\Connection
	 * @throws InvalidArgumentException
	 */
	public function connection () {
		if (!$this->connect) {
			$this->connect = ftp_connect($this->ftp['SERVER'], isset($this->ftp['PORT']) ? $this->ftp['PORT'] : 21, 10);
			if (!$this->connect || !@ftp_login($this->connect, $this->ftp['USER'], $this->ftp['PASS'])) {
				throw new InvalidArgumentException('FTP login failed.');
			}
			ftp_pasv($this->connect, true);
		}
		return $this->connect;
	}

	/**
	 * Create directory
	 * @param string $dir
	 * @return bool
	 */
	protected function dirMake ($dir) {
		if (ftp_mkdir($this->connection(), $this->makeFtpPath($dir))) {
			ftp_chmod($this->connection(), self::$mode, $this->makeFtpPath($dir));
			return true;
		}
		return false;
	}

	/**
	 * Delete directory
	 * @param string $dir
	 * @return bool
	 */
	protected function dirDelete ($dir) {
		return ftp_rmdir($this->connection(), $this->makeFtpPath($dir));
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
		$mode = eregi("^text", $this->getMime($dirOld === false ? $fileOld : $this->makeFtpPath($dirOld).$fileOld)) ? FTP_ASCII : FTP_BINARY;
		return ftp_get($this->connection(), $dirOld === false ? $fileOld : $this->makeFtpPath($dirOld).$fileOld, $this->makeFtpPath($dirNew).$fileNew, $mode);
	}

	/**
	 * Delete file
	 * @param string $file
	 * @return bool
	 */
	protected function fileDelete ($dir, $file) {
		return ftp_delete($this->connection(), $this->makeFtpPath($dir).$file);
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
		return ftp_rename($this->connection(), $this->makeFtpPath($dirOld).$fileOld, $this->makeFtpPath($dirNew).$fileNew);
	}
}
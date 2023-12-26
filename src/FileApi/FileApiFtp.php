<?php declare(strict_types=1);

namespace FileApi;

use InvalidArgumentException;
//	FTP\Connection;	support php8+

/**
 * File interaction throughout FTP
 * !!! Do NOT use it - NOT SAFE !!!
 */
class FileApiFtp extends FileApiPhp {
	/** @var array{'SERVER': string, 'USER': string, 'PASS': string, 'DIR': string, 'PORT'?: int} */
	private $ftp;
	/** @var \FTP\Connection|null */
	protected $connect = null;
	/** @var string */
	protected $pathFTP;

	/**
	 * @param string $loc
	 * @param array{'SERVER': string, 'USER': string, 'PASS': string, 'DIR': string, 'PORT'?: int} $ftp
	 */
	public function __construct (string $loc, array $ftp) {
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
	function makeFtpPath (string $dir): string {
		if ($dir === '') return $this->pathFTP;
		if (strpos($dir, $this->path) === 0) $dir = $this->cropPath($dir);
		return $this->pathFTP.(strrpos($dir, DIRECTORY_SEPARATOR) === 0 ? $dir.DIRECTORY_SEPARATOR : $dir);
	}

	/**
	 * Set current path if exists
	 * @param string $dir
	 * @return bool
	 */
	function setPath (string $dir): bool {
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
	function isWritable (string $url): bool {
		return true;
	}

	/**
	 * File upload. Error message is from isUpload
	 * @param string $input
	 * @param int|null $id
	 * @param string $dir
	 * @param string|null $file
	 * @param bool $copy
	 * @return bool
	 */
	public function upload (string $input, ?int $id, string $dir, string $file = null, bool $copy = false): bool {
		if ($this->isUpload($input, $id)) {
			if ($file === null) $file = $this->getUpload($input, 'name', $id);
			if (!$this->isWritable($this->makePath($dir))) $this->error = $this->getMsg('NO_RIGHTS', self::escape($file));
			else {
				$tmpName = $this->getUpload($input, 'tmp_name', $id);
				if ($copy) {
					if ($this->fileCopy(null, $dir, $tmpName, $file)) return true;
				}
				else {
					$mode = preg_match('/^text/i', $this->getUpload($input, 'type', $id)) ? FTP_ASCII : FTP_BINARY;
					if (ftp_put($this->connection(), $this->makeFtpPath($dir).$file, $tmpName, $mode)) return true;
				}
				$this->error = $this->getMsg('GENERAL_ERROR', self::escape($tmpName.', '.$dir.$file));
			}
		}
		return false;
	}

	/**
	 * Initialize ftp connection
	 * @return \FTP\Connection
	 * @throws InvalidArgumentException
	 */
	public function connection () {
		if (!$this->connect) {
			$connect = ftp_connect($this->ftp['SERVER'], $this->ftp['PORT'] ?? 21, 10);
			if (!$connect || !@ftp_login($connect, $this->ftp['USER'], $this->ftp['PASS'])) {
				throw new InvalidArgumentException('FTP login failed.');
			}
			ftp_pasv($connect, true);
			$this->connect = $connect;
		}
		return $this->connect;
	}

	/**
	 * Create directory
	 * @param string $dir
	 * @return bool
	 */
	protected function dirMake (string $dir): bool {
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
	protected function dirDelete (string $dir): bool {
		return ftp_rmdir($this->connection(), $this->makeFtpPath($dir));
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
		$mode = preg_match('/^text/i', $this->getMime($dirOld === null ? $fileOld : $this->makeFtpPath($dirOld).$fileOld)) ? FTP_ASCII : FTP_BINARY;
		return ftp_get($this->connection(), $dirOld === null ? $fileOld : $this->makeFtpPath($dirOld).$fileOld, $this->makeFtpPath($dirNew).$fileNew, $mode);
	}

	/**
	 * Delete file
	 * @param string $dir
	 * @param string $file
	 * @return bool
	 */
	protected function fileDelete (string $dir, string $file): bool {
		return ftp_delete($this->connection(), $this->makeFtpPath($dir).$file);
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
		return ftp_rename($this->connection(), $this->makeFtpPath($dirOld).$fileOld, $this->makeFtpPath($dirNew).$fileNew);
	}
}

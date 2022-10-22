<?php
namespace FileApi;

/**
 * Support class for filename extension and unit conversion
 */
class FileExt {

	/**
	 * Get maximum upload size
	 * @param int $custom
	 * @return int
	 */
	public static function getMaxUpload ($custom = 0) {
		$max = min(self::sizeToNum(ini_get('upload_max_filesize')), self::sizeToNum(ini_get('post_max_size')));
		return $custom !== 0 && $max > $custom ? $custom : $max;
	}

	/**
	 * Convert KB to number
	 * @param string $val
	 * @return int
	 */
	public static function sizeToNum ($val) {
		$val = preg_split('/(?<=\d) *(?=[a-z])/i', $val);
		$num = $val[0];
		if ($val[1]) {
			switch (strtoupper(substr($val[1], 0, 1))) {
				case 'P': $num *= 1024;
				case 'T': $num *= 1024;
				case 'G': $num *= 1024;
				case 'M': $num *= 1024;
				case 'K': $num *= 1024;
			}
		}
		return $num;
	}

	/**
	 * Convert numeric value to KB
	 * @param int $num
	 * @param bool $split
	 * @return array|string
	 */
	public static function numToSize ($num, $split = false) {
		$scale = ['','K','M','G','T','P'];
		$n = 0;
		while ($num >= 1024 && $num = round($num / 1024)) $n++;
		if ($split) return [$num, $scale[$n]];
		$num .= " ".$scale[$n]."B";
		return $num;
	}

	/**
	 * Get file extension
	 * @param string $name
	 * @return string
	 */
	public static function getExt ($name) {
		$dot = mb_strrpos($name, '.');
		if ($dot) return mb_strtolower(mb_substr($name, $dot + 1));
		return '';
	}

	/**
	 * Get fontawesome icon based on file extension
	 * @param string $ext
	 * @return string
	 */
	public static function getIcon ($ext) {
		switch ($ext) {
			case 'pdf': return 'fa-file-pdf';
			case 'txt': return 'fa-file-text';
			case 'svg':
			case 'gif':
			case 'bmp':
			case 'png':
			case 'jpeg':
			case 'jpg': return 'fa-file-image';
			case 'doc':
			case 'docx':
			case 'odt': return 'fa-file-word';
			case 'ods':
			case 'xls':
			case 'xlsx':
			case 'csv': return 'fa-file-excel';
			case '7z':
			case 'gz':
			case 'gzip':
			case 'rar':
			case 'tar':
			case 'zip': return 'fa-file-zipper';
			case 'aac':
			case 'm4a':
			case 'mp3':
			case 'flac':
			case 'opus':
			case 'wav': return 'fa-file-audio';
			case 'avi':
			case 'mp4':
			case 'mkv': return 'fa-file-video';
			case 'js':
			case 'css':
			case 'php': return 'fa-file-code';
		}
		return 'fa-file';
	}
}
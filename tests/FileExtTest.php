<?php
use FileApi\FileExt;

class FileExtTest extends \PHPUnit\Framework\TestCase {

	public function test_basic_getMaxUpload () {
		$this->assertEquals(100, FileExt::getMaxUpload(100));
	}

	public function test_sizeToNum () {
		$this->assertEquals(1024, FileExt::sizeToNum('1KB'));

		$this->assertEquals(10485760, FileExt::sizeToNum('10MB'));

		$this->assertEquals(107374182400, FileExt::sizeToNum('100GB'));

		$this->assertEquals(1099511627776, FileExt::sizeToNum('1 T'));
	}

	public function test_numToSize () {
		$this->assertEquals('1 KB', FileExt::numToSize(1024));

		$this->assertEquals('10 MB', FileExt::numToSize(10485760));

		$this->assertEquals('100 GB', FileExt::numToSize(107374182400));

		$this->assertEquals([1, 'T'], FileExt::numToSize(1099511627776, true));
	}

	public function test_getExt () {
		$this->assertEquals('test', FileExt::getExt('test.test'));

		$this->assertEquals('gif', FileExt::getExt('test.dot.gif'));

		$this->assertEquals('', FileExt::getExt('test'));
	}

	public function test_getIcon () {
		foreach (['gif','jpg','bmp','gif'] as $ext) {
			$this->assertEquals('fa-file-image', FileExt::getIcon($ext));
		}
		foreach (['doc','docx','odt'] as $ext) {
			$this->assertEquals('fa-file-word', FileExt::getIcon($ext));
		}
		foreach (['7z','rar','tar','zip'] as $ext) {
			$this->assertEquals('fa-file-zipper', FileExt::getIcon($ext));
		}
		foreach (['aac','mp3','wav'] as $ext) {
			$this->assertEquals('fa-file-audio', FileExt::getIcon($ext));
		}
		foreach (['avi','mp4','mkv'] as $ext) {
			$this->assertEquals('fa-file-video', FileExt::getIcon($ext));
		}
		foreach (['js','css','php'] as $ext) {
			$this->assertEquals('fa-file-code', FileExt::getIcon($ext));
		}
		$this->assertEquals('fa-file-pdf', FileExt::getIcon('pdf'));
		$this->assertEquals('fa-file-text', FileExt::getIcon('txt'));
		$this->assertEquals('fa-file', FileExt::getIcon('test'));
	}
}
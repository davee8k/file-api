<?php declare(strict_types=1);

use FileApi\Utils;

use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase {

	public function testBasicGetMaxUpload (): void {
		$this->assertEquals(100, Utils::getMaxUpload(100));
	}

	public function testSizeToNum (): void {
		$this->assertEquals(1024, Utils::sizeToNum('1KB'));

		$this->assertEquals(10485760, Utils::sizeToNum('10MB'));

		$this->assertEquals(107374182400, Utils::sizeToNum('100GB'));

		$this->assertEquals(1099511627776, Utils::sizeToNum('1 T'));
	}

	public function testNumToSize (): void {
		$this->assertEquals('1 KB', Utils::numToSize(1024));

		$this->assertEquals('10 MB', Utils::numToSize(10485760));

		$this->assertEquals('100 GB', Utils::numToSize(107374182400));

		$this->assertEquals('1 TB', Utils::numToSize(1099511627776));
	}

	public function testNumToSizeArray (): void {
		$this->assertEquals([1, 'K'], Utils::numToSizeArray(1024));

		$this->assertEquals([10, 'M'], Utils::numToSizeArray(10485760));

		$this->assertEquals([100, 'G'], Utils::numToSizeArray(107374182400));

		$this->assertEquals([1, 'T'], Utils::numToSizeArray(1099511627776));
	}

	public function testGetExt (): void {
		$this->assertEquals('test', Utils::getExt('test.test'));

		$this->assertEquals('gif', Utils::getExt('test.dot.gif'));

		$this->assertEquals('', Utils::getExt('test'));
	}

	public function testGetIcon (): void {
		foreach (['gif','jpg','bmp','gif'] as $ext) {
			$this->assertEquals('fa-file-image', Utils::getIcon($ext));
		}
		foreach (['doc','docx','odt'] as $ext) {
			$this->assertEquals('fa-file-word', Utils::getIcon($ext));
		}
		foreach (['7z','rar','tar','zip'] as $ext) {
			$this->assertEquals('fa-file-zipper', Utils::getIcon($ext));
		}
		foreach (['aac','mp3','wav'] as $ext) {
			$this->assertEquals('fa-file-audio', Utils::getIcon($ext));
		}
		foreach (['avi','mp4','mkv'] as $ext) {
			$this->assertEquals('fa-file-video', Utils::getIcon($ext));
		}
		foreach (['js','css','php'] as $ext) {
			$this->assertEquals('fa-file-code', Utils::getIcon($ext));
		}
		$this->assertEquals('fa-file-pdf', Utils::getIcon('pdf'));
		$this->assertEquals('fa-file-text', Utils::getIcon('txt'));
		$this->assertEquals('fa-file', Utils::getIcon('test'));
	}
}

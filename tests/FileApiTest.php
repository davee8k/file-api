<?php declare(strict_types=1);

use FileApi\FileApi,
	FileApi\FileApiPhp,
	FileApi\FileApiFtp;

/**
 * Test are build for Linux filesystems (some test checking access rights)
 */
class FileApiTest extends \PHPUnit\Framework\TestCase {

	private const DIR_SRC = 'source/';
	private const DIR_UPLOAD = 'upload/';

	/*private $ftp = [
		'SERVER' => '127.0.0.1',
		'USER' => 'user',
		'PASS' => 'password',
		'DIR' => DIR_TEST_TARGET
	];*/

	/**
	 * @return array<string, FileApi[]>
	 */
	public function apiBase (): array {
		return ['Base' => [ new FileApi(DIR_TEST_TARGET) ]];
	}

	/**
	 * @return array<string, FileApi[]>
	 */
	public function apiPhp (): array {
		return ['Php' => [ new FileApiPhp(DIR_TEST_TARGET) ]];
	}

	/**
	 * Fake FTP to be possible test it without ftp access
	 * @return array<string, FileApi[]>
	 */
	public function apiFtp (): array {
		if (!empty($this->ftp)) return ['Ftp' => [ new FileApiFtp(DIR_TEST_TARGET, $this->ftp) ]];
		return ['Php' => [ new FileApiPhp(DIR_TEST_TARGET) ]];
	}

	public function testValidateInitPath (): void {
		new FileApi(DIR_TEST_TARGET);
		$this->expectException('InvalidArgumentException');
		$this->expectExceptionMessage('Directory &#039;/fail/&#039; not found.');
		new FileApi('/fail/');
	}

	/**
	 *
	 * @dataProvider apiBase
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testGetUrlData (FileApi $api): void {
		$this->assertEquals('User-agent: *
Disallow:
Sitemap: /sitemap.xml', $api->getUrlData('http://localhost/0_BaseAdmin/robots.txt'));

		$this->assertNull($api->getUrlData('http://localhost/failtoload'));
		$this->assertEquals($api->getError(), 'Curl error: 404');
	}

	/**
	 *
	 * @dataProvider apiBase
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testSetPath (FileApi $api): void {
		$this->assertTrue($api->setPath('upload'));
		$this->assertEquals(DIR_TEST_TARGET.self::DIR_UPLOAD, $api->getPath());
		$this->assertTrue($api->setPath(self::DIR_UPLOAD));
		$this->assertEquals(DIR_TEST_TARGET.self::DIR_UPLOAD, $api->getPath());
		$this->assertFalse($api->setPath('fail/'));
		$this->assertEquals(DIR_TEST_TARGET.self::DIR_UPLOAD, $api->getPath());
	}

	/**
	 *
	 * @dataProvider apiBase
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testFilterPath (FileApi $api): void {
		$this->assertEquals('test/test/', $api->filterPath('test/../test/'));
		$this->assertEquals('/test/test/', $api->filterPath('../test/test/'));
		$this->assertEquals('test/test/', $api->filterPath('test/test/../'));
		$this->assertEquals('test/..x/test/', $api->filterPath('test/..x/test/'));
		$this->assertEquals('..x/test/', $api->filterPath('..x/test/'));
	}

	/**
	 *
	 * @dataProvider apiBase
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testMakePath (FileApi $api): void {
		$this->assertEquals(DIR_TEST_TARGET, $api->makePath(''));
		$this->assertEquals(DIR_TEST_TARGET.'tester/', $api->makePath('./tester'));
		$this->assertEquals(DIR_TEST_TARGET.'tester/', $api->makePath('tester'));
		$this->assertEquals(DIR_TEST_TARGET.'tester/', $api->makePath(DIR_TEST_TARGET.'tester'));
		$this->assertEquals(DIR_TEST_TARGET.'tester/', $api->makePath(DIR_TEST_TARGET.'tester/'));
	}

	/**
	 *
	 * @dataProvider apiBase
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testFileSize (FileApi $api): void {
		$this->assertEquals(79, $api->getSize(self::DIR_UPLOAD.'fail/test.gif'));
	}

	/**
	 *
	 * @dataProvider apiBase
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testMime (FileApi $api): void {
		$this->assertEquals('image/gif', $api->getMime(self::DIR_UPLOAD.'fail/test.gif'));
	}

	/**
	 *
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testDirAction (FileApi $api): void {
		$this->assertTrue($api->createDir('tester'));
		$this->assertTrue($api->delete('tester'));

		$this->assertFalse($api->createDir('upload/fail/fail'));
		$this->assertEquals('You do not have write rights to: &#039;'.DIR_TEST_TARGET.'upload/fail/&#039;.', $api->getError());
		unset($api);
	}

	/**
	 *
	 * @dataProvider apiBase
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testLoadFiles (FileApi $api): void {
		$this->assertEmpty($api->loadFiles(self::DIR_SRC.'empty/'));

		$this->assertEquals(1, count($api->loadFiles('upload/fail/')));
	}

	/**
	 *
	 * @dataProvider apiBase
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testIsUploadOk (FileApi $api): void {
		$_FILES = [
			'test' => [
				'name' => [ 0 => 'test.gif' ],
				'type' => [ 0 => 'image/gif' ],
				'size' => [ 0 => 79 ],
				'tmp_name' => [ 0 => DIR_TEST_TARGET.self::DIR_SRC.'test.gif' ],
				'error' => [ 0 => 0 ]
			]
		];

		$this->assertTrue($api->isUpload('test', 0));

		$_FILES['test']['error'][0] = 1;
		$this->assertFalse($api->isUpload('test', 0));
		$this->assertEquals('File &#039;test.gif&#039; has exceeded the maximum size.', $api->getError());

		$_FILES['test']['error'][0] = 2;
		$this->assertFalse($api->isUpload('test', 0));
		$this->assertEquals('File &#039;test.gif&#039; has exceeded the maximum size.', $api->getError());

		$_FILES['test']['error'][0] = 3;
		$this->assertFalse($api->isUpload('test', 0));
		$this->assertEquals('Upload of file &#039;test.gif&#039; has been stopped.', $api->getError());

		$_FILES['test']['error'][0] = 4;
		$this->assertTrue($api->isUpload('test', 0, true));
		$this->assertFalse($api->isUpload('test', 0));
		$this->assertEquals('No file to upload was specified.', $api->getError());

		$_FILES['test']['error'][0] = 5;
		$this->assertFalse($api->isUpload('test', 0));
		$this->assertEquals('Unable to upload file &#039;test.gif&#039; (error 5).', $api->getError());
	}

	/**
	 *
	 * @dataProvider apiBase
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testUploadClone (FileApi $api): void {
		$_FILES['from'] = [
			'name' => 'test.gif',
			'type' => 'image/gif',
			'size' => 79,
			'tmp_name' => DIR_TEST_TARGET.self::DIR_SRC.'test.gif',
			'error' => 0
		];

		$this->assertTrue($api->clonePost('from', null, 'to', null));
		$this->assertEquals($_FILES['from'], $_FILES['to']);
		unset($_FILES);

		$_FILES['subfrom'] = [
			'name' => [ 0 => 'test.gif' ],
			'type' => [ 0 => 'image/gif' ],
			'size' => [ 0 => 79 ],
			'tmp_name' => [ 0 => DIR_TEST_TARGET.self::DIR_SRC.'test.gif' ],
			'error' => [ 0 => 0 ]
		];

		$this->assertTrue($api->clonePost('subfrom', 0, 'subto', 0));
		$this->assertEquals($_FILES['subfrom'], $_FILES['subto']);
		unset($_FILES);

		$_FILES['subfrom'] = [
			'name' => [ 0 => 'test.gif' ],
			'type' => [ 0 => 'image/gif' ],
			'size' => [ 0 => 79 ],
			'tmp_name' => [ 0 => DIR_TEST_TARGET.self::DIR_SRC.'test.gif' ],
			'error' => [ 0 => 0 ]
		];

		$outData = [
			'name' => [ 1 => [ 0 => 'test.gif' ] ],
			'type' => [ 1 => [ 0 => 'image/gif' ] ],
			'size' => [ 1 => [ 0 => 79 ] ],
			'tmp_name' => [ 1 => [ 0 => DIR_TEST_TARGET.self::DIR_SRC.'test.gif' ] ],
			'error' => [ 1 => [ 0 => 0 ] ]
		];

		$this->assertTrue($api->clonePost('subfrom', 0, 'subto', [1, 0]));
		$this->assertEquals($_FILES['subto'], $outData);
	}

	/**
	 *
	 * @dataProvider apiBase
	 */
	public function testFakeUpload (FileApi $api): void {
		$expect = [
			'name' => 'test.gif',
			'type' => 'image/gif',
			'size' => 79,
			'tmp_name' => DIR_TEST_TARGET.self::DIR_SRC.'test.gif',
			'error' => 0
		];

		$api->fakeUpload('fake', $expect['tmp_name'], $expect['name']);

		$this->assertEquals($_FILES['fake'], $expect);

		$api->fakeUpload('fake2', $expect['tmp_name'], $expect['name'], 1);

		$this->assertEquals($_FILES['fake2']['name'][1], $expect['name']);
		$this->assertEquals($_FILES['fake2']['tmp_name'][1], $expect['tmp_name']);
	}

	/**
	 *
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testCreateFile (FileApi $api): void {
		$this->assertFalse($api->createFile('upload/fail/', 'test.gif', 'test'));
		$this->assertEquals("File &#039;test.gif&#039; already exists.", $api->getError());

		$this->assertFalse($api->createFile('upload/fail/', 'test2.gif', 'test'));
		$this->assertEquals('You do not have write rights to: &#039;'.DIR_TEST_TARGET.'upload/fail/&#039;.', $api->getError());

		$this->assertFalse($api->createFile('upload/fail/fail/', 'test2.gif', 'test'));
		$this->assertEquals('You do not have write rights to: &#039;'.DIR_TEST_TARGET.'upload/fail/&#039;.', $api->getError());

		$this->assertTrue($api->createFile(self::DIR_UPLOAD, 'test.txt', 'test'));
		$this->assertTrue(unlink(DIR_TEST_TARGET.'upload/test.txt'));
	}

	/**
	 *
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testDeleteFail (FileApi $api): void {
		$this->assertFalse($api->delete('upload/fail/'));
		$this->assertEquals($api->getError(), 'Directory &#039;upload/fail/&#039; is not empty.');

		$this->assertFalse($api->delete('upload/fail/','test.gif'));
		$this->assertEquals($api->getError(), 'File &#039;test.gif&#039; cannot be deleted.');
	}

	/**
	 *
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testRename (FileApi $api): void {
		$this->assertTrue($api->createDir('test'));
		$this->assertTrue($api->move('test', 'testb'));
		$this->assertFalse($api->exist('test'));
		$this->assertTrue($api->exist('testb'));
		$this->assertTrue($api->delete('testb'));

		$this->assertTrue($api->createFile('', 'test.txt', "blabla"));
		$this->assertTrue($api->rename('', 'test.txt', 'testb.txt'));
		$this->assertTrue($api->exist('', 'testb.txt'));
		$this->assertFalse($api->exist('', 'test.txt'));
		$this->assertTrue($api->delete('', 'testb.txt'));
		$this->assertFalse($api->exist('', 'testb.txt'));
	}

	/**
	 *
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testRenameFail (FileApi $api): void {
		$this->assertFalse($api->rename('test', 'test', ''));
		$this->assertEquals($api->getError(), 'No new name was entered.');

		$this->assertFalse($api->rename('failto', 'failto', 'nonexistent'));
		$this->assertEquals($api->getError(), 'File &#039;failto&#039; not found.');

		$this->assertFalse($api->rename('upload/fail/', 'test.gif', 'fail.gif'));
		$this->assertEquals($api->getError(), 'File &#039;test.gif&#039; cannot be renamed.');
	}

	/**
	 *
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testMove (FileApi $api): void {
		$this->assertTrue($api->createDir('test'));
		$this->assertTrue($api->move('test', 'testb'));
		$this->assertFalse($api->exist('test'));
		$this->assertTrue($api->exist('testb'));
		$this->assertTrue($api->delete('testb'));
		$this->assertFalse($api->exist('testb'));

		$this->assertTrue($api->createFile('', 'test.txt', "blabla"));
		$this->assertTrue($api->move('', self::DIR_UPLOAD, 'test.txt'));
		$this->assertFalse($api->exist('', 'test.txt'));
		$this->assertTrue($api->exist(self::DIR_UPLOAD, 'test.txt'));
		$this->assertTrue($api->delete(self::DIR_UPLOAD, 'test.txt'));
		$this->assertFalse($api->exist(self::DIR_UPLOAD, 'test.txt'));
	}

	/**
	 *
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testMoveFails (FileApi $api): void {
		$this->assertFalse($api->move('', self::DIR_UPLOAD, 'failto'));
		$this->assertEquals($api->getError(), 'File &#039;failto&#039; not found.');

		$this->assertFalse($api->move(self::DIR_SRC, 'upload/fail/', 'test.gif'));
		$this->assertEquals($api->getError(), 'File &#039;test.gif&#039; already exists.');

		$this->assertFalse($api->move(self::DIR_UPLOAD, 'upload'));
		$this->assertEquals($api->getError(), 'Directory &#039;upload&#039; already exists.');
	}

	/**
	 *
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testCopy (FileApi $api): void {
		$this->assertTrue($api->createDir('test'));
		$this->assertTrue($api->copy('test', 'testb'));
		$this->assertTrue($api->exist('test'));
		$this->assertTrue($api->exist('testb'));
		$this->assertTrue($api->delete('test'));
		$this->assertTrue($api->delete('testb'));
		$this->assertFalse($api->exist('test'));
		$this->assertFalse($api->exist('testb'));

		$this->assertTrue($api->createFile('', 'test.txt', "blabla"));
		$this->assertTrue($api->copy('', self::DIR_UPLOAD, 'test.txt'));
		$this->assertTrue($api->exist('', 'test.txt'));
		$this->assertTrue($api->exist(self::DIR_UPLOAD, 'test.txt'));
		$this->assertTrue($api->delete('', 'test.txt'));
		$this->assertTrue($api->delete(self::DIR_UPLOAD, 'test.txt'));
		$this->assertFalse($api->exist('', 'test.txt'));
		$this->assertFalse($api->exist(self::DIR_UPLOAD, 'test.txt'));
	}

	/**
	 *
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testCopyFail (FileApi $api): void {
		$this->assertFalse($api->copy('', self::DIR_UPLOAD, 'failto'));
		$this->assertEquals($api->getError(), 'File &#039;failto&#039; not found.');

		$this->assertFalse($api->copy(self::DIR_SRC, 'upload/fail/', 'test.gif'));
		$this->assertEquals($api->getError(), 'File &#039;test.gif&#039; already exists.');

		$this->assertFalse($api->copy(self::DIR_UPLOAD, 'upload'));
		$this->assertEquals($api->getError(), 'Directory &#039;upload&#039; already exists.');
	}

	/**
	 * Require case sensitive OS
	 * @dataProvider apiBase
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testExistCaseInsensitive (FileApi $api): void {
		// dir
		$api->setPath(self::DIR_SRC);
		$this->assertFalse($api->exist('CASE', null, false));
		$this->assertTrue($api->exist('CASE', null, true));

		$this->assertTrue($api->exist('case/a', null, true));
		$this->assertTrue($api->exist('case/A', null, true));

		$this->assertFalse($api->exist('case/ABCĎ', null, false));
		$this->assertTrue($api->exist('case/Abcď', null, false));
		$this->assertTrue($api->exist('case/Abcď', null, true));
		$this->assertTrue($api->exist('case/ABCĎ', null, true));
		$this->assertFalse($api->exist('case/ABCĎe', null, true));

		// file
		$this->assertFalse($api->exist('case/', 'Alfa.TXT', false));
		$this->assertTrue($api->exist('case/', 'Alfa.TXT', true));
	}

	/**
	 *
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testUpload (FileApi $api): void {
		$_FILES['from'] = [
			'name' => [ 0 => 'mask_01.gif' ],
			'type' => [ 0 => 'image/gif' ],
			'size' => [ 0 => 79 ],
			'tmp_name' => [ 0 => DIR_TEST_TARGET.self::DIR_SRC.'test.gif' ],
			'error' => [ 0 => 3 ]
		];

		$this->assertFalse($api->upload('from', 0, self::DIR_UPLOAD));
		$this->assertEquals($api->getError(), 'Upload of file &#039;mask_01.gif&#039; has been stopped.');

		$_FILES['from']['error'][0] = 0;
		$this->assertTrue($api->upload('from', 0, self::DIR_UPLOAD, 'target.gif', true));
		$this->assertTrue($api->delete(self::DIR_UPLOAD, 'target.gif'));
		$this->assertFalse($api->exist(self::DIR_UPLOAD, 'target.gif'));

		$_FILES['from']['tmp_name'][0] = '/fail/none.gif';
		$this->assertfalse($api->upload('from', 0, self::DIR_UPLOAD, 'target.gif', true));
		$this->assertEquals($api->getError(), 'General error: &#039;/fail/none.gif, upload/target.gif&#039;');
	}

	public function testFtpFail (): void {
		if (!empty($this->ftp)) {
			$api = new FileApiFtp(DIR_TEST_TARGET, $this->ftp);
			unset($api);
		}

		$api = new FileApiFtp(DIR_TEST_TARGET, ['SERVER' => '127.0.0.1', 'USER' => 'failed_user', 'PASS' => 'no', 'DIR'=>'/']);
		$this->expectException('InvalidArgumentException');
		$this->expectExceptionMessage('FTP login failed.');
		$api->connection();
	}

	/**
	 *
	 * @dataProvider apiBase
	 * @dataProvider apiPhp
	 * @dataProvider apiFtp
	 */
	public function testDownload (FileApi $api): void {
		$this->assertFalse($api->downloadFile('error', 'error'));
	}
}

<?php

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase;
use Cosninix\Cos\Cos;

class LetsTest extends TestCase {

	/**
	 * test the base class
	 */
	public function testIfset() {
		$v='value';
		$result=Cos::ifset($v, "default");
		$this->assertEquals('value', $result);

		$result=Cos::ifset($notdefined, "default");
		$this->assertEquals('default', $result);

	}

	public function testSanitize() {
		$v='simple string';
		$v2=Cos::sanitizeString($v);
		$this->assertEquals($v, $v2);

		$v="simple valid string \t\r\n";
		$v2=Cos::sanitizeString($v);
		$this->assertEquals($v, $v2);

		$v="A strange string to pass, maybe with some ø, æ, å characters.";
		$v2=Cos::sanitizeString($v);
		$this->assertEquals($v, $v2);

		$v="\xc2\xb8 \xC3\xB8 \xc3\xa6 \xD0\x94 \xe0\xa0\x86 \xe2\x96\x80 \xea\xa8\x86 	\xf0\x90\x80\x80 \xf0\x9f\xa9\xb3";
		$v2=Cos::sanitizeString($v);
		$this->assertEquals($v, $v2, " cedille o-stroke ae cyrillic-D Samaritan-zen block cham-ka LINEAR-B-SYLLABLE-B008-A Shorts");

		$v="illegal einding \xf0";
		$v2=Cos::sanitizeString($v);
		$this->assertEquals("illegal einding ", $v2);

		$v="illegal controls\x00\x01\x02\x1f\x0a\x0d\x09\e";
		$v2=Cos::sanitizeString($v);
		$this->assertEquals("illegal controls\n\r\t\e", $v2);

		$v="illegal utf \xf0!!\x81@@\xc2##\xe0$$\xe0%%\xff^^ in the middle";	// note: x81 and xff are not considered utf8 sequences so the next character remains
		$v2=Cos::sanitizeString($v);
		$this->assertEquals("illegal utf !@@#$%^^ in the middle", $v2);

		$v="illegal utf 2 \xe0\xa0!!\xf0\x9f\xa9## in the middle";
		$v2=Cos::sanitizeString($v);
		$this->assertEquals("illegal utf 2 !# in the middle", $v2);
	}

	/**
	 * Call the helpers that go through Laravel facades, so a missing import fails on every Laravel version
	 */
	public function testLaravelFacades() {
		// Crypt
		$this->assertEquals('1234', Cos::decodeId(Cos::encodeId(1234)));
		$this->assertFalse(Cos::decodeId(Cos::encodeId(1234, time() - 10)));

		// Request, Str, Config and App
		$this->app['config']->set('app.available_languages', ['nl', 'en']);
		$this->app['request']->headers->set('Accept-Language', 'NL-nl,en;q=0.8');
		$this->assertTrue(Cos::setBrowserLanguage());
		$this->assertEquals('nl', $this->app->getLocale());

		// URL
		$this->assertEquals('http://localhost/img/x.png', Cos::path2url('/var/www/site/public/img/x.png'));

		// DB and Closure
		$this->app['config']->set('database.connections.cos_test', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
		$this->app['config']->set('database.default', 'cos_test');
		$this->assertEquals(42, Cos::safeTransaction(function () {
			return 42;
		}));
	}

	public function testPersonalizeText() {
		$this->assertEquals('Hello Ronald, not found', Cos::personalizeText('Hello {{ name }}, {{other}}', ['name' => 'Ronald']));
	}

	/**
	 * Null input must not trigger the PHP 8.1+ "Passing null to parameter" deprecations
	 */
	public function testNullInput() {
		$deprecations = [];
		set_error_handler(function ($no, $msg, $file, $line) use (&$deprecations) {
			$deprecations[] = "$msg @ ".basename($file).":$line";
			return true;
		}, E_DEPRECATED);
		try {
			$this->assertSame('', Cos::sanitizeString(null));
			$this->assertSame("''", Cos::muq(null));
			$this->assertSame('', Cos::uq(null));
			$this->assertSame('', Cos::html_escape(null));
			$this->assertSame('', Cos::supertrim(null));
			$this->assertSame('', Cos::slug(null));
			$this->assertSame('', Cos::trimcase(null));
			$this->assertFalse(Cos::isUrl(null));
			$this->assertFalse(Cos::url2path(null));
			$this->assertSame('', Cos::path2url(null));
			$this->assertSame('', Cos::urlencode(null));
			$this->assertSame('', Cos::base64url_encode(null));
			$this->assertSame('', Cos::base64url_decode(null));
			$this->assertNull(Cos::parseJson(null));
			$this->assertSame(0, Cos::percDiffString(null, 0));
			$this->assertSame(0, Cos::percString(1, null));
			$this->app['request']->headers->remove('Accept-Language');
			$this->assertFalse(Cos::setBrowserLanguage());
		} finally {
			restore_error_handler();
		}
		$this->assertSame([], $deprecations);
	}

	public function testHtmlEscape() {
		$this->assertEquals('&lt;b&gt;', Cos::html_escape('<b>'));
		$this->assertEquals(['&amp;', '&#039;'], Cos::html_escape(['&', "'"]));
	}
}

<?php

namespace MichaelHall\HtmlValidatorPlugin\Tests;

use BlueMvc\Core\Http\StatusCode;
use BlueMvc\Core\Route;
use BlueMvc\Fakes\FakeApplication;
use BlueMvc\Fakes\FakeRequest;
use BlueMvc\Fakes\FakeResponse;
use DataTypes\FilePath;
use MichaelHall\HtmlValidatorPlugin\HtmlValidatorPlugin;
use MichaelHall\HtmlValidatorPlugin\Tests\Helpers\TestController;
use PHPUnit\Framework\TestCase;

/**
 * Test HtmlValidatorPlugin class.
 */
class HtmlValidatorPluginTest extends TestCase
{
    /**
     * Test not cached valid content.
     */
    public function testNotCachedValidContent()
    {
        $this->clearPluginCache();

        $this->myApplication->addPlugin(new HtmlValidatorPlugin());
        $request = new FakeRequest('/valid');
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame(StatusCode::OK, $response->getStatusCode()->getCode());
        self::assertSame('success', $response->getHeader('X-Html-Validator-Plugin'));
        self::assertSame("<!DOCTYPE html>\r\n<html lang=\"en\">\r<head>\n<title>A valid test page</title></head>\n\n</html>", $response->getContent());
    }

    /**
     * Test cached valid content.
     *
     * @depends testNotCachedValidContent
     */
    public function testCachedValidContent()
    {
        $this->myApplication->addPlugin(new HtmlValidatorPlugin());
        $request = new FakeRequest('/valid');
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame(StatusCode::OK, $response->getStatusCode()->getCode());
        self::assertSame('success; from-cache', $response->getHeader('X-Html-Validator-Plugin'));
        self::assertSame("<!DOCTYPE html>\r\n<html lang=\"en\">\r<head>\n<title>A valid test page</title></head>\n\n</html>", $response->getContent());
    }

    /**
     * Test expired cached valid content.
     *
     * @depends testCachedValidContent
     */
    public function testExpiredCachedValidContent()
    {
        // Make the cached result old.
        $cacheFile = $this->myApplication->getTempPath()->withFilePath(FilePath::parse('michaelhall/html-validator-plugin/cce8c0eb532e3346f352c7330b92f3baa186d829.json'));
        touch($cacheFile->__toString(), time() - 86401);

        $this->myApplication->addPlugin(new HtmlValidatorPlugin());
        $request = new FakeRequest('/valid');
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame(StatusCode::OK, $response->getStatusCode()->getCode());
        self::assertSame('success', $response->getHeader('X-Html-Validator-Plugin'));
        self::assertSame("<!DOCTYPE html>\r\n<html lang=\"en\">\r<head>\n<title>A valid test page</title></head>\n\n</html>", $response->getContent());
    }

    /**
     * Test not cached invalid content.
     */
    public function testNotCachedInvalidContent()
    {
        $this->clearPluginCache();

        $this->myApplication->addPlugin(new HtmlValidatorPlugin());
        $request = new FakeRequest('/invalid');
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame(StatusCode::INTERNAL_SERVER_ERROR, $response->getStatusCode()->getCode());
        self::assertSame('fail', $response->getHeader('X-Html-Validator-Plugin'));
        self::assertNotContains('<!DOCTYPE html><html><head></head><body><p>An invalid page.</p></body>', $response->getContent());
        self::assertContains('<h1>HTML validation failed</h1>', $response->getContent());
        self::assertContains('<li>error: line 4: Element &ldquo;head&rdquo; is missing a required instance of child element &ldquo;title&rdquo;.</li>', $response->getContent());
        self::assertContains('<h2>Source</h2><pre>  1 &lt;!DOCTYPE html&gt;<br />  2 &lt;html&gt;<br />  3 &lt;head&gt;<br />  4 &lt;/head&gt;&lt;body&gt;&lt;p&gt;An invalid page.&lt;/p&gt;<br />  5 <br />  6 &lt;/body&gt;<br /></pre>', $response->getContent());
    }

    /**
     * Test cached invalid content.
     *
     * @depends testNotCachedValidContent
     */
    public function testCachedInvalidContent()
    {
        $this->myApplication->addPlugin(new HtmlValidatorPlugin());
        $request = new FakeRequest('/invalid');
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame(StatusCode::INTERNAL_SERVER_ERROR, $response->getStatusCode()->getCode());
        self::assertSame('fail; from-cache', $response->getHeader('X-Html-Validator-Plugin'));
        self::assertNotContains('<!DOCTYPE html><html><head></head><body><p>An invalid page.</p></body>', $response->getContent());
        self::assertContains('<h1>HTML validation failed</h1>', $response->getContent());
        self::assertContains('<li>error: line 4: Element &ldquo;head&rdquo; is missing a required instance of child element &ldquo;title&rdquo;.</li>', $response->getContent());
        self::assertContains('<h2>Source</h2><pre>  1 &lt;!DOCTYPE html&gt;<br />  2 &lt;html&gt;<br />  3 &lt;head&gt;<br />  4 &lt;/head&gt;&lt;body&gt;&lt;p&gt;An invalid page.&lt;/p&gt;<br />  5 <br />  6 &lt;/body&gt;<br /></pre>', $response->getContent());
    }

    /**
     * Test empty content.
     */
    public function testEmptyContent()
    {
        $this->myApplication->addPlugin(new HtmlValidatorPlugin());
        $request = new FakeRequest('/empty');
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame(StatusCode::OK, $response->getStatusCode()->getCode());
        self::assertSame('ignored; empty-content', $response->getHeader('X-Html-Validator-Plugin'));
        self::assertSame('', $response->getContent());
    }

    /**
     * Test not-html content.
     */
    public function testNotHtmlContent()
    {
        $this->myApplication->addPlugin(new HtmlValidatorPlugin());
        $request = new FakeRequest('/notHtml');
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame(StatusCode::OK, $response->getStatusCode()->getCode());
        self::assertSame('ignored; not-html', $response->getHeader('X-Html-Validator-Plugin'));
        self::assertSame('{"Foo": "Bar"}', $response->getContent());
    }

    /**
     * Test with ignored paths.
     *
     * @dataProvider withIgnoredPathsDataProvider
     *
     * @param string $requestPath                        The request path.
     * @param string $expectedXHtmlValidatorPluginHeader The expected X-Html-Validator-Plugin header.
     */
    public function testWithIgnoredPaths($requestPath, $expectedXHtmlValidatorPluginHeader)
    {
        $htmlValidatorPlugin = new HtmlValidatorPlugin();
        $htmlValidatorPlugin->addIgnorePath('foo/');
        $htmlValidatorPlugin->addIgnorePath('/bar/baz');
        $htmlValidatorPlugin->addIgnorePath('bar/baz/foo/');

        $this->myApplication->addPlugin($htmlValidatorPlugin);
        $request = new FakeRequest($requestPath);
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame($expectedXHtmlValidatorPluginHeader, $response->getHeader('X-Html-Validator-Plugin'));
    }

    /**
     * Data provider for ignored paths test.
     *
     * @return array The data.
     */
    public function withIgnoredPathsDataProvider()
    {
        return [
            ['/foo', 'ignored; empty-content'],
            ['/foo/', 'ignored; ignore-path=/foo/'],
            ['/foo/bar', 'ignored; ignore-path=/foo/'],
            ['/foo/bar/', 'ignored; ignore-path=/foo/'],
            ['/bar', 'ignored; empty-content'],
            ['/bar/', 'ignored; empty-content'],
            ['/bar/foo', 'ignored; empty-content'],
            ['/bar/foo/', 'ignored; empty-content'],
            ['/bar/baz', 'ignored; ignore-path=/bar/baz'],
            ['/bar/baz/', 'ignored; empty-content'],
            ['/bar/baz/foo', 'ignored; empty-content'],
            ['/bar/baz/foo/', 'ignored; ignore-path=/bar/baz/foo/'],
            ['/bar/baz/foo/bar', 'ignored; ignore-path=/bar/baz/foo/'],
        ];
    }

    /**
     * Test addIgnorePath with invalid parameter type.
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The $ignorePath parameter is not a string.
     */
    public function testAddIgnorePathWithInvalidParameterType()
    {
        $plugin = new HtmlValidatorPlugin();
        $plugin->addIgnorePath(true);
    }

    /**
     * Test with failed result from validator.
     */
    public function testWithFailedResultFromValidator()
    {
        $this->clearPluginCache();

        $this->myApplication->addPlugin(new HtmlValidatorPlugin('http://localhost:123/'));
        $request = new FakeRequest('/valid');
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame(StatusCode::INTERNAL_SERVER_ERROR, $response->getStatusCode()->getCode());
        self::assertSame('fail', $response->getHeader('X-Html-Validator-Plugin'));
        self::assertNotContains("<!DOCTYPE html>\r\n<html>\r<head>\n<title>A valid test page</title></head>\n\n</html>", $response->getContent());
        self::assertContains('<h1>HTML validation failed</h1>', $response->getContent());
        self::assertContains('<li>error: line 0: Error contacting validator.</li>', $response->getContent());
        self::assertContains('<h2>Source</h2><pre>  1 &lt;!DOCTYPE html&gt;<br />  2 &lt;html lang=&quot;en&quot;&gt;<br />  3 &lt;head&gt;<br />  4 &lt;title&gt;A valid test page&lt;/title&gt;&lt;/head&gt;<br />  5 <br />  6 &lt;/html&gt;<br /></pre>', $response->getContent());
    }

    /**
     * Test constructor with invalid parameter type.
     *
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The $validatorUrl parameter is not a string.
     */
    public function testConstructorWithInvalidParameterType()
    {
        new HtmlValidatorPlugin(100);
    }

    /**
     * Test invalid content in release mode.
     */
    public function testInvalidContentInReleaseMode()
    {
        $this->myApplication->setDebug(false);
        $this->myApplication->addPlugin(new HtmlValidatorPlugin());
        $request = new FakeRequest('/invalid');
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame(StatusCode::OK, $response->getStatusCode()->getCode());
        self::assertNull($response->getHeader('X-Html-Validator-Plugin'));
        self::assertSame("<!DOCTYPE html>\r\n<html>\r<head>\n</head><body><p>An invalid page.</p>\n\n</body>", $response->getContent());
    }

    /**
     * Test invalid content in release mode with enable in release mode set.
     */
    public function testInvalidContentInReleaseModeWithEnableInReleaseModeSet()
    {
        $htmlValidatorPlugin = new HtmlValidatorPlugin();
        $htmlValidatorPlugin->enableInReleaseMode();

        $this->myApplication->setDebug(false);
        $this->myApplication->addPlugin($htmlValidatorPlugin);
        $request = new FakeRequest('/invalid');
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame(StatusCode::INTERNAL_SERVER_ERROR, $response->getStatusCode()->getCode());
        self::assertContains('fail', $response->getHeader('X-Html-Validator-Plugin'));
        self::assertNotContains('<p>An invalid page.</p>', $response->getContent());
        self::assertContains('<h1>HTML validation failed</h1>', $response->getContent());
        self::assertContains('<li>error: line 4: Element &ldquo;head&rdquo; is missing a required instance of child element &ldquo;title&rdquo;.</li>', $response->getContent());
        self::assertContains('<h2>Source</h2><pre>  1 &lt;!DOCTYPE html&gt;<br />  2 &lt;html&gt;<br />  3 &lt;head&gt;<br />  4 &lt;/head&gt;&lt;body&gt;&lt;p&gt;An invalid page.&lt;/p&gt;<br />  5 <br />  6 &lt;/body&gt;<br /></pre>', $response->getContent());
    }

    /**
     * Set up.
     */
    public function setUp()
    {
        $this->myApplication = new FakeApplication();
        $this->myApplication->setDebug(true);
        $this->myApplication->addRoute(new Route('', TestController::class));
    }

    /**
     * Tear down.
     */
    public function tearDown()
    {
        $this->myApplication = null;
    }

    /**
     * Clears the plugin cache.
     */
    private function clearPluginCache()
    {
        $cacheDirectory = $this->myApplication->getTempPath()->withFilePath(FilePath::parse('michaelhall/html-validator-plugin/'));
        if (!is_dir($cacheDirectory->__toString())) {
            return;
        }

        foreach (scandir($cacheDirectory->__toString()) as $cacheFile) {
            if ($cacheFile === '.' || $cacheFile === '..' || substr($cacheFile, -5) !== '.json') {
                continue;
            }

            unlink($cacheDirectory->__toString() . $cacheFile);
        }
        rmdir($cacheDirectory->__toString());
    }

    /**
     * @var FakeApplication My application.
     */
    private $myApplication;
}

<?php

namespace MichaelHall\HtmlValidatorPlugin\Tests;

use BlueMvc\Core\Http\StatusCode;
use BlueMvc\Core\Interfaces\ApplicationInterface;
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

        $request = new FakeRequest('/valid');
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame(StatusCode::OK, $response->getStatusCode()->getCode());
        self::assertSame('success', $response->getHeader('X-Html-Validator-Plugin'));
        self::assertSame("<!DOCTYPE html>\r\n<html>\r<head>\n<title>A valid test page</title></head>\n\n</html>", $response->getContent());
    }

    /**
     * Test cached valid content.
     *
     * @depends testNotCachedValidContent
     */
    public function testCachedValidContent()
    {
        $request = new FakeRequest('/valid');
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame(StatusCode::OK, $response->getStatusCode()->getCode());
        self::assertSame('success; from-cache', $response->getHeader('X-Html-Validator-Plugin'));
        self::assertSame("<!DOCTYPE html>\r\n<html>\r<head>\n<title>A valid test page</title></head>\n\n</html>", $response->getContent());
    }

    /**
     * Test expired cached valid content.
     *
     * @depends testCachedValidContent
     */
    public function testExpiredCachedValidContent()
    {
        // Make the cached result old.
        $cacheFile = $this->myApplication->getTempPath()->withFilePath(FilePath::parse('michaelhall/html-validator-plugin/42c3e1422d1ff596bd57ccafe0f0161d4057fd29.json'));
        touch($cacheFile->__toString(), time() - 86401);

        $request = new FakeRequest('/valid');
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame(StatusCode::OK, $response->getStatusCode()->getCode());
        self::assertSame('success', $response->getHeader('X-Html-Validator-Plugin'));
        self::assertSame("<!DOCTYPE html>\r\n<html>\r<head>\n<title>A valid test page</title></head>\n\n</html>", $response->getContent());
    }

    /**
     * Test not cached invalid content.
     */
    public function testNotCachedInvalidContent()
    {
        $this->clearPluginCache();

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
        /** @var HtmlValidatorPlugin $plugin */
        $plugin = $this->myApplication->getPlugins()[0];
        $plugin->addIgnorePath('foo/');
        $plugin->addIgnorePath('/bar/baz');
        $plugin->addIgnorePath('bar/baz/foo/');

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
     * Set up.
     */
    public function setUp()
    {
        $this->myApplication = new FakeApplication();
        $this->myApplication->addRoute(new Route('', TestController::class));
        $this->myApplication->addPlugin(new HtmlValidatorPlugin());
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
     * @var ApplicationInterface My application.
     */
    private $myApplication;
}

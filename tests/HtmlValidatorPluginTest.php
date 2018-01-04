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

/**
 * Test HtmlValidatorPlugin class.
 */
class HtmlValidatorPluginTest extends \PHPUnit_Framework_TestCase
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
        self::assertSame('Success', $response->getHeader('X-HtmlValidatorPlugin'));
        self::assertSame('<!DOCTYPE html><html><head><title>A valid test page</title></head></html>', $response->getContent());
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
        self::assertSame('Success; Cached', $response->getHeader('X-HtmlValidatorPlugin'));
        self::assertSame('<!DOCTYPE html><html><head><title>A valid test page</title></head></html>', $response->getContent());
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
        self::assertSame('Fail', $response->getHeader('X-HtmlValidatorPlugin'));
        self::assertNotContains('<!DOCTYPE html><html><head></head><body><p>An invalid page.</p></body>', $response->getContent());
        self::assertContains('<h1>HTML validation failed</h1>', $response->getContent());
        self::assertContains('<li>At line 1, column 34: Element &ldquo;head&rdquo; is missing a required instance of child element &ldquo;title&rdquo;.</li>', $response->getContent());
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
        self::assertSame('Fail; Cached', $response->getHeader('X-HtmlValidatorPlugin'));
        self::assertNotContains('<!DOCTYPE html><html><head></head><body><p>An invalid page.</p></body>', $response->getContent());
        self::assertContains('<h1>HTML validation failed</h1>', $response->getContent());
        self::assertContains('<li>At line 1, column 34: Element &ldquo;head&rdquo; is missing a required instance of child element &ldquo;title&rdquo;.</li>', $response->getContent());
    }

    // fixme: Test empty content.
    // fixme: Test non-html content.

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

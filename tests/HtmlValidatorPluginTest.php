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
     * Test valid content.
     */
    public function testValidContent()
    {
        $request = new FakeRequest('/valid');
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame(StatusCode::OK, $response->getStatusCode()->getCode());
        self::assertSame('<!DOCTYPE html><html><head><title>A valid test page</title></head></html>', $response->getContent());
    }

    /**
     * Test invalid content.
     */
    public function testInvalidContent()
    {
        $request = new FakeRequest('/invalid');
        $response = new FakeResponse();
        $this->myApplication->run($request, $response);

        self::assertSame(StatusCode::INTERNAL_SERVER_ERROR, $response->getStatusCode()->getCode());
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
        $this->clearPluginCache();
        $this->myApplication = null;
    }

    /**
     * Clears the plugin cache.
     */
    private function clearPluginCache()
    {
        $cacheDirectory = $this->myApplication->getTempPath()->withFilePath(FilePath::parse('michaelhall/html-validator-plugin/'));
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

<?php

namespace MichaelHall\HtmlValidatorPlugin\Tests;

use BlueMvc\Core\Http\StatusCode;
use BlueMvc\Core\Interfaces\ApplicationInterface;
use BlueMvc\Core\Route;
use BlueMvc\Fakes\FakeApplication;
use BlueMvc\Fakes\FakeRequest;
use BlueMvc\Fakes\FakeResponse;
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

    // fixme: Test empty content.
    // fixme: Test invalid content.
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
     * @var ApplicationInterface My application.
     */
    private $myApplication;
}

<?php


namespace MichaelHall\HtmlValidatorPlugin\Tests\Helpers;

use BlueMvc\Core\Controller;

/**
 * Test controller.
 */
class TestController extends Controller
{
    /**
     * A valid page.
     *
     * @return string The result.
     */
    public function validAction()
    {
        return '<!DOCTYPE html><html><head><title>A valid test page</title></head></html>';
    }
}

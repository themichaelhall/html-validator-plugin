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

    /**
     * An invalid page.
     *
     * @return string The result.
     */
    public function invalidAction()
    {
        return '<!DOCTYPE html><html><head></head><body><p>An invalid page.</p></body>';
    }

    /**
     * An empty page.
     *
     * @return string The result.
     */
    public function emptyAction()
    {
        return '';
    }
}

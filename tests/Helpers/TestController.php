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
        return "<!DOCTYPE html>\r\n<html lang=\"en\">\r<head>\n<title>A valid test page</title></head>\n\n</html>";
    }

    /**
     * An invalid page.
     *
     * @return string The result.
     */
    public function invalidAction()
    {
        return "<!DOCTYPE html>\r\n<html>\r<head>\n</head><body><p>An invalid page.</p>\n\n</body>";
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

    /**
     * A page with content that is not html.
     *
     * @return string The result.
     */
    public function notHtmlAction()
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json');

        return '{"Foo": "Bar"}';
    }
}

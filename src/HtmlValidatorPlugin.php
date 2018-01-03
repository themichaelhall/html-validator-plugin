<?php
/**
 * This file is a part of the html-validator-plugin package.
 *
 * Read more at https://github.com/themichaelhall/html-validator-plugin
 */

namespace MichaelHall\HtmlValidatorPlugin;

use BlueMvc\Core\Base\AbstractPlugin;
use BlueMvc\Core\Interfaces\ApplicationInterface;
use BlueMvc\Core\Interfaces\RequestInterface;
use BlueMvc\Core\Interfaces\ResponseInterface;

/**
 * HTML validator plugin.
 *
 * @since 1.0.0
 */
class HtmlValidatorPlugin extends AbstractPlugin
{
    /**
     * Called after a request is processed.
     *
     * @since 1.0.0
     *
     * @param ApplicationInterface $application The application.
     * @param RequestInterface     $request     The request.
     * @param ResponseInterface    $response    The response.
     *
     * @return bool True if request should stop processing, false otherwise.
     */
    public function onPostRequest(ApplicationInterface $application, RequestInterface $request, ResponseInterface $response)
    {
        parent::onPostRequest($application, $request, $response);

        return false;
    }
}

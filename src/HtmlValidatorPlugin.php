<?php
/**
 * This file is a part of the html-validator-plugin package.
 *
 * Read more at https://github.com/themichaelhall/html-validator-plugin
 */

namespace MichaelHall\HtmlValidatorPlugin;

use BlueMvc\Core\Base\AbstractPlugin;
use BlueMvc\Core\Http\StatusCode;
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

        $validationResult = self::myValidate($response->getContent());
        if (count($validationResult) === 0) {
            return false;
        }

        $response->setContent(self::myCreateErrorPageContent($validationResult));
        $response->setStatusCode(new StatusCode(StatusCode::INTERNAL_SERVER_ERROR));

        return true;
    }

    /**
     * Creates error page content from validation result.
     *
     * @param array $validationResult The validation result.
     *
     * @return string The error page content.
     */
    private static function myCreateErrorPageContent(array $validationResult)
    {
        $result =
            '<!DOCTYPE html>' .
            '<html>' .
            '<head>' .
            '<meta charset="utf-8">' .
            '<title>HTML validation failed</title>' .
            '</head>' .
            '<body>' .
            '<h1>HTML validation failed</h1>' .
            '<ul>';

        foreach ($validationResult as $validationItem) {
            $line = isset($validationItem['lastLine']) ? $validationItem['lastLine'] : 0;
            $column = isset($validationItem['lastColumn']) ? $validationItem['lastColumn'] : 0;
            $message = isset($validationItem['message']) ? $validationItem['message'] : '';

            $result .= '<li>At line ' . htmlentities($line) . ', column ' . htmlentities($column) . ': ' . htmlentities($message) . '</li>';
        }

        $result .=
            '</ul>' .
            '</body>' .
            '</html>';

        return $result;
    }

    /**
     * Validates the content.
     *
     * @param string $content The content.
     *
     * @return array The messages.
     */
    private static function myValidate($content)
    {
        // fixme: cache result.
        $result = self::myDoValidate($content);

        return json_decode($result, true)['messages'];
    }

    /**
     * Does a validation via validator.w3.org POST API.
     *
     * @param string $content The content to validate.
     *
     * @return string The result as JSON.
     */
    private static function myDoValidate($content)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, self::VALIDATOR_URL);
        curl_setopt($curl, CURLOPT_USERAGENT, 'HtmlValidatorPlugin/1.0 (+https://github.com/themichaelhall/html-validator-plugin)');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: text/html; charset=utf-8']); // fixme
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);

        curl_close($curl);

        return $result !== false ? $result : '{"messages":[]}';
    }

    /**
     * My validator url.
     *
     * @since 1.0.0
     */
    const VALIDATOR_URL = 'https://validator.w3.org/nu/?out=json';
}

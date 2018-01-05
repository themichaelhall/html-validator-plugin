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
use DataTypes\FilePath;
use DataTypes\Interfaces\FilePathInterface;

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

        $contentType = $response->getHeader('Content-Type') ?: 'text/html; charset=utf-8';

        $validationResult = self::myValidate($application->getTempPath(), $contentType, $response->getContent(), $resultHeader);
        $response->setHeader('X-Html-Validator-Plugin', $resultHeader);

        if (count($validationResult) === 0) {
            return false;
        }

        $response->setContent(self::myCreateErrorPageContent($validationResult));
        $response->setStatusCode(new StatusCode(StatusCode::INTERNAL_SERVER_ERROR));

        return true;
    }

    /**
     * Validates the content.
     *
     * @param FilePathInterface $tempDir      The path to a temporary directory.
     * @param string            $contentType  The content type.
     * @param string            $content      The content.
     * @param string|null       $resultHeader The header describing the result.
     *
     * @return array The messages.
     */
    private static function myValidate(FilePathInterface $tempDir, $contentType, $content, &$resultHeader = null)
    {
        if (trim($content) === '') {
            $resultHeader = 'ignored; empty-content';

            return [];
        }

        if (!self::myIsHtmlContentType($contentType)) {
            $resultHeader = 'ignored; not-html';

            return [];
        }

        $checksum = sha1($content);
        $cacheFilename = self::myGetCacheFilename($tempDir, $checksum);
        $isCached = true;

        if (!file_exists($cacheFilename->__toString())) {
            $isCached = false;
            $result = self::myDoValidate($contentType, $content);
            file_put_contents($cacheFilename->__toString(), $result);
        }

        $jsonResult = file_get_contents($cacheFilename->__toString());
        $result = json_decode($jsonResult, true)['messages'];

        $resultHeader = count($result) === 0 ? 'success' : 'fail';
        if ($isCached) {
            $resultHeader .= '; from-cache';
        }

        return $result;
    }

    /**
     * Does a validation via validator.w3.org POST API.
     *
     * @param string $contentType The content type.
     * @param string $content     The content to validate.
     *
     * @return string The result as JSON.
     */
    private static function myDoValidate($contentType, $content)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, self::VALIDATOR_URL);
        curl_setopt($curl, CURLOPT_USERAGENT, 'HtmlValidatorPlugin/1.0 (+https://github.com/themichaelhall/html-validator-plugin)');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: ' . $contentType]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);

        curl_close($curl);

        return $result !== false ? $result : '{"messages":[]}';
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
     * Returns the path to the cache file with the specified checksum.
     *
     * @param FilePathInterface $tempDir  The path to a temporary directory.
     * @param string            $checksum The checksum.
     *
     * @return FilePathInterface The path to the cache file.
     */
    private static function myGetCacheFilename(FilePathInterface $tempDir, $checksum)
    {
        $directory = $tempDir->withFilePath(FilePath::parse('michaelhall' . DIRECTORY_SEPARATOR . 'html-validator-plugin' . DIRECTORY_SEPARATOR));
        if (!is_dir($directory->__toString())) {
            mkdir($directory->__toString(), 0777, true);
        }

        return $directory->withFilePath(FilePath::parse($checksum . '.json'));
    }

    /**
     * Checks if the specified content type is html.
     *
     * @param string $contentType The content type.
     *
     * @return bool True if the specified content type
     */
    private static function myIsHtmlContentType($contentType)
    {
        $contentTypeParts = explode(';', $contentType, 2);
        $contentType = strtolower(trim($contentTypeParts[0]));

        return in_array($contentType, ['text/html']);
    }

    /**
     * My validator url.
     *
     * @since 1.0.0
     */
    const VALIDATOR_URL = 'https://validator.w3.org/nu/?out=json';
}

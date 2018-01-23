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
use DataTypes\Interfaces\UrlPathInterface;
use DataTypes\UrlPath;

/**
 * HTML validator plugin.
 *
 * @since 1.0.0
 */
class HtmlValidatorPlugin extends AbstractPlugin
{
    /**
     * HtmlValidatorPlugin constructor.
     *
     * @since 1.0.0
     *
     * @param string $validatorUrl The validator url.
     *
     * @throws \InvalidArgumentException If the $validatorUrl parameter is not a string.
     */
    public function __construct($validatorUrl = self::DEFAULT_VALIDATOR_URL)
    {
        if (!is_string($validatorUrl)) {
            throw new \InvalidArgumentException('The $validatorUrl parameter is not a string.');
        }

        $this->myValidatorUrl = $validatorUrl;
    }

    /**
     * Adds a path to ignore for validation.
     *
     * If the path is a directory, the whole directory will be ignored. If the path is a file, only the file will be ignored.
     *
     * @since 1.0.0
     *
     * @param string $ignorePath The path to ignore.
     *
     * @throws \InvalidArgumentException If the $ignorePath parameter is not a string.
     */
    public function addIgnorePath($ignorePath)
    {
        if (!is_string($ignorePath)) {
            throw new \InvalidArgumentException('The $ignorePath parameter is not a string.');
        }

        $this->myIgnorePaths[] = UrlPath::parse('/' . $ignorePath);
    }

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
        $content = $response->getContent();

        $validationResult = $this->myValidate($request->getUrl()->getPath(), $application->getTempPath(), $contentType, $content, $resultHeader);
        $response->setHeader('X-Html-Validator-Plugin', $resultHeader);

        if (count($validationResult) === 0) {
            return false;
        }

        $response->setContent(self::myCreateErrorPageContent($validationResult, $content));
        $response->setStatusCode(new StatusCode(StatusCode::INTERNAL_SERVER_ERROR));

        return true;
    }

    /**
     * Validates the content.
     *
     * @param UrlPathInterface  $requestPath  The request path.
     * @param FilePathInterface $tempDir      The path to a temporary directory.
     * @param string            $contentType  The content type.
     * @param string            $content      The content.
     * @param string|null       $resultHeader The header describing the result.
     *
     * @return array The messages.
     */
    private function myValidate(UrlPathInterface $requestPath, FilePathInterface $tempDir, $contentType, $content, &$resultHeader = null)
    {
        if ($this->myIsIgnoredPath($requestPath, $ignorePath)) {
            $resultHeader = 'ignored; ignore-path=' . $ignorePath;

            return [];
        }

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

        if (!file_exists($cacheFilename->__toString()) || filemtime($cacheFilename->__toString()) <= time() - 86400) {
            $isCached = false;
            $result = $this->myDoValidate($contentType, $content);
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
     * Checks if the request path is an ignored path.
     *
     * @param UrlPathInterface      $requestPath The request path.
     * @param UrlPathInterface|null $ignorePath  If path was ignored, the ignored path, otherwise undefined.
     *
     * @return bool True if request path is an ignored path, false otherwise.
     */
    private function myIsIgnoredPath(UrlPathInterface $requestPath, UrlPathInterface &$ignorePath = null)
    {
        foreach ($this->myIgnorePaths as $ignorePath) {
            if ($ignorePath->isDirectory()) {
                $isMatch = substr($requestPath->__toString(), 0, strlen($ignorePath->__toString())) === $ignorePath->__toString();
            } else {
                $isMatch = $requestPath->equals($ignorePath);
            }

            if ($isMatch) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does a validation via validator.w3.org POST API.
     *
     * @param string $contentType The content type.
     * @param string $content     The content to validate.
     *
     * @return string The result as JSON.
     */
    private function myDoValidate($contentType, $content)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $this->myValidatorUrl);
        curl_setopt($curl, CURLOPT_USERAGENT, 'HtmlValidatorPlugin/1.0 (+https://github.com/themichaelhall/html-validator-plugin)');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: ' . $contentType]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);

        curl_close($curl);

        return $result !== false ? $result : '{"messages":[{"type":"error","message":"Error contacting validator."}]}';
    }

    /**
     * Creates error page content from validation result.
     *
     * @param array  $validationResult The validation result.
     * @param string $content          The content.
     *
     * @return string The error page content.
     */
    private static function myCreateErrorPageContent(array $validationResult, $content)
    {
        $result = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>HTML validation failed</title></head><body><h1>HTML validation failed</h1><ul>';

        foreach ($validationResult as $validationItem) {
            $type = $validationItem['type'];
            $firstLine = isset($validationItem['firstLine']) ? $validationItem['firstLine'] : 0;
            $lastLine = isset($validationItem['lastLine']) ? $validationItem['lastLine'] : 0;
            $message = isset($validationItem['message']) ? $validationItem['message'] : '';

            $result .= '<li>' . htmlentities($type) . ': line ' . htmlentities($firstLine !== 0 ? $firstLine . '-' . $lastLine : $lastLine) . ': ' . htmlentities($message) . '</li>';
        }

        $result .= '</ul><h2>Source</h2><pre>';

        $line = 1;
        foreach (preg_split("/\r\n|\n|\r/", $content) as $contentLine) {
            $result .= str_pad($line++, 3, ' ', STR_PAD_LEFT) . ' ' . htmlentities($contentLine) . '<br />';
        }

        $result .= '</pre></body></html>';

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
     * @var string My validator url.
     */
    private $myValidatorUrl;

    /**
     * @var UrlPathInterface[] My ignore paths.
     */
    private $myIgnorePaths = [];

    /**
     * My validator url.
     *
     * @since 1.0.0
     */
    const DEFAULT_VALIDATOR_URL = 'https://validator.w3.org/nu/?out=json';
}

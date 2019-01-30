<?php
/**
 * This file is a part of the html-validator-plugin package.
 *
 * Read more at https://github.com/themichaelhall/html-validator-plugin
 */
declare(strict_types=1);

namespace MichaelHall\HtmlValidatorPlugin;

use BlueMvc\Core\Base\AbstractPlugin;
use BlueMvc\Core\Http\StatusCode;
use BlueMvc\Core\Interfaces\ApplicationInterface;
use BlueMvc\Core\Interfaces\RequestInterface;
use BlueMvc\Core\Interfaces\ResponseInterface;
use DataTypes\FilePath;
use DataTypes\Interfaces\FilePathInterface;
use DataTypes\Interfaces\UrlPathInterface;
use DataTypes\Url;
use DataTypes\UrlPath;
use MichaelHall\HttpClient\HttpClient;
use MichaelHall\HttpClient\HttpClientRequest;

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
     */
    public function __construct(string $validatorUrl = self::DEFAULT_VALIDATOR_URL)
    {
        $this->validatorUrl = $validatorUrl;
    }

    /**
     * Adds a path to ignore for validation.
     *
     * If the path is a directory, the whole directory will be ignored. If the path is a file, only the file will be ignored.
     *
     * @since 1.0.0
     *
     * @param string $ignorePath The path to ignore.
     */
    public function addIgnorePath(string $ignorePath): void
    {
        $this->ignorePaths[] = UrlPath::parse('/' . $ignorePath);
    }

    /**
     * Enables plugin to run, even if not in debug mode.
     *
     * Note that it is not recommended to run this plugin in live code!
     *
     * @since 1.0.0
     */
    public function enableInReleaseMode(): void
    {
        $this->enableInReleaseMode = true;
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
    public function onPostRequest(ApplicationInterface $application, RequestInterface $request, ResponseInterface $response): bool
    {
        parent::onPostRequest($application, $request, $response);

        if (!$application->isDebug() && !$this->enableInReleaseMode) {
            return false;
        }

        $content = $response->getContent();
        $validationResult = $this->validate($application, $request, $response, $resultHeader);
        $response->setHeader('X-Html-Validator-Plugin', $resultHeader);

        if (count($validationResult) === 0) {
            return false;
        }

        $response->setContent(self::createErrorPageContent($validationResult, $content));
        $response->setStatusCode(new StatusCode(StatusCode::INTERNAL_SERVER_ERROR));

        return true;
    }

    /**
     * Validates the content.
     *
     * @param ApplicationInterface $application  The application.
     * @param RequestInterface     $request      The request.
     * @param ResponseInterface    $response     The response.
     * @param string|null          $resultHeader The header describing the result.
     *
     * @return array The messages.
     */
    private function validate(ApplicationInterface $application, RequestInterface $request, ResponseInterface $response, ?string &$resultHeader = null): array
    {
        $content = $response->getContent();
        $contentType = $response->getHeader('Content-Type') ?: 'text/html; charset=utf-8';

        if ($this->shouldIgnore($request->getUrl()->getPath(), $content, $contentType, $reason)) {
            $resultHeader = 'ignored; ' . $reason;

            return [];
        }

        $checksum = sha1($content);
        $cacheFilename = self::getCacheFilename($application->getTempPath(), $checksum);
        $isCached = true;

        if (!file_exists($cacheFilename->__toString()) || filemtime($cacheFilename->__toString()) <= time() - 86400) {
            $isCached = false;
            $result = $this->doValidate($contentType, $content);
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
     * Check whether validation should be ignored.
     *
     * @param UrlPathInterface $path        The path
     * @param string           $content     The content.
     * @param string           $contentType The content type.
     * @param string|null      $reason      The reason for ignore or undefined if validation should not be ignored.
     *
     * @return bool True if validation should be ignored, false otherwise.
     */
    private function shouldIgnore(UrlPathInterface $path, string $content, string $contentType, ?string &$reason = null): bool
    {
        if ($this->isIgnoredPath($path, $ignorePath)) {
            $reason = 'ignore-path=' . $ignorePath;

            return true;
        }

        if (trim($content) === '') {
            $reason = 'empty-content';

            return true;
        }

        $contentTypeParts = explode(';', $contentType, 2);
        if (!in_array(strtolower(trim($contentTypeParts[0])), ['text/html'])) {
            $reason = 'not-html';

            return true;
        }

        return false;
    }

    /**
     * Checks if the request path is an ignored path.
     *
     * @param UrlPathInterface      $requestPath The request path.
     * @param UrlPathInterface|null $ignorePath  If path was ignored, the ignored path, otherwise undefined.
     *
     * @return bool True if request path is an ignored path, false otherwise.
     */
    private function isIgnoredPath(UrlPathInterface $requestPath, UrlPathInterface &$ignorePath = null): bool
    {
        foreach ($this->ignorePaths as $ignorePath) {
            $isMatch = $ignorePath->isDirectory() ?
                substr($requestPath->__toString(), 0, strlen($ignorePath->__toString())) === $ignorePath->__toString() :
                $requestPath->equals($ignorePath);

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
    private function doValidate(string $contentType, string $content): string
    {
        $httpClient = new HttpClient();

        $request = new HttpClientRequest(Url::parse($this->validatorUrl), 'POST');
        $request->addHeader('User-Agent: HtmlValidatorPlugin/1.1 (+https://github.com/themichaelhall/html-validator-plugin)');
        $request->addHeader('Content-Type: ' . $contentType);
        $request->setRawContent($content);

        $response = $httpClient->send($request);

        return $response->isSuccessful() ? $response->getContent() : '{"messages":[{"type":"error","message":"Error contacting validator."}]}';
    }

    /**
     * Creates error page content from validation result.
     *
     * @param array  $validationResult The validation result.
     * @param string $content          The content.
     *
     * @return string The error page content.
     */
    private static function createErrorPageContent(array $validationResult, string $content): string
    {
        $result = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>HTML validation failed</title></head><body><h1>HTML validation failed</h1><ul>';

        foreach ($validationResult as $validationItem) {
            $type = $validationItem['type'];
            $firstLine = isset($validationItem['firstLine']) ? $validationItem['firstLine'] : 0;
            $lastLine = isset($validationItem['lastLine']) ? $validationItem['lastLine'] : 0;
            $message = isset($validationItem['message']) ? $validationItem['message'] : '';

            $result .= '<li>' . htmlentities($type) . ': line ' . htmlentities(strval($firstLine !== 0 ? $firstLine . '-' . $lastLine : $lastLine)) . ': ' . htmlentities($message) . '</li>';
        }

        $result .= '</ul><h2>Source</h2><pre>';

        $line = 1;
        foreach (preg_split("/\r\n|\n|\r/", $content) as $contentLine) {
            $result .= str_pad(strval($line++), 3, ' ', STR_PAD_LEFT) . ' ' . htmlentities($contentLine) . '<br />';
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
    private static function getCacheFilename(FilePathInterface $tempDir, string $checksum): FilePathInterface
    {
        $directory = $tempDir->withFilePath(FilePath::parse('michaelhall' . DIRECTORY_SEPARATOR . 'html-validator-plugin' . DIRECTORY_SEPARATOR));
        if (!is_dir($directory->__toString())) {
            mkdir($directory->__toString(), 0777, true);
        }

        return $directory->withFilePath(FilePath::parse($checksum . '.json'));
    }

    /**
     * @var string My validator url.
     */
    private $validatorUrl;

    /**
     * @var UrlPathInterface[] My ignore paths.
     */
    private $ignorePaths = [];

    /**
     * @var bool If true always enabled, if false only enable in debug mode.
     */
    private $enableInReleaseMode = false;

    /**
     * My validator url.
     *
     * @since 1.0.0
     */
    const DEFAULT_VALIDATOR_URL = 'https://validator.w3.org/nu/?out=json';
}

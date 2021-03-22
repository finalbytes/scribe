<?php

namespace Knuckles\Camel\Output;

use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route;
use Knuckles\Camel\BaseDTO;
use Knuckles\Camel\Extraction\ResponseCollection;
use Knuckles\Camel\Extraction\ResponseField;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Tools\Utils as u;
use Knuckles\Camel\Extraction\Metadata;


class OutputEndpointData extends BaseDTO
{
    /**
     * @var array<string>
     */
    public array $methods;

    public string $uri;

    public Metadata $metadata;

    /**
     * @var array<string,string>
     */
    public array $headers = [];

    /**
     * @var array<string,\Knuckles\Camel\Output\Parameter>
     */
    public array $urlParameters = [];

    /**
     * @var array<string,mixed>
     */
    public array $cleanUrlParameters = [];

    /**
     * @var array<string,\Knuckles\Camel\Output\Parameter>
     */
    public array $queryParameters = [];

    /**
     * @var array<string,mixed>
     */
    public array $cleanQueryParameters = [];

    /**
     * @var array<string, \Knuckles\Camel\Output\Parameter>
     */
    public array $bodyParameters = [];

    /**
     * @var array<string,mixed>
     */
    public array $cleanBodyParameters = [];

    /**
     * @var array
     * @var array<string,\Illuminate\Http\UploadedFile>
     */
    public array $fileParameters = [];

    /**
     * @var \Knuckles\Camel\Extraction\ResponseCollection
     */
    public $responses;

    /**
     * @var array<string,\Knuckles\Camel\Extraction\ResponseField>
     */
    public array $responseFields = [];

    /**
     * Authentication info for this endpoint. In the form [{where}, {name}, {sample}]
     * Example: ["query", "api_key", "njiuyiw97865rfyvgfvb1"]
     */
    public array $auth = [];

    /**
     * @var array<string, array>
     */
    public array $nestedBodyParameters = [];

    public ?string $boundUri;

    public function __construct(array $parameters = [])
    {
        // spatie/dto currently doesn't auto-cast nested DTOs like that
        $parameters['responses'] = new ResponseCollection($parameters['responses']);
        $parameters['bodyParameters'] = array_map(fn($param) => new Parameter($param), $parameters['bodyParameters']);
        $parameters['queryParameters'] = array_map(fn($param) => new Parameter($param), $parameters['queryParameters']);
        $parameters['urlParameters'] = array_map(fn($param) => new Parameter($param), $parameters['urlParameters']);
        $parameters['responseFields'] = array_map(fn($param) => new ResponseField($param), $parameters['responseFields']);

        parent::__construct($parameters);

        $this->boundUri = u::getUrlWithBoundParameters($this->uri, $this->cleanUrlParameters);
        $this->nestedBodyParameters = Extractor::nestArrayAndObjectFields($this->bodyParameters);

        $this->cleanBodyParameters = Extractor::cleanParams($this->bodyParameters);
        $this->cleanQueryParameters = Extractor::cleanParams($this->queryParameters);
        $this->cleanUrlParameters = Extractor::cleanParams($this->urlParameters);

        [$files, $regularParameters] = collect($this->cleanBodyParameters)
            ->partition(
                fn($example) => $example instanceof UploadedFile
                    || (is_array($example) && ($example[0] ?? null) instanceof UploadedFile)
            );
        if (count($files)) {
            $this->headers['Content-Type'] = 'multipart/form-data';
        }
        $this->fileParameters = $files->toArray();
        $this->cleanBodyParameters = $regularParameters->toArray();
    }

    /**
     * @param Route $route
     *
     * @return array<string>
     */
    public static function getMethods(Route $route): array
    {
        $methods = $route->methods();

        // Laravel adds an automatic "HEAD" endpoint for each GET request, so we'll strip that out,
        // but not if there's only one method (means it was intentional)
        if (count($methods) === 1) {
            return $methods;
        }

        return array_diff($methods, ['HEAD']);
    }

    public static function fromExtractedEndpointArray(array $endpoint): OutputEndpointData
    {
        return new self($endpoint);
    }

    public function name()
    {
        return sprintf("[%s] {$this->route->uri}.", implode(',', $this->route->methods));
    }

    public function endpointId()
    {
        return $this->methods[0].str_replace(['/', '?', '{', '}', ':'], '-', $this->uri);
    }

    public function hasResponses(): bool
    {
        return count($this->responses) > 0;
    }

    public function hasFiles(): bool
    {
        return count($this->fileParameters) > 0;
    }

    public function isGet(): bool
    {
        return in_array('GET', $this->methods);
    }

    public function hasRequestOptions(): bool
    {
        return !empty($this->headers)
            || !empty($this->cleanQueryParameters)
            || !empty($this->cleanBodyParameters);
    }
}
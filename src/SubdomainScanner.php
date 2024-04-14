<?php
namespace Ccteam\Pss;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DomCrawler\Crawler;

class SubdomainScanner
{
    protected string $domain = '';
    protected array $subdomains = [];
    protected readonly ClientInterface $client;
    protected string $baseUrl = 'https://subdomainfinder.c99.nl';

    public function __construct(string $domain)
    {
        $this->domain = $domain;

        $this->client = new Client([
            'allow_redirects' => false,
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36'
        ]);
    }

    public function scan()
    {
        $scanUrl = $this->getScanUrl();
        if (!$scanUrl) {
            return false;
        }

        $response = $this->client->request('GET', $scanUrl, [
            'headers' => [
                'Origin' => $this->baseUrl,
                'Referer' => $this->baseUrl . '/index.php',
            ]
        ]);
        
        $that = $this;

        (new Crawler($response->getBody()))
            ->filter('center .table-responsive tr')
            ->each(function (Crawler $node, $i) use ($that) {
                if ($i > 0) {
                    $links = $node->filter('td .link');
                    if ($links->count() > 0) {
                        $that->subdomains[] = new Subdomain(
                            $links->eq(0)->text(),
                            $links->eq(1)->text()
                        );
                    }
                }
            });

        return $this->subdomains;
    }

    public function getScanUrl()
    {
        $response = $this->client->request('POST', $this->baseUrl . '/index.php', [
            'form_params' => $this->getPostParams(),
        ]);

        if ($response->getStatusCode() === 302) {
            return $response->getHeader('Location')[0];
        }

        return '';
    }

    public function getPostParams()
    {
        $response = $this->client->get($this->baseUrl);
        $crawler = new Crawler($response->getBody());
        $re = new RE($crawler, $this->client);
        
        $postParams = [
            'domain' => $this->domain,
            'scan_subdomains' => '',
        ];

        $formInputs = $crawler->filter('body div.input-group > input[type=hidden]');

        foreach ($formInputs as $input) {
            $inputName = $input->getAttribute('name');
            if ($inputName === $re->getOldAttribute()) {
                $postParams[$re->getNewAttributte()] = $input->getAttribute('value');
            } else if ($inputName === 'jn') {
                $postParams['jn'] = 'JS aan, T aangeroepen, CSRF aangepast';
            } else {
                $postParams[$inputName] = $input->getAttribute('value');
            }
        }

        return $postParams;
    }
}

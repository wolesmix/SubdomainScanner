<?php

namespace Ccteam\Pss;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DomCrawler\Crawler;

class Subdomain {
    public readonly string $subdomain;
    public readonly string $ip;
    public readonly ClientInterface $client;

    public function __construct(string $subdomain, string $ip)
    {
        $this->subdomain = $subdomain;
        $this->ip = $ip;
        $this->client = new Client(['allow_redirects' => false]);
    }

    public function isOpen(int $timeout)
    {
        $isOpen = [false, false, false];
        if ($fp = @fsockopen(hostname: $this->subdomain, port: '81', timeout: $timeout)) {
            fclose($fp);
            $isOpen[0] = true;

            try {
                $response = $this->client->get('http://' . $this->subdomain . ':81');
                if ($response->getStatusCode() === 200) {
                    if (preg_match('/<h1>Index of \/<\/h1>/', $response->getBody())) {
                        $isOpen[2] = true;
                    }
                }
                $isOpen[1] = $response->getStatusCode();
            } catch (ClientException $e) {
                $isOpen[1] = $e->getResponse()->getStatusCode();
            }
        }

        return $isOpen;
    }

    public function getFilesUrl()
    {
        $crawler = new Crawler(
            $this->client->get('http://' . $this->subdomain . ':81')->getBody()
        );

        $files = [];

        foreach ($crawler->filter('pre a') as $a) {
            $url = $a->getAttribute('href');
            if ($url && $url !== '../') {
                $files[] = [$url, $a->textContent];
            }
        }

        return $files;
    }

    public function downloadFile(array $file)
    {
        $fileDir = json_decode(
            file_get_contents(__DIR__ . '/config/config.json'), true
        )['fileDir'];

        @mkdir($fileDir);
        @mkdir($fileDir . '/' . $this->subdomain);


        try {
            $response = $this->client->get('http://' . $this->subdomain . ':81/' . $file[0]);
            if ($response->getStatusCode() === 200) {
                file_put_contents($fileDir . '/' . $this->subdomain . '/' . $file[1], $response->getBody());
            }

            return true;
        } catch (ClientException $e) {
            return false;
        }
    }
}
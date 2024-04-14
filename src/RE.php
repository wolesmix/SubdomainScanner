<?php
namespace Ccteam\Pss;

use Exception;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DomCrawler\Crawler;

class RE {
    protected string $js = '';
    protected string $oldAttribute = '';
    protected string $newAttribute = '';
    protected string $csrf ='';
    protected array $token = [];
    protected ClientInterface $client;

    public function __construct(Crawler $crawler, ClientInterface $client)
    {
        $this->client = $client;

        foreach ($crawler->filter('head script') as $script) {
            if ($script->hasAttribute('src')) {
                $jsUrl = $script->getAttribute('src');
                if (preg_match('/\/\/subdomainfinder\.c99\.nl\/js\/.*\.js/', $jsUrl)) {
                    $this->js = $this->client->get('https:' . $jsUrl)->getBody();
                }
            }
        }

        if (!$this->js) {
             throw new Exception('Missing Csrf Js File.');
        }
    }

    public function getToken() {
        if (!$this->token) {
            $token = [];
            if (!preg_match('/item\.setAttribute\("name", "(.*)" \+ data.(.*) \+ "(.*)"\);/', $this->js, $token)) {
                throw new Exception('Missing Token Attributes.');
            }
            $this->token = [$token[1], $token[2], $token[3]];
        }
        
        return $this->token;
    }

    public function getNewAttributte() {
        $token = $this->getToken();
        return $token[0] . $this->getCsrf() . $token[2];
    }

    public function getCsrf() {
        if (!$this->csrf) {
            $path = [];
            if (!preg_match('/fetch\("(.*)"\)/', $this->js, $path)) {
                throw new Exception('Missing Csrf Url');
            }
            $this->csrf = json_decode(
                $this->client->get('https://subdomainfinder.c99.nl' . $path[1])->getBody(), true
            )
            [$this->getToken()[1]];

            if (!$this->csrf) {
                throw new Exception('Missing csrf.');
            }
        }
        
        return $this->csrf;

    }

    public function getOldAttribute() {
        if (!$this->oldAttribute) {
            $oldAttribute = [];
            if (!preg_match('/document\.getElementsByName\("(.*)"\);/', $this->js, $oldAttribute)) {
                throw new Exception('Missing Csrf Old Attribute.');
            }

            $this->oldAttribute = $oldAttribute[1];
        }

        return $this->oldAttribute;
    }
}
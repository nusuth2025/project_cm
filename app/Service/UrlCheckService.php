<?php
declare(strict_types=1);

namespace App\Service;

class UrlCheckService
{
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64; rv:148.0) Gecko/20100101 Firefox/148.0';
    private const ACCEPTED_CONTENT_TYPES = ['text/html'];

    /**
     * Prüft ob eine URL erreichbar ist und HTML zurückliefert.
     * Bug-Fix gegenüber PostUrl: ein einziger cURL-Request statt drei.
     */
    public function isWorkingUrl(string $url): bool
    {
        $handle = $this->buildCurlHandle($url);
        curl_exec($handle);

        $statusCode  = (int) (curl_getinfo($handle, CURLINFO_HTTP_CODE) ?: $this->fallbackStatusCode($url));
        $contentType = $this->resolveContentType($handle, $url);

        curl_close($handle);

        if ($statusCode === 0) {
            return false;
        }
        if ($statusCode === 200) {
            return true;
        }
        // Andere Statuscodes (301, 403 etc.): nur gültig wenn Content-Type text/html
        return in_array($contentType, self::ACCEPTED_CONTENT_TYPES, true);
    }

    public function getHttpStatusCode(string $url): int
    {
        $handle = $this->buildCurlHandle($url);
        curl_exec($handle);
        $code = (int) (curl_getinfo($handle, CURLINFO_HTTP_CODE) ?: $this->fallbackStatusCode($url));
        curl_close($handle);
        return $code;
    }

    public function getContentType(string $url): string
    {
        $handle = $this->buildCurlHandle($url);
        curl_exec($handle);
        $type = $this->resolveContentType($handle, $url);
        curl_close($handle);
        return $type;
    }

    private function resolveContentType(\CurlHandle $handle, string $url): string
    {
        $raw = curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        if ($raw === false || $raw === null || $raw === '') {
            // Fallback für Server die mit cURL keinen Content-Type liefern (z. B. IHK-Seite)
            $raw = $this->fallbackContentType($url);
        }
        return strtolower(explode(';', (string) $raw)[0]);
    }

    private function buildCurlHandle(string $url): \CurlHandle
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_USERAGENT  => self::USER_AGENT,
            CURLOPT_ENCODING   => 'gzip, deflate, br, zstd',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: de,en-US;q=0.9,en;q=0.8',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
            ],
            CURLOPT_NOBODY          => true,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_MAXREDIRS       => 5,
            CURLOPT_TIMEOUT         => 10,
        ]);
        return $handle;
    }

    // Fallback für IHK-Server, die mit cURL keinen gültigen Content-Type liefern
    private function fallbackContentType(string $url): string
    {
        $headers = @get_headers($url, true);
        if ($headers === false) {
            return '';
        }
        return (string) ($headers['content-type'] ?? $headers['Content-Type'] ?? '');
    }

    private function fallbackStatusCode(string $url): int
    {
        $headers = @get_headers($url, true);
        if ($headers === false || !isset($headers[0])) {
            return 0;
        }
        $parts = explode(' ', (string) $headers[0]);
        return isset($parts[1]) ? (int) $parts[1] : 0;
    }
}

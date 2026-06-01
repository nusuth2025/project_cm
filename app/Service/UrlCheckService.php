<?php
declare(strict_types=1);

namespace App\Service;

class UrlCheckService
{
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64; rv:148.0) Gecko/20100101 Firefox/148.0';
    private const ACCEPTED_CONTENT_TYPES = ['text/html'];

    /**
     * Vollständige URL-Diagnose mit Statuscode, Weiterleitungsinfo und
     * einer nutzerverständlichen Meldung.
     */
    public function check(string $url): UrlCheckResult
    {
        $handle = $this->buildCurlHandle($url);
        curl_exec($handle);

        $statusCode   = (int) (curl_getinfo($handle, CURLINFO_HTTP_CODE) ?: $this->fallbackStatusCode($url));
        $contentType  = $this->resolveContentType($handle, $url);
        $effectiveUrl = (string) (curl_getinfo($handle, CURLINFO_EFFECTIVE_URL) ?: $url);

        curl_close($handle);

        // Manche Server lehnen HEAD ab — mit GET-Anfrage erneut versuchen
        if ($statusCode === 405) {
            return $this->checkWithGet($url);
        }

        return $this->buildResult($statusCode, $contentType, $effectiveUrl, $url);
    }

    /**
     * Rückwärtskompatible Kurzform.
     */
    public function isWorkingUrl(string $url): bool
    {
        return $this->check($url)->isUsable;
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

    /**
     * GET-Fallback wenn der Server HEAD-Anfragen ablehnt (405).
     * Body wird sofort verworfen — es zählen nur Status und Content-Type.
     */
    private function checkWithGet(string $url): UrlCheckResult
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_USERAGENT      => self::USER_AGENT,
            CURLOPT_ENCODING       => 'gzip, deflate, br, zstd',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: de,en-US;q=0.9,en;q=0.8',
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_WRITEFUNCTION  => static fn($_ch, $data) => strlen($data),
        ]);

        curl_exec($handle);

        $statusCode   = (int) (curl_getinfo($handle, CURLINFO_HTTP_CODE) ?: $this->fallbackStatusCode($url));
        $contentType  = $this->resolveContentType($handle, $url);
        $effectiveUrl = (string) (curl_getinfo($handle, CURLINFO_EFFECTIVE_URL) ?: $url);

        curl_close($handle);

        return $this->buildResult($statusCode, $contentType, $effectiveUrl, $url);
    }

    private function buildResult(
        int    $code,
        string $contentType,
        string $effectiveUrl,
        string $submittedUrl,
    ): UrlCheckResult {
        $isHtml     = in_array($contentType, self::ACCEPTED_CONTENT_TYPES, true);
        $redirected = rtrim($effectiveUrl, '/') !== rtrim($submittedUrl, '/');

        if ($code === 0) {
            return new UrlCheckResult(
                isUsable: false, statusCode: 0, effectiveUrl: $submittedUrl,
                message:  'Die Adresse ist nicht erreichbar. Bitte prüfen Sie die URL auf Tippfehler oder Verbindungsprobleme.',
                severity: 'error',
            );
        }

        if ($code === 200) {
            if ($redirected) {
                return new UrlCheckResult(
                    isUsable: true, statusCode: 200, effectiveUrl: $effectiveUrl,
                    message:  'Die eingegebene URL wurde automatisch weitergeleitet. Für den Monitor wird die finale Zieladresse verwendet.',
                    severity: 'info',
                );
            }
            return new UrlCheckResult(
                isUsable: true, statusCode: 200, effectiveUrl: $effectiveUrl,
                message:  'Die URL ist erreichbar.',
                severity: 'ok',
            );
        }

        if ($code >= 300 && $code < 400) {
            return new UrlCheckResult(
                isUsable: false, statusCode: $code, effectiveUrl: $effectiveUrl,
                message:  "Weiterleitungsschleife oder zu viele Weiterleitungen ({$code}). Bitte prüfen Sie die URL.",
                severity: 'error',
            );
        }

        return match(true) {
            $code === 401 => new UrlCheckResult(
                isUsable: false, statusCode: 401, effectiveUrl: $effectiveUrl,
                message:  'Authentifizierung erforderlich (401 Unauthorized). Diese Seite ist nur nach einem Login erreichbar.',
                severity: 'error',
            ),
            $code === 403 => new UrlCheckResult(
                isUsable: $isHtml, statusCode: 403, effectiveUrl: $effectiveUrl,
                message:  $isHtml
                    ? 'Zugriff eingeschränkt (403 Forbidden) — HTML-Inhalt ist dennoch abrufbar, der Monitor kann eingerichtet werden.'
                    : 'Zugriff verweigert (403 Forbidden). Die Seite blockiert automatische Anfragen vollständig.',
                severity: $isHtml ? 'warning' : 'error',
            ),
            $code === 404 => new UrlCheckResult(
                isUsable: false, statusCode: 404, effectiveUrl: $effectiveUrl,
                message:  'Seite nicht gefunden (404 Not Found). Diese URL existiert nicht (mehr). Bitte prüfen Sie die Adresse.',
                severity: 'error',
            ),
            $code === 410 => new UrlCheckResult(
                isUsable: false, statusCode: 410, effectiveUrl: $effectiveUrl,
                message:  'Seite dauerhaft entfernt (410 Gone). Diese URL ist nicht mehr gültig.',
                severity: 'error',
            ),
            $code === 429 => new UrlCheckResult(
                isUsable: false, statusCode: 429, effectiveUrl: $effectiveUrl,
                message:  'Zu viele Anfragen (429 Too Many Requests). Die Seite hat den Zugriff temporär gesperrt — bitte später erneut versuchen.',
                severity: 'error',
            ),
            $code >= 400 && $code < 500 => new UrlCheckResult(
                isUsable: $isHtml, statusCode: $code, effectiveUrl: $effectiveUrl,
                message:  "Clientfehler ({$code})." . ($isHtml
                    ? ' HTML-Inhalt ist dennoch vorhanden, der Monitor kann eingerichtet werden.'
                    : ' Die Seite ist nicht aufrufbar.'),
                severity: $isHtml ? 'warning' : 'error',
            ),
            $code === 500 => new UrlCheckResult(
                isUsable: false, statusCode: 500, effectiveUrl: $effectiveUrl,
                message:  'Interner Serverfehler (500 Internal Server Error). Die Seite ist aktuell fehlerhaft — bitte später erneut versuchen.',
                severity: 'error',
            ),
            $code === 502 => new UrlCheckResult(
                isUsable: false, statusCode: 502, effectiveUrl: $effectiveUrl,
                message:  'Gateway-Fehler (502 Bad Gateway). Der Zielserver ist nicht erreichbar.',
                severity: 'error',
            ),
            $code === 503 => new UrlCheckResult(
                isUsable: false, statusCode: 503, effectiveUrl: $effectiveUrl,
                message:  'Dienst nicht verfügbar (503 Service Unavailable). Die Seite ist vorübergehend offline.',
                severity: 'error',
            ),
            $code === 504 => new UrlCheckResult(
                isUsable: false, statusCode: 504, effectiveUrl: $effectiveUrl,
                message:  'Gateway-Timeout (504). Der Server antwortet zu langsam.',
                severity: 'error',
            ),
            $code >= 500 => new UrlCheckResult(
                isUsable: false, statusCode: $code, effectiveUrl: $effectiveUrl,
                message:  "Serverfehler ({$code}). Die Seite ist aktuell nicht erreichbar.",
                severity: 'error',
            ),
            default => new UrlCheckResult(
                isUsable: $isHtml, statusCode: $code, effectiveUrl: $effectiveUrl,
                message:  "Unbekannter HTTP-Status ({$code})." . ($isHtml ? ' HTML-Inhalt ist vorhanden.' : ''),
                severity: $isHtml ? 'warning' : 'error',
            ),
        };
    }

    private function resolveContentType(\CurlHandle $handle, string $url): string
    {
        $raw = curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        if ($raw === false || $raw === null || $raw === '') {
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
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 10,
        ]);
        return $handle;
    }

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

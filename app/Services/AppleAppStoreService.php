<?php

namespace App\Services;

use App\Dto\VerifiedAppleTransaction;
use App\Exceptions\AppleIapException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AppleAppStoreService
{
    private const PRODUCTION_URL = 'https://api.storekit.apple.com';

    private const SANDBOX_URL = 'https://api.storekit-sandbox.apple.com';

    public function verifyTransaction(string $transactionId): VerifiedAppleTransaction
    {
        $transactionId = trim($transactionId);

        if (! preg_match('/^[A-Za-z0-9._-]{1,255}$/', $transactionId)) {
            throw new AppleIapException('The Apple transaction ID is invalid.');
        }

        $response = $this->getTransaction(self::PRODUCTION_URL, $transactionId);

        if ($this->isTransactionNotFound($response)) {
            $response = $this->getTransaction(self::SANDBOX_URL, $transactionId);
        }

        if (! $response->successful()) {
            throw new AppleIapException(
                'Apple could not verify this transaction.',
                $response->status() >= 500 ? 503 : 422
            );
        }

        $signedTransaction = $response->json('signedTransactionInfo');

        if (! is_string($signedTransaction) || $signedTransaction === '') {
            throw new AppleIapException('Apple returned an invalid transaction response.', 502);
        }

        $payload = $this->verifyAndDecodeJws($signedTransaction);
        $this->validatePayload($payload, $transactionId);

        return new VerifiedAppleTransaction(
            transactionId: (string) $payload['transactionId'],
            originalTransactionId: isset($payload['originalTransactionId']) ? (string) $payload['originalTransactionId'] : null,
            productId: (string) $payload['productId'],
            purchaseDate: Carbon::createFromTimestampMs((int) $payload['purchaseDate'])->utc(),
            environment: Str::lower((string) $payload['environment']),
            appAccountToken: isset($payload['appAccountToken']) ? Str::lower((string) $payload['appAccountToken']) : null,
            revocationDate: isset($payload['revocationDate'])
                ? Carbon::createFromTimestampMs((int) $payload['revocationDate'])->utc()
                : null,
            signedTransactionInfo: $signedTransaction,
        );
    }

    protected function getTransaction(string $baseUrl, string $transactionId): Response
    {
        return Http::acceptJson()
            ->withToken($this->makeBearerToken())
            ->connectTimeout(5)
            ->timeout(15)
            ->retry(2, 250, throw: false)
            ->get($baseUrl.'/inApps/v1/transactions/'.rawurlencode($transactionId));
    }

    private function isTransactionNotFound(Response $response): bool
    {
        return $response->status() === 404 && (int) $response->json('errorCode') === 4040010;
    }

    private function makeBearerToken(): string
    {
        $issuerId = (string) config('services.apple.iap.issuer_id');
        $keyId = (string) config('services.apple.iap.key_id');
        $bundleId = (string) config('services.apple.iap.bundle_id');
        $privateKeyPath = (string) config('services.apple.iap.private_key_path');

        if ($issuerId === '' || $keyId === '' || $bundleId === '' || $privateKeyPath === '') {
            throw new AppleIapException('Apple IAP server credentials are not configured.', 503);
        }

        $resolvedPath = $this->resolvePath($privateKeyPath);
        $privateKey = is_file($resolvedPath) ? file_get_contents($resolvedPath) : false;

        if (! is_string($privateKey) || $privateKey === '') {
            throw new AppleIapException('The Apple IAP private key could not be read.', 503);
        }

        $now = time();
        $header = ['alg' => 'ES256', 'kid' => $keyId, 'typ' => 'JWT'];
        $payload = [
            'iss' => $issuerId,
            'iat' => $now,
            'exp' => $now + 300,
            'aud' => 'appstoreconnect-v1',
            'bid' => $bundleId,
        ];
        $unsigned = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR))
            .'.'.$this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        if (! openssl_sign($unsigned, $derSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new AppleIapException('Unable to sign the Apple API request.', 503);
        }

        return $unsigned.'.'.$this->base64UrlEncode($this->derToJose($derSignature));
    }

    /** @return array<string, mixed> */
    private function verifyAndDecodeJws(string $jws): array
    {
        $parts = explode('.', $jws);

        if (count($parts) !== 3) {
            throw new AppleIapException('Apple returned malformed signed transaction data.', 502);
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = json_decode($this->base64UrlDecode($encodedHeader), true, flags: JSON_THROW_ON_ERROR);
        $payload = json_decode($this->base64UrlDecode($encodedPayload), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($header) || ! is_array($payload) || ($header['alg'] ?? null) !== 'ES256') {
            throw new AppleIapException('Apple returned unsupported signed transaction data.', 502);
        }

        $chain = $header['x5c'] ?? null;
        if (! is_array($chain) || $chain === []) {
            throw new AppleIapException('The Apple certificate chain is missing.', 502);
        }

        $certificates = array_map(
            fn (string $certificate) => "-----BEGIN CERTIFICATE-----\n".chunk_split($certificate, 64, "\n")."-----END CERTIFICATE-----\n",
            $chain
        );

        $this->verifyCertificateChain($certificates);

        $rawSignature = $this->base64UrlDecode($encodedSignature);
        if (strlen($rawSignature) !== 64) {
            throw new AppleIapException('The Apple transaction signature is invalid.', 502);
        }

        $verified = openssl_verify(
            $encodedHeader.'.'.$encodedPayload,
            $this->joseToDer($rawSignature),
            $certificates[0],
            OPENSSL_ALGO_SHA256
        );

        if ($verified !== 1) {
            throw new AppleIapException('The Apple transaction signature could not be verified.', 422);
        }

        return $payload;
    }

    /** @param list<string> $certificates */
    private function verifyCertificateChain(array $certificates): void
    {
        $rootPaths = array_map(fn (string $path) => $this->resolvePath($path), config('services.apple.iap.root_ca_paths', []));

        if ($rootPaths === [] || collect($rootPaths)->contains(fn (string $path) => ! is_file($path))) {
            throw new AppleIapException('Trusted Apple root certificates are not configured.', 503);
        }

        $untrustedFile = tempnam(sys_get_temp_dir(), 'apple-iap-chain-');
        $trustedFile = tempnam(sys_get_temp_dir(), 'apple-iap-roots-');

        if ($untrustedFile === false || $trustedFile === false) {
            throw new AppleIapException('Unable to prepare Apple certificate verification.', 503);
        }

        try {
            file_put_contents($untrustedFile, implode("\n", array_slice($certificates, 1)));

            $trustedCertificates = '';
            foreach ($rootPaths as $rootPath) {
                $rootContents = file_get_contents($rootPath);
                if (! is_string($rootContents) || $rootContents === '') {
                    throw new AppleIapException('A configured Apple root certificate could not be read.', 503);
                }
                if (! str_contains($rootContents, '-----BEGIN CERTIFICATE-----')) {
                    $rootContents = "-----BEGIN CERTIFICATE-----\n"
                        .chunk_split(base64_encode($rootContents), 64, "\n")
                        ."-----END CERTIFICATE-----\n";
                }

                $root = openssl_x509_read($rootContents);
                if ($root === false || ! openssl_x509_export($root, $rootPem)) {
                    throw new AppleIapException('A configured Apple root certificate is invalid.', 503);
                }
                $trustedCertificates .= $rootPem."\n";
            }
            file_put_contents($trustedFile, $trustedCertificates);

            $result = openssl_x509_checkpurpose(
                $certificates[0],
                X509_PURPOSE_ANY,
                [$trustedFile],
                $untrustedFile
            );
        } finally {
            @unlink($untrustedFile);
            @unlink($trustedFile);
        }

        if ($result !== true && $result !== 1) {
            throw new AppleIapException('The Apple certificate chain is not trusted.', 422);
        }
    }

    /** @param array<string, mixed> $payload */
    private function validatePayload(array $payload, string $requestedTransactionId): void
    {
        foreach (['transactionId', 'productId', 'purchaseDate', 'bundleId', 'environment', 'type'] as $field) {
            if (! isset($payload[$field])) {
                throw new AppleIapException("The Apple transaction is missing {$field}.", 502);
            }
        }

        if (! hash_equals($requestedTransactionId, (string) $payload['transactionId'])) {
            throw new AppleIapException('The returned Apple transaction does not match the requested transaction.');
        }

        if (! hash_equals((string) config('services.apple.iap.bundle_id'), (string) $payload['bundleId'])) {
            throw new AppleIapException('The Apple transaction belongs to a different app.');
        }

        if ((string) $payload['type'] !== 'Non-Renewing Subscription') {
            throw new AppleIapException('The Apple transaction is not a non-renewing subscription.');
        }

        $environment = Str::lower((string) $payload['environment']);
        if (! in_array($environment, ['production', 'sandbox'], true)) {
            throw new AppleIapException('The Apple transaction environment is invalid.');
        }

        $appAppleId = (string) config('services.apple.iap.app_apple_id');
        if ($environment === 'production' && $appAppleId !== '' && (string) ($payload['appAppleId'] ?? '') !== $appAppleId) {
            throw new AppleIapException('The Apple transaction belongs to a different App Store app.');
        }

        if (! is_numeric($payload['purchaseDate']) || (int) $payload['purchaseDate'] <= 0) {
            throw new AppleIapException('The Apple purchase date is invalid.');
        }

        if (isset($payload['revocationDate'])) {
            throw new AppleIapException('This Apple purchase has been refunded or revoked.');
        }
    }

    private function resolvePath(string $path): string
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) ? $path : base_path($path);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new AppleIapException('Apple returned invalid base64 data.', 502);
        }

        return $decoded;
    }

    private function derToJose(string $der): string
    {
        $offset = 0;
        if (ord($der[$offset++]) !== 0x30) {
            throw new AppleIapException('Unable to encode the Apple API signature.', 503);
        }
        $this->readDerLength($der, $offset);
        if (ord($der[$offset++]) !== 0x02) {
            throw new AppleIapException('Unable to encode the Apple API signature.', 503);
        }
        $rLength = $this->readDerLength($der, $offset);
        $r = substr($der, $offset, $rLength);
        $offset += strlen($r);
        if (ord($der[$offset++]) !== 0x02) {
            throw new AppleIapException('Unable to encode the Apple API signature.', 503);
        }
        $sLength = $this->readDerLength($der, $offset);
        $s = substr($der, $offset, $sLength);

        return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT)
            .str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    private function readDerLength(string $der, int &$offset): int
    {
        $length = ord($der[$offset++]);
        if (($length & 0x80) === 0) {
            return $length;
        }
        $bytes = $length & 0x7F;
        $length = 0;
        while ($bytes-- > 0) {
            $length = ($length << 8) | ord($der[$offset++]);
        }

        return $length;
    }

    private function joseToDer(string $signature): string
    {
        $encodeInteger = function (string $integer): string {
            $integer = ltrim($integer, "\0") ?: "\0";
            if ((ord($integer[0]) & 0x80) !== 0) {
                $integer = "\0".$integer;
            }

            return "\x02".chr(strlen($integer)).$integer;
        };

        $sequence = $encodeInteger(substr($signature, 0, 32)).$encodeInteger(substr($signature, 32, 32));

        return "\x30".chr(strlen($sequence)).$sequence;
    }
}

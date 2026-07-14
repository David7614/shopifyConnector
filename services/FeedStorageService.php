<?php
namespace app\services;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

/**
 * S3-compatible object storage for XML feeds (Stackhero MinIO).
 * Required env vars: STACKHERO_MINIO_HOST, STACKHERO_MINIO_ROOT_ACCESS_KEY, STACKHERO_MINIO_ROOT_SECRET_KEY
 * Optional env vars: MINIO_BUCKET (default: feeds), FEEDS_PATH (prefix within bucket, default: shopify)
 */
class FeedStorageService
{
    private S3Client $s3;
    private string $bucket;
    private string $prefix;

    /** @var array<string,true>|null  Request-level cache of existing keys */
    private static ?array $keysCache = null;

    public function __construct(S3Client $s3, string $bucket, string $prefix = '')
    {
        $this->s3     = $s3;
        $this->bucket = $bucket;
        $this->prefix = $prefix ? rtrim($prefix, '/') . '/' : '';
    }

    public static function isConfigured(): bool
    {
        return (bool) getenv('STACKHERO_MINIO_HOST');
    }

    public static function create(): self
    {
        $host   = getenv('STACKHERO_MINIO_HOST');
        $key    = getenv('STACKHERO_MINIO_ROOT_ACCESS_KEY');
        $secret = getenv('STACKHERO_MINIO_ROOT_SECRET_KEY');
        $bucket = getenv('MINIO_BUCKET') ?: 'feeds';
        $prefix = getenv('FEEDS_PATH') ?: 'shopify';
        $region = 'us-east-1';

        if (!$host || !$key || !$secret) {
            throw new \RuntimeException('MinIO not configured. Set STACKHERO_MINIO_HOST, STACKHERO_MINIO_ROOT_ACCESS_KEY, STACKHERO_MINIO_ROOT_SECRET_KEY env vars.');
        }

        $endpoint = 'https://' . $host;

        $s3 = new S3Client([
            'version'                 => 'latest',
            'region'                  => $region,
            'endpoint'                => $endpoint,
            'use_path_style_endpoint' => true,
            'credentials'             => [
                'key'    => $key,
                'secret' => $secret,
            ],
        ]);

        return new self($s3, $bucket, $prefix);
    }

    public function exists(string $key): bool
    {
        return $this->s3->doesObjectExist($this->bucket, $this->prefix . $key);
    }

    /**
     * Cached version of exists() — loads all feed keys once per request.
     */
    public function existsCached(string $key): bool
    {
        if (self::$keysCache === null) {
            $this->warmCache();
        }
        return isset(self::$keysCache[$this->prefix . $key]);
    }

    public function invalidateCache(): void
    {
        self::$keysCache = null;
    }

    private function warmCache(): void
    {
        self::$keysCache = [];
        $types = ['product', 'order', 'customer'];

        foreach ($types as $type) {
            $params = ['Bucket' => $this->bucket, 'Prefix' => $this->prefix . $type . '/'];

            do {
                $result = $this->s3->listObjectsV2($params);
                foreach ($result['Contents'] ?? [] as $object) {
                    self::$keysCache[$object['Key']] = true;
                }
                $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
            } while ($result['IsTruncated']);
        }
    }

    public function get(string $key): string
    {
        $result = $this->s3->getObject([
            'Bucket' => $this->bucket,
            'Key'    => $this->prefix . $key,
        ]);
        return (string) $result['Body'];
    }

    /**
     * Returns [size, resource] for the S3 object so the caller can set headers
     * before streaming. Caller is responsible for fclose($resource).
     *
     * @return array{0: int, 1: resource}
     */
    public function getStream(string $key): array
    {
        $result = $this->s3->getObject([
            'Bucket' => $this->bucket,
            'Key'    => $this->prefix . $key,
        ]);

        return [(int) $result['ContentLength'], $result['Body']->detach()];
    }

    public function put(string $key, string $content, string $contentType = 'application/octet-stream'): void
    {
        $this->s3->putObject([
            'Bucket'      => $this->bucket,
            'Key'         => $this->prefix . $key,
            'Body'        => $content,
            'ContentType' => $contentType,
        ]);
    }

    public function append(string $key, string $additionalContent): void
    {
        $existing = $this->exists($key) ? $this->get($key) : '';
        $this->put($key, $existing . $additionalContent);
    }

    public function delete(string $key): void
    {
        try {
            $this->s3->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->prefix . $key,
            ]);
        } catch (S3Exception $e) {
            // ignore if not found
        }
    }

    /**
     * Starts a multipart upload and returns its UploadId.
     * Lets large objects be assembled from parts without holding the whole
     * content in memory at once.
     */
    public function createMultipartUpload(string $key, string $contentType = 'application/octet-stream'): string
    {
        $result = $this->s3->createMultipartUpload([
            'Bucket'      => $this->bucket,
            'Key'         => $this->prefix . $key,
            'ContentType' => $contentType,
        ]);

        return (string) $result['UploadId'];
    }

    /**
     * Uploads one part of a multipart upload. Every part except the last
     * must be at least 5 MB (S3/MinIO requirement).
     *
     * @return string The part's ETag, needed to complete the upload.
     */
    public function uploadPart(string $key, string $uploadId, int $partNumber, string $body): string
    {
        $result = $this->s3->uploadPart([
            'Bucket'     => $this->bucket,
            'Key'        => $this->prefix . $key,
            'UploadId'   => $uploadId,
            'PartNumber' => $partNumber,
            'Body'       => $body,
        ]);

        return (string) $result['ETag'];
    }

    /**
     * @param array<int, array{PartNumber: int, ETag: string}> $parts
     */
    public function completeMultipartUpload(string $key, string $uploadId, array $parts): void
    {
        $this->s3->completeMultipartUpload([
            'Bucket'          => $this->bucket,
            'Key'             => $this->prefix . $key,
            'UploadId'        => $uploadId,
            'MultipartUpload' => ['Parts' => $parts],
        ]);
    }

    public function abortMultipartUpload(string $key, string $uploadId): void
    {
        try {
            $this->s3->abortMultipartUpload([
                'Bucket'   => $this->bucket,
                'Key'      => $this->prefix . $key,
                'UploadId' => $uploadId,
            ]);
        } catch (S3Exception $e) {
            // ignore if already gone
        }
    }

    /**
     * Aborts any multipart upload left incomplete by a previous attempt that
     * got interrupted mid-assembly, so retries don't pile up abandoned
     * uploads (and their storage cost) on every crash.
     */
    public function abortStaleMultipartUploads(string $key): void
    {
        try {
            $result = $this->s3->listMultipartUploads([
                'Bucket' => $this->bucket,
                'Prefix' => $this->prefix . $key,
            ]);

            foreach ($result['Uploads'] ?? [] as $upload) {
                if ($upload['Key'] === $this->prefix . $key) {
                    $this->abortMultipartUpload($key, $upload['UploadId']);
                }
            }
        } catch (S3Exception $e) {
            // best-effort cleanup; a stale upload left behind isn't fatal
        }
    }
}

<?php

namespace KalnaLab\FlysystemMicrosoftGraph\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SharePointDownloadController
{
    /**
     * Download file from SharePoint using signed URL
     * 
     * URL format: /sharepoint/download/{itemId}?expires={timestamp}&signature={hash}
     */
    public function __invoke(Request $request, string $itemId): StreamedResponse
    {
        // Validate signature and expiration
        $this->validateSignature($request, $itemId);

        // Get file from SharePoint using item ID
        $disk = Storage::disk('sharepoint');
        
        // Get file metadata from SharePoint
        $file = $this->getFileByItemId($itemId, $disk);
        
        if (!$file) {
            abort(404, 'File not found');
        }

        // Stream file from SharePoint
        $stream = $disk->readStream($file['path']);
        
        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $file['mime_type'] ?? 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $file['name'] . '"',
            'Content-Length' => $file['size'] ?? 0,
        ]);
    }

    /**
     * Validate URL signature and expiration
     */
    private function validateSignature(Request $request, string $itemId): void
    {
        $expires = $request->query('expires');
        $signature = $request->query('signature');

        // Check required parameters
        if (!$expires || !$signature) {
            abort(403, 'Invalid URL');
        }

        // Check expiration
        if (time() > $expires) {
            abort(403, 'URL has expired');
        }

        // Validate signature
        $validSignature = hash_hmac('sha256', $itemId . $expires, config('app.key'));
        
        if (!hash_equals($validSignature, $signature)) {
            abort(403, 'Invalid signature');
        }
    }

    /**
     * Get file metadata by SharePoint item ID
     */
    private function getFileByItemId(string $itemId, $disk): ?array
    {
        try {
            // Get adapter to access Graph API directly
            $adapter = $disk->getAdapter();
            
            // Access the underlying MicrosoftGraphAdapter
            // The FilesystemAdapter wraps our adapter, so we need to get it
            if (method_exists($adapter, 'getAdapter')) {
                $graphAdapter = $adapter->getAdapter();
            } else {
                $graphAdapter = $adapter;
            }

            // Get item metadata from SharePoint using getter methods
            $graphClient = $graphAdapter->getGraphClient();
            $driveId = $graphAdapter->getDriveId();
            
            $response = $graphClient->createRequest('GET', "/drives/{$driveId}/items/{$itemId}")
                ->execute();

            if (!isset($response['name'])) {
                return null;
            }

            // Extract path from parentReference
            $parentPath = $response['parentReference']['path'] ?? '';
            $parentPath = preg_replace('#^/drives/[^/]+/root:#', '', $parentPath);
            
            // Handle prefix if set
            $path = ltrim($parentPath . '/' . $response['name'], '/');
            
            // Strip prefix if adapter has one
            $prefixer = $graphAdapter->getPrefixer();
            $path = $prefixer->stripPrefix($path);

            return [
                'path' => $path,
                'name' => $response['name'],
                'size' => $response['size'] ?? 0,
                'mime_type' => $response['file']['mimeType'] ?? 'application/octet-stream',
            ];

        } catch (\Exception $e) {
            \Log::error('Failed to get file by item ID', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

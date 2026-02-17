<?php

namespace KalnaLab\FlysystemMicrosoftGraph\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SharePointDownloadController extends SharePointViewController
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
}

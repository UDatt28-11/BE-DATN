<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Illuminate\Support\Facades\Auth;

class GoogleDriveService
{
    protected $client;
    protected $service;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setClientId(env('GOOGLE_CLIENT_ID'));
        $this->client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $this->client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        $this->client->addScope(Drive::DRIVE_FILE);

        $user = Auth::user();
        if (!$user) {
            throw new \RuntimeException('User not authenticated');
        }

        $token = $user->googleToken;
        if (!$token) {
            throw new \RuntimeException('Google token not found for user');
        }

        $this->client->setAccessToken([
            'access_token' => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expires_in' => $token->expires_at?->diffInSeconds() ?? 0,
        ]);

        // TỰ ĐỘNG REFRESH TOKEN
        if ($this->client->isAccessTokenExpired() && $token->refresh_token) {
            $newToken = $this->client->fetchAccessTokenWithRefreshToken($token->refresh_token);
            if (isset($newToken['access_token'])) {
                $token->update([
                    'access_token' => $newToken['access_token'],
                    'expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
                ]);
            }
        }

        $this->service = new Drive($this->client);
    }

    public function upload($filePath, $fileName)
    {
        $file = new DriveFile();
        $file->setName($fileName);

        $created = $this->service->files->create($file, [
            'data' => file_get_contents($filePath),
            'mimeType' => mime_content_type($filePath),
            'uploadType' => 'multipart',
            'fields' => 'id,name,mimeType,size,webViewLink',
        ]);

        // Mặc định tạo quyền xem bằng link (nếu cần public)
        try {
            $permission = new Permission();
            $permission->setType('anyone');
            $permission->setRole('reader');
            $this->service->permissions->create($created->getId(), $permission);
        } catch (\Throwable $e) {
            // Không chặn nếu gán quyền thất bại
        }

        // Lấy lại metadata có webViewLink
        $fileMeta = $this->service->files->get($created->getId(), [
            'fields' => 'id,name,mimeType,size,webViewLink',
        ]);

        return $fileMeta;
    }

    public function listFiles()
    {
        $results = $this->service->files->listFiles([
            'q' => "mimeType != 'application/vnd.google-apps.folder'",
            'fields' => 'files(id,name,webViewLink,size,mimeType)',
            'pageSize' => 100,
        ]);

        return $results->getFiles();
    }

    public function getFileIdByName($fileName)
    {
        $results = $this->service->files->listFiles([
            'q' => "name='$fileName'",
            'fields' => 'files(id)',
        ]);

        $files = $results->getFiles();
        return !empty($files) ? $files[0]->getId() : null;
    }

    public function downloadFile($fileId)
    {
        $request = $this->service->files->get($fileId, ['alt' => 'media']);
        $content = $request->getResponseBody();

        $file = $this->service->files->get($fileId, ['fields' => 'mimeType, name']);
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';

        return [
            'content' => $content,
            'mime' => $mimeType,
            'name' => $file->getName(),
        ];
    }

    public function deleteFile(string $fileId): void
    {
        if (!$fileId) {
            return; // nothing to delete
        }
        try {
            $this->service->files->delete($fileId);
        } catch (\Google\Service\Exception $e) {
            // Ignore notFound errors to keep idempotent behavior
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }
}

<?php
/**
 * File Upload Functions
 */

function mimeToImageExtension($mimeType)
{
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            return 'jpg';
        case 'image/png':
            return 'png';
        case 'image/webp':
            return 'webp';
        default:
            return null;
    }
}

function uploadDeliveryPhoto(array $file, int $parcelId, int $riderId): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload failed. Please try again.'];
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds maximum allowed (5MB).'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, WEBP.'];
    }

    $extension = mimeToImageExtension($mimeType);

    if (!$extension) {
        return ['success' => false, 'message' => 'Unsupported image format.'];
    }

    $uploadDir = UPLOAD_DIR;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = 'proof_' . $parcelId . '_' . $riderId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'message' => 'Failed to save uploaded file.'];
    }

    $relativePath = 'uploads/delivery_proofs/' . $fileName;

    $stmt = db()->prepare(
        'INSERT INTO delivery_photos (parcel_id, rider_id, file_path, file_name, file_size, mime_type)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$parcelId, $riderId, $relativePath, $fileName, $file['size'], $mimeType]);

    logActivity(currentUserId(), 'photo_upload', "Uploaded delivery proof for parcel ID $parcelId");

    return [
        'success' => true,
        'message' => 'Photo uploaded successfully.',
        'file_path' => $relativePath,
        'photo_id' => (int) db()->lastInsertId()
    ];
}

function uploadProfilePhoto(array $file, int $userId): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload failed.'];
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Profile photo must be under 2MB.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
        return ['success' => false, 'message' => 'Invalid image type.'];
    }

    $extension = mimeToImageExtension($mimeType);

    if (!$extension) {
        return ['success' => false, 'message' => 'Invalid image type.'];
    }

    $uploadDir = __DIR__ . '/../uploads/profiles/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = 'profile_' . $userId . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'message' => 'Failed to save photo.'];
    }

    $relativePath = 'uploads/profiles/' . $fileName;

    $stmt = db()->prepare('UPDATE users SET profile_photo = ? WHERE id = ?');
    $stmt->execute([$relativePath, $userId]);

    logActivity($userId, 'profile_photo', 'Profile photo updated');

    return ['success' => true, 'message' => 'Profile photo updated.', 'file_path' => $relativePath];
}

function handleBase64ImageUpload(string $base64Data, int $parcelId, int $riderId): array
{
    if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $base64Data, $matches)) {
        return ['success' => false, 'message' => 'Invalid image data.'];
    }

    $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
    $imageData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $base64Data));

    if ($imageData === false) {
        return ['success' => false, 'message' => 'Failed to decode image.'];
    }

    if (strlen($imageData) > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'message' => 'Image too large.'];
    }

    $uploadDir = UPLOAD_DIR;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = 'proof_' . $parcelId . '_' . $riderId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $filePath = $uploadDir . $fileName;

    if (file_put_contents($filePath, $imageData) === false) {
        return ['success' => false, 'message' => 'Failed to save image.'];
    }

    $mimeType = 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension);
    $relativePath = 'uploads/delivery_proofs/' . $fileName;

    $stmt = db()->prepare(
        'INSERT INTO delivery_photos (parcel_id, rider_id, file_path, file_name, file_size, mime_type)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$parcelId, $riderId, $relativePath, $fileName, strlen($imageData), $mimeType]);

    logActivity(currentUserId(), 'photo_upload', "Uploaded delivery proof for parcel ID $parcelId");

    return ['success' => true, 'message' => 'Photo uploaded.', 'file_path' => $relativePath];
}

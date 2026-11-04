<?php
/**
 * LASU FCIT Secure Examination Question Management System
 * Profile Picture & Avatar Service
 */

class ProfileService {

    /**
     * Upload and process a new profile picture.
     * Enforces limits, valid MIME types, and crops to square.
     *
     * @param int $userId
     * @param array $file $_FILES['profile_picture']
     * @throws Exception
     */
    public static function uploadProfilePicture(int $userId, array $file): void {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Upload failed with error code: " . $file['error']);
        }

        if ($file['size'] > MAX_PROFILE_PIC_SIZE_BYTES) {
            throw new Exception("File is too large. Maximum size is 5MB.");
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            throw new Exception("Uploaded file is not a valid image.");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($file['tmp_name']);
        $mime = $info['mime'];

        if ($detectedMime !== $mime || !in_array($mime, ALLOWED_PROFILE_PIC_MIMES, true)) {
            throw new Exception("Invalid image type. Only JPEG, PNG, and WebP are allowed.");
        }

        if (!is_dir(PROFILE_PICTURES_STORAGE_PATH)) {
            mkdir(PROFILE_PICTURES_STORAGE_PATH, 0755, true);
        }

        $ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg');
        $filename = bin2hex(random_bytes(16)) . '_' . time() . '.' . $ext;
        $destPath = PROFILE_PICTURES_STORAGE_PATH . '/' . $filename;
        $saved = false;

        if (extension_loaded('gd')) {
            // Process Image with GD (strips embedded metadata/polyglot payloads)
            $srcImage = null;
            switch ($mime) {
                case 'image/jpeg':
                    $srcImage = @imagecreatefromjpeg($file['tmp_name']);
                    break;
                case 'image/png':
                    $srcImage = @imagecreatefrompng($file['tmp_name']);
                    break;
                case 'image/webp':
                    $srcImage = @imagecreatefromwebp($file['tmp_name']);
                    break;
            }

            if (!$srcImage) {
                throw new Exception("Uploaded image could not be decoded safely.");
            }

            $width = imagesx($srcImage);
            $height = imagesy($srcImage);
            
            // Crop to square
            $minSize = min($width, $height);
            $cropX = (int)(($width - $minSize) / 2);
            $cropY = (int)(($height - $minSize) / 2);

            $targetSize = min($minSize, 500); // Max 500x500

            $dstImage = imagecreatetruecolor($targetSize, $targetSize);
            
            // Handle transparency for PNG/WebP
            if ($mime === 'image/png' || $mime === 'image/webp') {
                imagealphablending($dstImage, false);
                imagesavealpha($dstImage, true);
                $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
                imagefilledrectangle($dstImage, 0, 0, $targetSize, $targetSize, $transparent);
            }

            imagecopyresampled(
                $dstImage, $srcImage,
                0, 0,
                $cropX, $cropY,
                $targetSize, $targetSize,
                $minSize, $minSize
            );

            if ($ext === 'png') {
                $saved = imagepng($dstImage, $destPath);
            } elseif ($ext === 'webp') {
                $saved = imagewebp($dstImage, $destPath, 85);
            } else {
                $saved = imagejpeg($dstImage, $destPath, 85);
            }

            imagedestroy($srcImage);
            imagedestroy($dstImage);
        } else {
            // Fallback only if GD extension is not installed on host
            $saved = move_uploaded_file($file['tmp_name'], $destPath);
        }

        if (!$saved) {
            throw new Exception("Failed to save the processed profile picture.");
        }

        $pdo = getDb();
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $oldPic = $stmt->fetchColumn();

        // Update DB
        $stmtUpd = $pdo->prepare("UPDATE users SET profile_picture = :pic, updated_at = NOW() WHERE id = :id");
        $stmtUpd->execute(['pic' => $filename, 'id' => $userId]);

        // Update active session if uploading for current user
        if (isset($_SESSION['user_data']) && (int)$_SESSION['user_id'] === $userId) {
            $_SESSION['user_data']['profile_picture'] = $filename;
        }

        // Remove old pic safely
        if (!empty($oldPic)) {
            $oldPath = PROFILE_PICTURES_STORAGE_PATH . '/' . basename($oldPic);
            if (file_exists($oldPath) && is_file($oldPath)) {
                @unlink($oldPath);
            }
        }
    }

    /**
     * Remove the user's profile picture and revert to initials.
     *
     * @param int $userId
     */
    public static function removeProfilePicture(int $userId): void {
        $pdo = getDb();
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $oldPic = $stmt->fetchColumn();

        if (!empty($oldPic)) {
            $oldPath = PROFILE_PICTURES_STORAGE_PATH . '/' . basename($oldPic);
            if (file_exists($oldPath) && is_file($oldPath)) {
                @unlink($oldPath);
            }

            $stmtUpd = $pdo->prepare("UPDATE users SET profile_picture = NULL, updated_at = NOW() WHERE id = :id");
            $stmtUpd->execute(['id' => $userId]);

            if (isset($_SESSION['user_data']) && (int)$_SESSION['user_id'] === $userId) {
                $_SESSION['user_data']['profile_picture'] = null;
            }
        }
    }
}

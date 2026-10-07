<?php
/*
 * includes/image_upload.php
 * ---------------------------------------------------------------
 * Uploads product pictures to Supabase Storage.
 *
 * Bucket:
 *   product-images
 *
 * The database continues to store only the image file name.
 */

require_once __DIR__ . '/functions.php';

define('SUPABASE_STORAGE_BUCKET', 'product-images');

/**
 * Upload product image to Supabase Storage.
 *
 * @param array  $file      one entry of $_FILES
 * @param string $baseName  product name, used to create a safe filename
 * @return string|null      saved file name, or null if no image was selected
 * @throws AppException
 */
function save_product_image(array $file, string $baseName): ?string
{
    // No image selected.
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'The image is too large for the server settings.',
            UPLOAD_ERR_FORM_SIZE  => 'The image is too large.',
            UPLOAD_ERR_PARTIAL    => 'The image was only partly uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder for uploads.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not save the image.',
        ];

        throw new AppException(
            $messages[$file['error']] ?? 'The image could not be uploaded.'
        );
    }

    // Keep the existing 2 MB limit.
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new AppException('Please choose an image smaller than 2 MB.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new AppException('Invalid upload.');
    }

    // Make sure the file is a real image.
    $info = @getimagesize($file['tmp_name']);

    if ($info === false) {
        throw new AppException(
            'That file is not a valid image. Please use a JPG, PNG, WEBP or GIF picture.'
        );
    }

    $allowed = [
        IMAGETYPE_JPEG => [
            'ext'  => 'jpg',
            'mime' => 'image/jpeg',
        ],
        IMAGETYPE_PNG => [
            'ext'  => 'png',
            'mime' => 'image/png',
        ],
        IMAGETYPE_GIF => [
            'ext'  => 'gif',
            'mime' => 'image/gif',
        ],
        IMAGETYPE_WEBP => [
            'ext'  => 'webp',
            'mime' => 'image/webp',
        ],
    ];

    if (!isset($allowed[$info[2]])) {
        throw new AppException(
            'Only JPG, PNG, WEBP and GIF images are allowed.'
        );
    }

    $ext  = $allowed[$info[2]]['ext'];
    $mime = $allowed[$info[2]]['mime'];

    // Create our own safe filename.
    $slug = strtolower(
        preg_replace('/[^A-Za-z0-9]+/', '-', $baseName)
    );

    $slug = trim($slug, '-');

    if ($slug === '') {
        $slug = 'product';
    }

    $name = substr($slug, 0, 40)
        . '-'
        . date('YmdHis')
        . '-'
        . bin2hex(random_bytes(2))
        . '.'
        . $ext;

    /*
     * Read the temporary uploaded file.
     * Vercel's temporary filesystem is only used here while processing
     * the upload. The final image goes to Supabase Storage.
     */
    $contents = @file_get_contents($file['tmp_name']);

    if ($contents === false) {
        throw new AppException('The uploaded image could not be read.');
    }

    $supabaseUrl = rtrim((string) getenv('SUPABASE_URL'), '/');
    $serviceKey  = (string) getenv('SUPABASE_SERVICE_ROLE_KEY');

    if ($supabaseUrl === '' || $serviceKey === '') {
        throw new AppException(
            'Supabase Storage is not configured on the server.'
        );
    }

    /*
     * Supabase Storage upload endpoint.
     *
     * The image is stored in:
     * product-images/<filename>
     */
    $url = $supabaseUrl
        . '/storage/v1/object/'
        . SUPABASE_STORAGE_BUCKET
        . '/'
        . rawurlencode($name);

    $ch = curl_init($url);

    if ($ch === false) {
        throw new AppException('Could not start the image upload.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $contents,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $serviceKey,
            'apikey: ' . $serviceKey,
            'Content-Type: ' . $mime,
            'x-upsert: true',
            'Cache-Control: public, max-age=31536000, immutable',
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);

    curl_close($ch);

    if ($response === false || $curlErr !== '') {
        throw new AppException(
            'The image could not be uploaded to Supabase Storage.'
        );
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log(
            '[QuickCart] Supabase Storage upload failed. HTTP '
            . $httpCode
            . ': '
            . $response
        );

        throw new AppException(
            'The image could not be saved to Supabase Storage.'
        );
    }

    // Return only the filename. This is what the products table stores.
    return $name;
}


/**
 * Deletes a product picture from Supabase Storage.
 *
 * Sample SVG images shipped with QuickCart are never deleted.
 */
function delete_product_image(?string $file): void
{
    $file = basename((string) $file);

    if (
        $file === ''
        || $file === 'placeholder.svg'
        || strtolower(substr($file, -4)) === '.svg'
    ) {
        return;
    }

    $supabaseUrl = rtrim((string) getenv('SUPABASE_URL'), '/');
    $serviceKey  = (string) getenv('SUPABASE_SERVICE_ROLE_KEY');

    if ($supabaseUrl === '' || $serviceKey === '') {
        return;
    }

    $url = $supabaseUrl
        . '/storage/v1/object/'
        . SUPABASE_STORAGE_BUCKET
        . '/'
        . rawurlencode($file);

    $ch = curl_init($url);

    if ($ch === false) {
        return;
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $serviceKey,
            'apikey: ' . $serviceKey,
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    curl_exec($ch);

    curl_close($ch);
}
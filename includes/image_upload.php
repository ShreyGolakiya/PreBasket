<?php
/*
 * includes/image_upload.php
 * ---------------------------------------------------------------
 * Saves a product picture uploaded from the admin panel into
 * /assets/images/products/ and returns the new file name.
 *
 * Safety rules used here:
 *   - only real image files are accepted (checked with getimagesize)
 *   - only jpg / png / webp / gif are allowed
 *     (SVG is NOT accepted from an upload because an SVG file can
 *      contain JavaScript. The sample SVGs shipped with the project
 *      were written by us, so those are safe.)
 *   - the file name is created by us, never taken from the user
 *   - maximum size 2 MB
 */
require_once __DIR__ . '/functions.php';

define('PRODUCT_IMG_DIR', __DIR__ . '/../assets/images/products/');

/**
 * @param array  $file      one entry of $_FILES
 * @param string $baseName  product name, used to build a tidy file name
 * @return string|null      the saved file name, or null if nothing was uploaded
 * @throws AppException      when the file is not a valid image
 */
function save_product_image(array $file, string $baseName): ?string
{
    // nothing chosen - that is fine, the caller keeps the old picture
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
        throw new AppException($messages[$file['error']] ?? 'The image could not be uploaded.');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        throw new AppException('Please choose an image smaller than 2 MB.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new AppException('Invalid upload.');
    }

    // getimagesize() returns false for anything that is not a real picture
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new AppException('That file is not a valid image. Please use a JPG, PNG, WEBP or GIF picture.');
    }

    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    if (!isset($allowed[$info[2]])) {
        throw new AppException('Only JPG, PNG, WEBP and GIF images are allowed.');
    }
    $ext = $allowed[$info[2]];

    // we build the file name ourselves, so a user can never choose it
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $baseName));
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'product';
    }
    $name = substr($slug, 0, 40) . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(2)) . '.' . $ext;

    if (!is_dir(PRODUCT_IMG_DIR)) {
        @mkdir(PRODUCT_IMG_DIR, 0777, true);
    }
    if (!move_uploaded_file($file['tmp_name'], PRODUCT_IMG_DIR . $name)) {
        throw new AppException('The image could not be saved. Check that the products folder is writable.');
    }

    return $name;
}

/**
 * Deletes an old uploaded picture.
 * The SVG files that came with the project are never deleted, because
 * several products may share them and they are part of the sample data.
 */
function delete_product_image(?string $file): void
{
    $file = basename((string) $file);
    if ($file === '' || $file === 'placeholder.svg' || strtolower(substr($file, -4)) === '.svg') {
        return;
    }
    $path = PRODUCT_IMG_DIR . $file;
    if (is_file($path)) {
        @unlink($path);
    }
}

<?php
// photo uploads: checking and saving JPG/PNG files (product photos and profile pictures)
// the file type is read from the file itself, not from what the browser claims

// why an uploaded photo cannot be used, or '' when it is fine.
// $required: whether "no file chosen" counts as an error
function photo_upload_error($file, $required = true)
{
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
        return $required ? 'Please choose a photo.' : '';
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return $required ? 'Please choose a photo.' : '';
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > MAX_PHOTO_BYTES) {
        return 'The photo is too large. The limit is ' . (MAX_PHOTO_BYTES / 1024 / 1024) . ' MB.';
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return 'The photo could not be uploaded. Please try again.';
    }
    if (photo_extension($file['tmp_name']) === '') {
        return 'Only JPG and PNG photos are accepted.';
    }
    return '';
}

// '.jpg' or '.png' when the file really is such an image, otherwise ''
function photo_extension($path)
{
    $info = @getimagesize($path);
    if ($info === false) {
        return '';
    }
    if ($info[2] === IMAGETYPE_JPEG) {
        return '.jpg';
    }
    if ($info[2] === IMAGETYPE_PNG) {
        return '.png';
    }
    return '';
}

// move a checked upload into $folder (an absolute path) under a new random name.
// returns the new file name, or '' if it could not be saved
function save_photo($file, $folder)
{
    if (!is_dir($folder) && !@mkdir($folder, 0775, true)) {
        return '';
    }
    $name = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . photo_extension($file['tmp_name']);
    return move_uploaded_file($file['tmp_name'], $folder . '/' . $name) ? $name : '';
}

// a <input type="file" name="photos[]" multiple> arrives as one array per property;
// turn it into a list of single files, skipping the empty slots
function uploaded_files_list($files)
{
    $list = array();
    if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
        return $list;
    }
    foreach (array_keys($files['name']) as $i) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $list[] = array(
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        );
    }
    return $list;
}

// delete a photo file, but never the sample photos that come with the project
function delete_photo_file($path)
{
    if ($path !== '' && is_file($path) && strpos(basename($path), 'sample_') !== 0) {
        @unlink($path);
    }
}

// the full path of a file stored as a path from the project folder, e.g. "user/images/x.jpg"
function app_file($relativePath)
{
    return dirname(__DIR__) . '/' . $relativePath;
}

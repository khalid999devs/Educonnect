<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Resources\ServeLocalResourceDownloadController;
use App\Http\Controllers\Api\V1\Resources\StoreLocalResourceUploadController;
use Illuminate\Support\Facades\Route;

/*
 * Development-only local-disk transport for resource files. These stand in for
 * the S3 presigned PUT/GET when RESOURCE_STORAGE_DISK points at a local driver.
 *
 * They are registered OUTSIDE the stateful `api` group on purpose: the browser
 * uploads the raw body with no cookies and no CSRF token (exactly as it would
 * to S3), so the short-lived signature is the sole authorization. The
 * controllers abort 404 unless the resource disk is a local driver, so the
 * routes exist but do nothing in production.
 */
Route::put('/resource-transport/upload', StoreLocalResourceUploadController::class)
    ->name('resources.local-upload');

Route::get('/resource-transport/download', ServeLocalResourceDownloadController::class)
    ->name('resources.local-download');

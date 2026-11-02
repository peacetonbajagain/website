<?php
/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('admin', ['namespace' => 'Travo\Admin\Controllers'], static function ($routes) {
    $routes->get('/', 'Dashboard::index', ['filter' => 'permission:admin.access']);

    $routes->get('settings', 'Settings::index', ['filter' => 'permission:admin.settings']);
    $routes->post('settings', 'Settings::save', ['filter' => 'permission:admin.settings']);
    $routes->post('settings/permissions', 'Settings::updatePermissions', ['filter' => 'permission:admin.settings']);
    $routes->get('settings/email', 'Settings::index', ['filter' => 'permission:admin.settings']);
    $routes->post('settings/email', 'Settings::emailsave', ['filter' => 'permission:admin.settings']);
    $routes->post('settings/email/test', 'Settings::testEmail', ['filter' => 'permission:admin.settings']);
    $routes->get('settings/general', 'Settings::index', ['filter' => 'permission:admin.settings']);
    $routes->post('settings/general', 'Settings::saveGeneral', ['filter' => 'permission:admin.settings']);
    $routes->get('settings/homepage', 'Settings::index', ['filter' => 'permission:admin.settings']);
    $routes->post('settings/homepage', 'Settings::saveHomepage', ['filter' => 'permission:admin.settings']);
    $routes->post('settings/homepage/restore', 'Settings::restoreHomepage', ['filter' => 'permission:admin.settings']);
    $routes->post('settings/cache/clear', 'Settings::clearCache', ['filter' => 'permission:admin.settings']);

    $routes->get('posts', 'PostsController::index', ['filter' => 'permission:content.manage']);
    $routes->get('posts/new', 'PostsController::create', ['filter' => 'permission:content.manage']);
    $routes->post('posts/create', 'PostsController::save', ['filter' => 'permission:content.manage']);
    $routes->get('posts/edit/(:num)', 'PostsController::edit/$1', ['filter' => 'permission:content.manage']);
    $routes->post('posts/update/(:num)', 'PostsController::update/$1', ['filter' => 'permission:content.manage']);
    $routes->post('posts/delete/(:num)', 'PostsController::delete/$1', ['filter' => 'permission:content.manage']);
    $routes->post('posts/published', 'PostsController::updatePublished', ['filter' => 'permission:content.manage']);
    $routes->post('posts/featured', 'PostsController::updateFeatured', ['filter' => 'permission:content.manage']);
    $routes->post('posts/breaking', 'PostsController::updateBreaking', ['filter' => 'permission:content.manage']);
    $routes->post('posts/trending', 'PostsController::updateTrending', ['filter' => 'permission:content.manage']);
    $routes->post('posts/bulk-delete', 'PostsController::bulkDelete', ['filter' => 'permission:content.manage']);
    $routes->get('posts/tags', 'PostsController::tagsSearch', ['filter' => 'permission:content.manage']);
    $routes->post('posts/autosave', 'PostsController::autosave', ['filter' => 'permission:content.manage']);

    $routes->get('pages', 'Pages::index', ['filter' => 'permission:content.manage']);
    $routes->get('pages/new', 'Pages::create', ['filter' => 'permission:content.manage']);
    $routes->post('pages/create', 'Pages::save', ['filter' => 'permission:content.manage']);
    $routes->get('pages/edit/(:num)', 'Pages::Edit/$1', ['filter' => 'permission:content.manage']);
    $routes->post('pages/update/(:num)', 'Pages::update/$1', ['filter' => 'permission:content.manage']);
    $routes->post('pages/published', 'Pages::updatePublished', ['filter' => 'permission:content.manage']);
    $routes->post('pages/delete/(:num)', 'Pages::delete/$1', ['filter' => 'permission:content.manage']);
    $routes->post('pages/autosave', 'Pages::autosave', ['filter' => 'permission:content.manage']);

    $routes->get('posts/categories', 'CategoriesController::index', ['filter' => 'permission:content.manage']);
    $routes->get('posts/categories/new', 'CategoriesController::create', ['filter' => 'permission:content.manage']);
    $routes->post('posts/categories/create', 'CategoriesController::save', ['filter' => 'permission:content.manage']);
    $routes->get('posts/categories/edit/(:num)', 'CategoriesController::edit/$1', ['filter' => 'permission:content.manage']);
    $routes->post('posts/categories/update', 'CategoriesController::update', ['filter' => 'permission:content.manage']);
    $routes->post('posts/categories/published', 'CategoriesController::updatePublished', ['filter' => 'permission:content.manage']);
    $routes->post('posts/categories/delete/(:num)', 'CategoriesController::delete/$1', ['filter' => 'permission:content.manage']);
    $routes->get('categories', 'CategoriesController::index', ['filter' => 'permission:content.manage']);
    $routes->post('categories/create', 'CategoriesController::save', ['filter' => 'permission:content.manage']);
    $routes->post('categories/delete/(:num)', 'CategoriesController::delete/$1', ['filter' => 'permission:content.manage']);

    $routes->get('comments', 'CommentsController::index', ['filter' => 'permission:comments.moderate']);
    $routes->get('comments/(:num)', 'CommentsController::details/$1', ['filter' => 'permission:comments.moderate']);
    $routes->post('comments/status/(:num)/(:segment)', 'CommentsController::status/$1/$2', ['filter' => 'permission:comments.moderate']);
    $routes->post('comments/approve/(:num)', 'CommentsController::approve/$1', ['filter' => 'permission:comments.moderate']);
    $routes->post('comments/reject/(:num)', 'CommentsController::reject/$1', ['filter' => 'permission:comments.moderate']);
    $routes->post('comments/update/(:num)', 'CommentsController::update/$1', ['filter' => 'permission:comments.moderate']);
    $routes->post('comments/reply/(:num)', 'CommentsController::reply/$1', ['filter' => 'permission:comments.moderate']);
    $routes->post('comments/delete/(:num)', 'CommentsController::delete/$1', ['filter' => 'permission:comments.moderate']);

    $routes->get('submissions', 'StorySubmissionsController::index', ['filter' => 'permission:content.manage']);
    $routes->get('submissions/image/(:num)', 'StorySubmissionsController::image/$1', ['filter' => 'permission:content.manage']);
    $routes->post('submissions/approve/(:num)', 'StorySubmissionsController::approve/$1', ['filter' => 'permission:content.manage']);
    $routes->post('submissions/reject/(:num)', 'StorySubmissionsController::reject/$1', ['filter' => 'permission:content.manage']);

    $routes->get('users', 'UsersController::index', ['filter' => 'permission:users.view']);
    $routes->get('users/new', 'UsersController::create', ['filter' => 'permission:users.create']);
    $routes->post('users/create', 'UsersController::save', ['filter' => 'permission:users.create']);
    $routes->post('users/role/(:num)', 'UsersController::role/$1', ['filter' => 'permission:users.edit']);
    $routes->post('users/status/(:num)', 'UsersController::status/$1', ['filter' => 'permission:users.edit']);
    $routes->post('users/delete-batch', 'UsersController::deleteBatch', ['filter' => 'permission:users.delete']);
    $routes->post('users/delete/(:num)', 'UsersController::delete/$1', ['filter' => 'permission:users.delete']);

    $routes->get('gallery', 'UploadController::galleryIndex', ['filter' => 'permission:media.manage']);
    $routes->get('uploader', 'UploadController::galleryIndex', ['filter' => 'permission:media.manage']);
    $routes->post('gallery/upload', 'UploadController::upload', ['filter' => 'permission:media.manage']);
    $routes->get('gallery/files', 'UploadController::get_uploaded_files', ['filter' => 'permission:media.manage']);
    $routes->get('gallery/editor-files', 'UploadController::get_uploaded_files_editor', ['filter' => 'permission:media.manage']);
    $routes->post('gallery/preview-files', 'UploadController::get_preview_files', ['filter' => 'permission:media.manage']);
    $routes->get('gallery/file-url', 'UploadController::getUrl', ['filter' => 'permission:media.manage']);
    $routes->get('gallery/picker-grid', 'UploadController::pickerGrid', ['filter' => 'permission:media.manage']);
    $routes->post('gallery/delete-batch', 'UploadController::deleteBatch', ['filter' => 'permission:media.manage']);
    $routes->post('gallery/rename', 'UploadController::renameInGallery', ['filter' => 'permission:media.manage']);
    $routes->post('gallery/delete', 'UploadController::deleteFromGallery', ['filter' => 'permission:media.manage']);
});

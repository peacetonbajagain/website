<?php
/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('admin', ['namespace' => 'Travo\Admin\Controllers'], static function ($routes) {
    $routes->get('/', 'DashboardController::index', ['filter' => 'permission:admin.access']);

    $routes->get('settings', 'SettingsController::index', ['filter' => 'permission:admin.settings']);
    $routes->post('settings', 'SettingsController::update', ['filter' => 'permission:admin.settings']);
    $routes->post('settings/permissions', 'SettingsController::updatePermissions', ['filter' => 'permission:admin.settings']);

    $routes->get('posts', 'PostsController::index', ['filter' => 'permission:content.manage']);
    $routes->get('posts/new', 'PostsController::new', ['filter' => 'permission:content.manage']);
    $routes->post('posts/create', 'PostsController::create', ['filter' => 'permission:content.manage']);
    $routes->get('posts/edit/(:num)', 'PostsController::edit/$1', ['filter' => 'permission:content.manage']);
    $routes->post('posts/update/(:num)', 'PostsController::update/$1', ['filter' => 'permission:content.manage']);
    $routes->post('posts/delete/(:num)', 'PostsController::delete/$1', ['filter' => 'permission:content.manage']);

    $routes->get('pages', 'PagesController::index', ['filter' => 'permission:content.manage']);
    $routes->get('pages/new', 'PagesController::new', ['filter' => 'permission:content.manage']);
    $routes->post('pages/create', 'PagesController::create', ['filter' => 'permission:content.manage']);
    $routes->get('pages/edit/(:num)', 'PagesController::edit/$1', ['filter' => 'permission:content.manage']);
    $routes->post('pages/update/(:num)', 'PagesController::update/$1', ['filter' => 'permission:content.manage']);
    $routes->post('pages/delete/(:num)', 'PagesController::delete/$1', ['filter' => 'permission:content.manage']);

    $routes->get('categories', 'CategoriesController::index', ['filter' => 'permission:content.manage']);
    $routes->post('categories/create', 'CategoriesController::create', ['filter' => 'permission:content.manage']);
    $routes->post('categories/delete/(:num)', 'CategoriesController::delete/$1', ['filter' => 'permission:content.manage']);

    $routes->get('comments', 'CommentsController::index', ['filter' => 'permission:comments.moderate']);
    $routes->post('comments/status/(:num)/(:segment)', 'CommentsController::status/$1/$2', ['filter' => 'permission:comments.moderate']);
    $routes->post('comments/delete/(:num)', 'CommentsController::delete/$1', ['filter' => 'permission:comments.moderate']);

    $routes->get('users', 'UsersController::index', ['filter' => 'permission:users.view']);
    $routes->post('users/create', 'UsersController::create', ['filter' => 'permission:users.create']);
    $routes->post('users/role/(:num)', 'UsersController::role/$1', ['filter' => 'permission:users.edit']);
    $routes->post('users/status/(:num)', 'UsersController::status/$1', ['filter' => 'permission:users.edit']);
    $routes->post('users/delete/(:num)', 'UsersController::delete/$1', ['filter' => 'permission:users.delete']);

    $routes->get('gallery', 'GalleryController::index', ['filter' => 'permission:media.manage']);
    $routes->post('gallery/upload', 'GalleryController::upload', ['filter' => 'permission:media.manage']);
    $routes->post('gallery/rename', 'GalleryController::rename', ['filter' => 'permission:media.manage']);
    $routes->post('gallery/delete', 'GalleryController::delete', ['filter' => 'permission:media.manage']);
});

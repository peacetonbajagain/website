<?php
/** @var \CodeIgniter\Router\RouteCollection $routes */

// Default namespace (app/Config/Routing.php) = Travo\FrontEnd\Controllers
$routes->get('/', 'HomeController::index');
$routes->get('blog', 'PostController::index');
$routes->get('blog/category/(:segment)', 'PostController::category/$1');
$routes->get('blog/(:segment)', 'PostController::show/$1');
$routes->post('blog/comment', 'PostController::comment');
$routes->get('pages/(:segment)', 'PageController::show/$1');
$routes->get('search', 'SearchController::index');

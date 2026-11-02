<?php
/** @var \CodeIgniter\Router\RouteCollection $routes */

// Default namespace (app/Config/Routing.php) = Travo\FrontEnd\Controllers
$routes->get('/', 'HomeController::index');
$routes->get('blog', 'PostController::index');
$routes->get('blog/category/(:segment)', 'CategoryController::details/$1');
$routes->get('category/(:segment)', 'CategoryController::details/$1');
$routes->get('blog/submit', 'PostController::submit');
$routes->post('blog/submit', 'PostController::saveSubmission');
$routes->get('blog/(:segment)', 'PostController::PostDetails/$1');
$routes->post('blog/comment', 'PostController::comment');
$routes->post('blog/view/(:num)', 'PostController::recordView/$1');
$routes->get('pages/(:segment)', 'PageController::index/$1');
$routes->get('advertisement', 'PageController::advertisement');
$routes->post('advertisement/inquire', 'PageController::inquireAdvertisement');
$routes->post('contact-us', 'PageController::sendContact');
$routes->get('search', 'SearchController::index');

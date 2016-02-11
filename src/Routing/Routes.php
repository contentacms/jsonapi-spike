<?php
/**
 * @file
 * Contains \Drupal\jsonapi\Routing\Routes.
 */

namespace Drupal\jsonapi\Routing;
use Symfony\Component\Routing\Route;
/**
 * Defines dynamic routes.
 */
class Routes {

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $routes = array();
    // Declares a single route under the name 'example.content'.
    // Returns an array of Route objects.
    $routes['jsonapi.dynamic'] = new Route(
      // Path to attach this route to:
      '/example',
      // Route defaults:
      array(
        '_controller' => '\Drupal\jsonapi\Controller\HelloController::hello',
      ),
      // Route requirements:
      array(
        '_permission'  => 'access content',
      )
    );
    return $routes;
  }

}
?>
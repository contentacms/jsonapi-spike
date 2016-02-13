<?php
/**
 * @file
 * Contains \Drupal\jsonapi\Routing\Routes.
 */

namespace Drupal\jsonapi\Routing;
use Drupal\jsonapi\HardCodedConfig;
use Symfony\Component\Routing\Route;


/**
 * Defines dynamic routes.
 */
class Routes {

    public function __construct() {
        $this->config = new HardCodedConfig();
    }


    /**
     * {@inheritdoc}
     */
    public function routes() {
        $routes = [];
        foreach($this->config->endpoints() as $endpoint) {
            $path = $endpoint['scope'] . $endpoint['path'];
            $routeKey = join('.', ['jsonapi', 'dynamic', $endpoint['scope'], $endpoint['path']]);
            $requirements = ['_permission'  => 'access content' ];

            // Collection endpoint, like /api/v1/photos
            $routes[$routeKey . '.' . 'collection'] = new Route(
                $path,
                [
                    '_controller' => '\Drupal\jsonapi\Controller\EndpointController::handleCollection',
                    'scope' => $endpoint['scope'],
                    'endpoint' => $endpoint['path']
                ],
                $requirements
            );

            // Individual endpoint, like /api/v1/photos/123
            $routes[$routeKey . '.' . 'individual'] = new Route(
                $path . '/{id}',
                [
                    '_controller' => '\Drupal\jsonapi\Controller\EndpointController::handleIndividual',
                    'scope' => $endpoint['scope'],
                    'endpoint' => $endpoint['path']
                ],
                $requirements
            );

            // Related endpoint, like /api/v1/photos/123/comments
            $routes[$routeKey . '.' . 'related'] = new Route(
                $path . '/{id}/{related}',
                [
                    '_controller' => '\Drupal\jsonapi\Controller\HelloController::hello',
                    'scope' => $endpoint['scope'],
                    'endpoint' => $endpoint['path']
                ],
                $requirements
            );

            // Relationship endpoint, like /api/v1/photos/123/relationships/comments
            // http://jsonapi.org/recommendations/#urls-relationships
            $routes[$routeKey . '.' . 'relationship'] = new Route(
                $path . '/{id}/relationships/{related}',
                [
                    '_controller' => '\Drupal\jsonapi\Controller\HelloController::hello',
                    'scope' => $endpoint['scope'],
                    'endpoint' => $endpoint['path']
                ],
                $requirements
            );

        }
        return $routes;
    }

}
?>
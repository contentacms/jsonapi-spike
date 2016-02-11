<?php
/**
 * @file
 * Contains \Drupal\jsonapi\Controller\HelloController.
 */

namespace Drupal\jsonapi\Controller;

use Drupal\jsonapi\Response;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * DemoController.
 */
class HelloController {
    /**
     * Generates an example page.
     */
    public function hello(RouteMatchInterface $route_match, Request $request) {
        $response = new Response(["query_params" => $request->query->all(), "route_params" => $route_match->getRawParameters()->all()], 200);
        if ($response instanceof Response && $data = $response->getResponseData()) {
            $output = Json::encode($data);
            $response->setContent($output);
            $response->headers->set('Content-Type', 'application/vnd.api+json');
        }
        return $response;
    }

}
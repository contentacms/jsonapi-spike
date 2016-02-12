<?php
/**
 * @file
 * Contains \Drupal\jsonapi\Controller\NodeController.
 */

namespace Drupal\jsonapi\Controller;

use Drupal\jsonapi\Response;
use Drupal\jsonapi\DocumentContext;
use Drupal\jsonapi\HardCodedConfig;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;


/**
 * DemoController.
 */
class NodeController implements ContainerAwareInterface {

    use ContainerAwareTrait;

    public function __construct() {
        $this->config = new HardCodedConfig();
    }

    public function handle($nid, Request $request) {

        $context = [
            "debug" => !!$request->query->get("debug")
        ];

        $response = $this->makeResponse($nid);

        if ($response instanceof Response && $data = $response->getResponseData()) {
            $serializer = $this->container->get('serializer');
            $output = $serializer->serialize($data, "jsonapi", $context);
            $response->setContent($output);
            $response->headers->set('Content-Type', 'application/vnd.api+json');
        }
        return $response;
    }

    protected function makeResponse($nid) {
        $node = Node::load($nid);

        if (!$node) {
            # http://jsonapi.org/format/#error-objects
            return new Response(["errors" => [["title" => "Node not found", "detail" => "A node with nid=" . $nid . " was not found."]]], 404);
        }


        if (!$node->access('view')) {
            # http://jsonapi.org/format/#error-objects
            return new Response(["errors" => [["title" => "Access denied to node", "detail" => "Access denied to node with nid=" . $nid . "."]]], 403);
        }
        return new Response(new DocumentContext($node), 200);
    }

}
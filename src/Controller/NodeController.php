<?php
/**
 * @file
 * Contains \Drupal\jsonapi\Controller\NodeController.
 */

namespace Drupal\jsonapi\Controller;

use Drupal\jsonapi\Response;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * DemoController.
 */
class NodeController implements ContainerAwareInterface {

    use ContainerAwareTrait;

    /**
     * Generates an example page.
     */
    public function handle($nid) {

        $response = $this->makeResponse($nid);

        if ($response instanceof Response && $data = $response->getResponseData()) {
            $serializer = $this->container->get('serializer');
            $output = $serializer->serialize($data, "jsonapi");
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

        // $content["meta"] = [ "keys" => [] ];
        // foreach ($node as $name => $field) {
        //     $content["meta"]["keys"][] = $name;
        // }
        return new Response([ "node" => $node, "normal" => $this->container->get('serializer')->normalize($node) ], 200);
    }

}